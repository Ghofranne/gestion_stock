<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
        private ?TranslatorInterface $translator = null,
        private string $defaultLocale = 'fr'
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20], // before RouterListener
        ];
        
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $this->requestStack->getSession();
        if (!$session) {
            return;
        }

        // Resolve current user identifier (if authenticated)
        $userIdentifier = 'guest';
        $token = $this->tokenStorage->getToken();
        if ($token) {
            $user = $token->getUser();
            if (\is_object($user) && method_exists($user, 'getUserIdentifier')) {
                $userIdentifier = (string) $user->getUserIdentifier();
            }
        }

        // Load preferences from session
        $prefsKey = 'preferences_' . $userIdentifier;
        $prefs = $session->get($prefsKey);
        if (!$prefs) {
            // Fallback: pick the first preferences_* entry found
            foreach ($session->all() as $key => $value) {
                if (str_starts_with((string) $key, 'preferences_')) {
                    $prefs = $value;
                    break;
                }
            }
        }

        $language = is_array($prefs) && isset($prefs['language']) ? (string)$prefs['language'] : null;
        $locale = match ($language) {
            'English' => 'en',
            'Français' => 'fr',
            default => $session->get('_locale', $this->defaultLocale),
        };

        // Persist and apply locale
        $session->set('_locale', $locale);
        $request->setLocale($locale);
        if ($this->translator) {
            $this->translator->setLocale($locale);
        }
    }
}
