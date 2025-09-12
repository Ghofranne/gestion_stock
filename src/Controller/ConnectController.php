<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\Google;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ConnectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ParameterBagInterface $params,
        private readonly UserActivityLogger $activityLogger,
    ) {}

    private function getEnv(string $key, ?string $default = null): ?string
    {
        return $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    private function makeGoogleProvider(): Google
    {
        return new Google([
            'clientId'     => (string) $this->getEnv('GOOGLE_CLIENT_ID', ''),
            'clientSecret' => (string) $this->getEnv('GOOGLE_CLIENT_SECRET', ''),
            'redirectUri'  => (string) $this->getEnv('OAUTH_GOOGLE_REDIRECT_URI', 'http://127.0.0.1:8000/connect/google/check'),
        ]);
    }

    #[Route('/connect/google', name: 'app_connect_google')]
    public function connectGoogle(Request $request): RedirectResponse
    {
        $provider = $this->makeGoogleProvider();
        $authUrl = $provider->getAuthorizationUrl([
            'scope' => ['openid', 'email', 'profile'],
        ]);
        // CSRF state
        $request->getSession()->set('oauth2state', $provider->getState());
        return new RedirectResponse($authUrl);
    }

    #[Route('/connect/google/check', name: 'app_connect_google_check')]
    public function connectGoogleCheck(Request $request): Response
    {
        $provider = $this->makeGoogleProvider();
        $state = $request->query->get('state');
        $saved = $request->getSession()->get('oauth2state');
        
        // Log the OAuth callback attempt
        $this->activityLogger->log(
            null,
            'auth',
            'Tentative de connexion Google OAuth',
            [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'state_matches' => ($state && $state === $saved)
            ]
        );
        
        if (!$state || $state !== $saved) {
            $request->getSession()->remove('oauth2state');
            $this->addFlash('error', 'notifications.generic_error');
            return $this->redirectToRoute('app_login');
        }

        try {
            $token = $provider->getAccessToken('authorization_code', [
                'code' => (string) $request->query->get('code'),
            ]);
            $googleUser = $provider->getResourceOwner($token);
            $email = $googleUser->getEmail();
            $name = $googleUser->getName();

            if (!$email) {
                $this->addFlash('error', 'notifications.generic_error');
                return $this->redirectToRoute('app_login');
            }

            $user = $this->users->findOneBy(['email' => $email]);
            $isNewUser = false;
            
            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                // random password (not used)
                $rand = bin2hex(random_bytes(16));
                $user->setPassword($this->hasher->hashPassword($user, $rand));
                if ($name) {
                    $parts = preg_split('/\s+/', trim($name));
                    if ($parts) {
                        $user->setPrenom($parts[0] ?? null);
                        if (count($parts) > 1) {
                            $user->setNom(implode(' ', array_slice($parts, 1)) ?: null);
                        }
                    }
                }
                $this->em->persist($user);
                $this->em->flush();
                $isNewUser = true;
                
                // Log new user creation via OAuth
                $this->activityLogger->log(
                    $user,
                    'create',
                    'Nouvel utilisateur créé via Google OAuth',
                    [
                        'email' => $email,
                        'name' => $name,
                        'source' => 'google_oauth'
                    ]
                );
            }

            // Log in programmatically (Symfony 7+)
            $this->security->login($user, 'main');
            
            // Log successful OAuth login
            $this->activityLogger->log(
                $user,
                'login',
                'Connexion réussie via Google OAuth',
                [
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'is_new_user' => $isNewUser
                ]
            );
            
            $this->addFlash('success', 'notifications.login_success');
            return $this->redirectToRoute('app_menu');
        } catch (\Throwable $e) {
            // Log OAuth error
            $this->activityLogger->log(
                null,
                'auth_error',
                'Échec de la connexion Google OAuth',
                [
                    'error' => $e->getMessage(),
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]
            );
            
            $this->addFlash('error', 'notifications.generic_error');
            return $this->redirectToRoute('app_login');
        }
    }
}
