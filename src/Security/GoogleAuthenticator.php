<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class GoogleAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly RouterInterface $router,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $client->getAccessToken();
        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $email = $googleUser->getEmail();
        $fullName = $googleUser->getName();

        return new SelfValidatingPassport(new UserBadge($email, function(string $userIdentifier) use ($fullName) {
            $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
            if (!$user) {
                $user = new User();
                $user->setEmail($userIdentifier);
                // Set a random hashed password (not used for Google login)
                $random = bin2hex(random_bytes(20));
                $hashed = $this->passwordHasher->hashPassword($user, $random);
                $user->setPassword($hashed);
                // Optionally set name if available
                if ($fullName) {
                    // naive split first/last names
                    $parts = preg_split('/\s+/', trim($fullName));
                    if ($parts) {
                        $user->setPrenom($parts[0] ?? null);
                        if (count($parts) > 1) {
                            $user->setNom(implode(' ', array_slice($parts, 1)) ?: null);
                        }
                    }
                }
                $this->em->persist($user);
                $this->em->flush();
            }
            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        // Redirect to main menu after successful login
        return new RedirectResponse($this->router->generate('app_menu'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Redirect back to login with an error message
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // Entry point if someone accesses a protected page
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
