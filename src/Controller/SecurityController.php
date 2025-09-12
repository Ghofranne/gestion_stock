<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Security\PasswordPolicy;
use App\Repository\PasswordHistoryRepository;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Repository\SocieteRepository;
use App\Repository\EquipementRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\UserActivityRepository;
use App\Repository\DepotRepository;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface as Hasher;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mime\Email;
use App\Service\UserActivityLogger;

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
        UserActivityRepository $activityRepository,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Calculer les statistiques de l'utilisateur
        $equipementsCount = $equipementRepository->count([]);
        $mouvementsCount = $mouvementRepository->count([]);
        $societesCount = $societeRepository->count([]);
        $depotsCount = $depotRepository->count([]);
        
        // Récupérer l'historique complet des activités de l'utilisateur connecté
        $userActivities = $activityRepository->findUserHistory($user, 5); // 5 dernières activités
        $totalUserActivities = $activityRepository->countUserActivities($user);
        
        // Récupérer la dernière activité
        $lastActivity = !empty($userActivities) ? $userActivities[0] : null;
        $lastActivityText = $lastActivity ?
            $lastActivity->getActionDescription() . ' - ' . $lastActivity->getCreatedAt()->format('d/m/Y H:i') :
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
            'mouvements_count' => $totalUserActivities, // Nombre total d'activités de l'utilisateur
            'societes_count' => $societesCount,
            'depots_count' => $depotsCount,
            'last_activity' => $lastActivityText,
            'preferences' => $prefs,
            'user_activities' => $userActivities, // Nouvelles activités complètes
            'total_user_activities' => $totalUserActivities,
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
        UserPasswordHasherInterface $passwordHasher,
        UserActivityLogger $activityLogger,
        PasswordPolicy $policy,
        PasswordHistoryRepository $historyRepo
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
            } elseif (($msg = $policy->validateComplexity($new)) !== null) {
                $error = $msg;
            } elseif ($policy->violatesHistory($user, $new) || password_verify($new, $user->getPassword())) {
                $error = sprintf('Vous ne pouvez pas réutiliser vos %d derniers mots de passe.', $policy->getHistoryDepth());
            } else {
                // Enregistrer l'ancien hash dans l'historique
                $oldHash = $user->getPassword();
                if ($oldHash) {
                    $history = new \App\Entity\PasswordHistory();
                    $history->setUser($user)->setPasswordHash($oldHash);
                    $em->persist($history);
                }

                // Définir le nouveau mot de passe
                $user->setPassword($passwordHasher->hashPassword($user, $new));
                $em->flush();

                // Purger l'historique au-delà de la profondeur autorisée
                $historyRepo->pruneExceeding($user, $policy->getHistoryDepth());
                
                // Logger l'activité
                $activityLogger->logPasswordChange();
                
                $this->addFlash('success', 'Mot de passe modifié avec succès.');
                return $this->redirectToRoute('app_profil');
            }
        }

        return $this->render('password_change.html.twig', [
            'error' => $error,
        ]);
    }

    /**
     * Page d'historique détaillé des activités de l'utilisateur
     */
    #[Route(path: '/profil/historique', name: 'app_profil_historique')]
    public function profilHistorique(UserActivityRepository $activityRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $user = $this->getUser();
        
        // Récupérer l'historique complet des activités de l'utilisateur
        $historique = $activityRepository->findUserHistory($user, 50);
        $totalActivites = $activityRepository->countUserActivities($user);
        $activitesRecentes = $activityRepository->findRecentUserActivities($user, 10);
        $statistiques = $activityRepository->getUserActivityStats($user);
        
        // Utilisation du nouveau template d'historique
        return $this->render('profile/historique_new.html.twig', [
            'user' => $user,
            'activites' => $historique,
            'totalActivites' => $totalActivites,
            'activitesRecentes' => $activitesRecentes,
            'statistiques' => $statistiques,
        ]);
    }

    /**
     * Supprime une ou plusieurs activités de l'historique
     */
    #[Route('/api/activities/delete', name: 'app_activity_delete', methods: ['POST'])]
    public function deleteActivities(Request $request, UserActivityRepository $activityRepository, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        // Désactivation volontaire de la suppression de l'historique
        return $this->json([
            'success' => false,
            'message' => 'La suppression des activités de l\'historique est désactivée.'
        ], 403);
    }
    
    /**
     * Exporte les activités sélectionnées au format CSV
     */
    #[Route('/export/activities', name: 'app_activity_export', methods: ['POST'])]
    public function exportActivities(Request $request, UserActivityRepository $activityRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        try {
            $ids = json_decode($request->request->get('ids', '[]'), true);
            $user = $this->getUser();
            
            $activities = $activityRepository->createQueryBuilder('a')
                ->where('a.id IN (:ids)')
                ->andWhere('a.user = :user')
                ->setParameter('ids', $ids)
                ->setParameter('user', $user)
                ->orderBy('a.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
            
            $filename = sprintf('export-activites-%s.csv', date('Y-m-d'));
            
            $response = $this->render('export/activities.csv.twig', [
                'activities' => $activities
            ]);
            
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
            
            return $response;
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'export');
            return $this->redirectToRoute('app_profil_historique');
        }
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
