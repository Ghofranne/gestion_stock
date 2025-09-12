<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Service\TwoFactorService;

class TwoFactorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TwoFactorService $twoFactorService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 15]]; // before RouterListener (default 32)
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $request = $event->getRequest();

        // Allow these paths without 2FA gate
        $route = (string) $request->attributes->get('_route');
        $allowedRoutes = [
            'app_login', 'app_logout',
            'app_2fa_challenge', 'app_2fa_verify', 'app_2fa_resend',
            // password reset
            'app_forgot_password', 'app_reset_password',
        ];
        if (in_array($route, $allowedRoutes, true)) { return; }

        $token = $this->tokenStorage->getToken();
        if (!$token || !is_object($user = $token->getUser())) { return; }

        if (method_exists($user, 'isTwoFactorEnabled') && $user->isTwoFactorEnabled()) {
            $session = $request->getSession();
            if (!$session->get('2fa_passed')) {
                // Save target path for after verification
                $session->set('2fa_target_path', $request->getRequestUri());
                // Send code proactively on first gate
                try {
                    $this->twoFactorService->sendEmailCode($user);
                } catch (\Throwable $e) {
                    // Avoid breaking the request cycle; code can be resent from challenge page
                }
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_2fa_challenge')));
            }
        }
    }
}
