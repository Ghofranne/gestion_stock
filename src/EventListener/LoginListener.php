<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\UserActivityLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: 'security.interactive_login')]
#[AsEventListener(event: 'security.logout')]
class LoginListener
{
    public function __construct(
        private UserActivityLogger $activityLogger
    ) {
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if ($user instanceof User) {
            $this->activityLogger->logLogin($user);
        }
    }

    public function onSecurityLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        
        if ($user instanceof User) {
            $this->activityLogger->logLogout($user);
        }
    }
}
