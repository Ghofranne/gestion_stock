<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Repository\SocieteRepository;
use App\Repository\EquipementRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\DepotRepository;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface as Hasher;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mime\Email;

class SecurityController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/menu', name: 'app_menu')]
    public function menu(SocieteRepository $societeRepository): Response
    {
        return $this->render('menu.html.twig', [
            'societes' => $societeRepository->findAll(),
        ]);
    }
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_menu');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * Demande de réinitialisation du mot de passe (saisie email)
     */
    #[Route(path: '/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, UserRepository $users, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $user = $email !== '' ? $users->findOneBy(['email' => $email]) : null;

            // Toujours afficher un message générique pour ne pas révéler l'existence d'un compte
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $user->setResetToken(substr($token, 0, 64));
                $user->setResetRequestedAt(new \DateTimeImmutable());
                $em->flush();

                // Envoyer le mail réel avec le lien de réinitialisation
                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $user->getResetToken()], UrlGeneratorInterface::ABSOLUTE_URL);
                $mail = (new TemplatedEmail())
                    ->from(new Address($_ENV['MAILER_FROM'] ?? 'no-reply@stockpro.local', 'StockPro'))
                    ->to(new Address($user->getEmail()))
                    ->subject('Réinitialisation de votre mot de passe')
                    ->htmlTemplate('emails/reset_password.html.twig')
                    ->context([
                        'user' => $user,
                        'reset_url' => $resetUrl,
                        'expires_in' => '1 heure',
                    ]);
                $mailer->send($mail);
            }

            $this->addFlash('success', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');
            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    /**
     * Réinitialisation via token
     */
    #[Route(path: '/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(string $token, Request $request, UserRepository $users, EntityManagerInterface $em, Hasher $hasher): Response
    {
        $user = $users->findOneBy(['resetToken' => $token]);
        if (!$user) {
            $this->addFlash('danger', 'Lien invalide ou expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        // Vérifier l'expiration (1h)
        $requestedAt = $user->getResetRequestedAt();
        if (!$requestedAt || $requestedAt < (new \DateTimeImmutable('-1 hour'))) {
            $this->addFlash('danger', 'Le lien de réinitialisation a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('new_password');
            $confirm = (string) $request->request->get('confirm_password');
            if ($password === '' || $confirm === '') {
                $error = 'Veuillez saisir et confirmer le nouveau mot de passe.';
            } elseif ($password !== $confirm) {
                $error = 'La confirmation ne correspond pas.';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères.';
            } else {
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setResetToken(null);
                $user->setResetRequestedAt(null);
                $em->flush();
                $this->addFlash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'error' => $error,
        ]);
    }


    /**
     * Page de profil (vue générale)
     */
    #[Route(path: '/profil', name: 'app_profil')]
    public function profil(
        EquipementRepository $equipementRepository,
        MouvementStockRepository $mouvementRepository,
        SocieteRepository $societeRepository,
        DepotRepository $depotRepository,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Calculer les statistiques de l'utilisateur
        $equipementsCount = $equipementRepository->count([]);
        $mouvementsCount = $mouvementRepository->count([]);
        $societesCount = $societeRepository->count([]);
        $depotsCount = $depotRepository->count([]);
        
        // Récupérer la dernière activité (dernier mouvement par date)
        $lastMovement = $mouvementRepository->findOneBy([], ['date' => 'DESC']);
        $lastActivity = $lastMovement ?
            'Mouvement ' . $lastMovement->getTypeMouvement() . ' - ' . $lastMovement->getDate()->format('d/m/Y H:i') :
            'Aucune activité récente';
        
        // Préférences depuis la session (fallback valeurs par défaut)
        $session = $request->getSession();
        $prefsKey = 'preferences_' . ($user?->getUserIdentifier() ?? 'guest');
        $prefs = $session->get($prefsKey, [
            'language' => 'Français',
            'date_format' => 'DD/MM/YYYY',
            'notifications' => true,
        ]);

        return $this->render('profil.html.twig', [
            'equipements_count' => $equipementsCount,
            'mouvements_count' => $mouvementsCount,
            'societes_count' => $societesCount,
            'depots_count' => $depotsCount,
            'last_activity' => $lastActivity,
            'preferences' => $prefs,
        ]);
    }

    /**
     * Préférences utilisateur (stockées en session pour éviter une migration DB)
     */
    #[Route(path: '/profil/preferences', name: 'app_profil_preferences')]
    public function preferences(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $session = $request->getSession();
        $prefsKey = 'preferences_' . $user->getUserIdentifier();
        $prefs = $session->get($prefsKey, [
            'language' => 'Français',
            'date_format' => 'DD/MM/YYYY',
            'notifications' => true,
        ]);

        if ($request->isMethod('POST')) {
            $language = (string) $request->request->get('language', $prefs['language']);
            $dateFormat = (string) $request->request->get('date_format', $prefs['date_format']);
            $notifications = $request->request->has('notifications');

            $prefs = [
                'language' => $language ?: 'Français',
                'date_format' => $dateFormat ?: 'DD/MM/YYYY',
                'notifications' => $notifications,
            ];
            $session->set($prefsKey, $prefs);
            // Apply locale immediately
            $locale = ($prefs['language'] === 'English') ? 'en' : 'fr';
            $session->set('_locale', $locale);
            $request->setLocale($locale);
            $this->addFlash('success', 'Préférences enregistrées.');
            return $this->redirectToRoute('app_profil');
        }

        return $this->render('profil_preferences.html.twig', [
            'preferences' => $prefs,
        ]);
    }

    /**
     * Modification d'informations du profil (email, prénom, nom, téléphone, poste)
     */
    #[Route(path: '/profil/edit', name: 'app_profil_edit')]
    public function editProfil(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $prenom = trim((string) $request->request->get('prenom')) ?: null;
            $nom = trim((string) $request->request->get('nom')) ?: null;
            $telephone = trim((string) $request->request->get('telephone')) ?: null;
            $poste = trim((string) $request->request->get('poste')) ?: null;

            $changed = false;
            if ($email !== '' && $email !== $user->getEmail()) {
                $user->setEmail($email);
                $changed = true;
            }
            if ($prenom !== $user->getPrenom()) { $user->setPrenom($prenom); $changed = true; }
            if ($nom !== $user->getNom()) { $user->setNom($nom); $changed = true; }
            if ($telephone !== $user->getTelephone()) { $user->setTelephone($telephone); $changed = true; }
            if ($poste !== $user->getPoste()) { $user->setPoste($poste); $changed = true; }

            if ($changed) {
                $em->flush();
                $this->addFlash('success', 'Profil mis à jour.');
                return $this->redirectToRoute('app_profil');
            }
            $this->addFlash('warning', "Aucun changement détecté.");
        }

        return $this->render('profil_edit.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Changement de mot de passe
     */
    #[Route(path: '/profil/password', name: 'app_profil_password')]
    public function changePassword(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $error = null;
        if ($request->isMethod('POST')) {
            $current = (string) $request->request->get('current_password');
            $new = (string) $request->request->get('new_password');
            $confirm = (string) $request->request->get('confirm_password');

            if ($new === '' || $confirm === '') {
                $error = 'Veuillez saisir et confirmer le nouveau mot de passe.';
            } elseif ($new !== $confirm) {
                $error = 'La confirmation ne correspond pas.';
            } elseif (!$passwordHasher->isPasswordValid($user, $current)) {
                $error = 'Le mot de passe actuel est incorrect.';
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $new));
                $em->flush();
                $this->addFlash('success', 'Mot de passe modifié avec succès.');
                return $this->redirectToRoute('app_profil');
            }
        }

        return $this->render('password_change.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Envoi d'email de test (auth requis) - à supprimer après vérification
     */
    #[Route(path: '/mail-test', name: 'app_mail_test', methods: ['GET'])]
    public function mailTest(Request $request, MailerInterface $mailer): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $to = trim((string) $request->query->get('to', (string) ($_ENV['MAILER_FROM'] ?? '')));
        if ($to === '') {
            return new JsonResponse(['ok' => false, 'error' => 'Paramètre "to" manquant et MAILER_FROM non défini.'], 400);
        }

        $email = (new Email())
            ->from(new Address($_ENV['MAILER_FROM'] ?? 'no-reply@stockpro.local', 'StockPro Test'))
            ->to($to)
            ->subject('Test email StockPro')
            ->html('<p>Ceci est un email de test depuis StockPro. Si vous le recevez, la configuration Mailer fonctionne ✅.</p>');

        $mailer->send($email);
        return new JsonResponse(['ok' => true, 'to' => $to]);
    }
}
