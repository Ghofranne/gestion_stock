<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\TwoFactorService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator, private TwoFactorService $twoFactorService)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('username', ''); // ou 'email' selon votre formulaire
        
        // Note: Security::LAST_USERNAME n'existe plus dans les versions récentes de Symfony
        // Cette fonctionnalité est maintenant gérée automatiquement

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Vérifier s'il y a un chemin cible spécifique (par exemple, page protégée)
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Récupérer l'utilisateur connecté
        $user = $token->getUser();

        // 2FA: si activé et non vérifié dans cette session, envoyer code et rediriger vers challenge
        $session = $request->getSession();
        if (is_object($user) && method_exists($user, 'isTwoFactorEnabled') && $user->isTwoFactorEnabled() && !$session->get('2fa_passed')) {
            // Envoyer le code email (par défaut)
            if (method_exists($user, 'getTwoFactorDefaultMethod') && $user->getTwoFactorDefaultMethod() === 'email') {
                $this->twoFactorService->sendEmailCode($user);
            }
            // Sauver chemin cible et rediriger
            if ($targetPath = $this->getTargetPath($session, $firewallName)) {
                $session->set('2fa_target_path', $targetPath);
            }
            return new RedirectResponse($this->urlGenerator->generate('app_2fa_challenge'));
        }
        
        // Si l'utilisateur est un admin, le rediriger vers la page d'administration
        if ($user && in_array('ROLE_ADMIN', $user->getRoles())) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        // Redirection par défaut après login réussi (utilisateurs normaux)
        return new RedirectResponse($this->urlGenerator->generate('app_menu'));
    }

    // 🚨 MÉTHODE MANQUANTE - C'est ce qui causait l'erreur fatale !
    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function supports(Request $request): bool
    {
        // L'authenticator ne s'exécute que lors de la soumission POST du formulaire
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }
}