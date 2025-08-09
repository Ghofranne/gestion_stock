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

class SecurityController extends AbstractController
{
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
     * Page de profil (vue générale)
     */
    #[Route(path: '/profil', name: 'app_profil')]
    public function profil(
        EquipementRepository $equipementRepository,
        MouvementStockRepository $mouvementRepository,
        SocieteRepository $societeRepository,
        DepotRepository $depotRepository
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
        
        return $this->render('profil.html.twig', [
            'equipements_count' => $equipementsCount,
            'mouvements_count' => $mouvementsCount,
            'societes_count' => $societesCount,
            'depots_count' => $depotsCount,
            'last_activity' => $lastActivity,
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
}
