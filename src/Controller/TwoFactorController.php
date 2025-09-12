<?php

namespace App\Controller;

use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TwoFactorController extends AbstractController
{
    #[Route('/2fa', name: 'app_2fa_challenge', methods: ['GET'])]
    public function challenge(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'isTwoFactorEnabled') || !$user->isTwoFactorEnabled()) {
            return $this->redirectToRoute('app_menu');
        }
        return $this->render('security/2fa_challenge.html.twig');
    }

    #[Route('/2fa/verify', name: 'app_2fa_verify', methods: ['POST'])]
    public function verify(Request $request, TwoFactorService $service): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $code = (string) $request->request->get('code', '');
        $backup = (string) $request->request->get('backup', '');

        $ok = false;
        if ($code !== '') {
            $ok = $service->verifyEmailCode($user, $code);
        } elseif ($backup !== '') {
            $ok = $service->consumeBackupCode($user, $backup);
        }

        if ($ok) {
            $request->getSession()->set('2fa_passed', true);
            $target = $request->getSession()->get('2fa_target_path');
            return $target ? $this->redirect($target) : $this->redirectToRoute('app_menu');
        }

        $this->addFlash('danger', 'Code invalide ou expiré.');
        return $this->redirectToRoute('app_2fa_challenge');
    }

    #[Route('/2fa/resend', name: 'app_2fa_resend', methods: ['POST'])]
    public function resend(Request $request, TwoFactorService $service): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $service->sendEmailCode($user);
        $this->addFlash('success', 'Nouveau code envoyé par email.');
        return $this->redirectToRoute('app_2fa_challenge');
    }
}
