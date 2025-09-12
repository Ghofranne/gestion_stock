<?php

namespace App\Controller;

use App\Entity\UserActivity;
use App\Repository\SocieteRepository;
use App\Repository\DepotRepository;
use App\Repository\EquipementRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\UserActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(
        SocieteRepository $societeRepository,
        DepotRepository $depotRepository,
        EquipementRepository $equipementRepository,
        MouvementStockRepository $mouvementRepository
    ): Response {
        // Statistiques générales
        $stats = [
            'total_societes' => $societeRepository->count([]),
            'total_depots' => $depotRepository->count([]),
            'total_equipements' => $equipementRepository->count([]),
            'total_mouvements' => $mouvementRepository->count([]),
        ];

        // Répartition des équipements par type
        $equipements = $equipementRepository->findAll();
        $equipementsByType = [];
        foreach ($equipements as $equipement) {
            $type = $equipement->getTypeEquipement();
            $equipementsByType[$type] = ($equipementsByType[$type] ?? 0) + 1;
        }

        // Mouvements récents (10 derniers)
        $recentMovements = $mouvementRepository->findBy([], ['date' => 'DESC'], 10);

        // Sociétés avec le plus de dépôts
        $societes = $societeRepository->findAll();
        $societesStats = [];
        foreach ($societes as $societe) {
            $societesStats[] = [
                'societe' => $societe,
                'nb_depots' => count($societe->getDepots()),
                'nb_mouvements' => $this->countMovementsBySociete($societe, $mouvementRepository)
            ];
        }
        
        // Trier par nombre de dépôts
        usort($societesStats, fn($a, $b) => $b['nb_depots'] <=> $a['nb_depots']);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'equipements_by_type' => $equipementsByType,
            'recent_movements' => $recentMovements,
            'societes_stats' => array_slice($societesStats, 0, 5), // Top 5
        ]);
    }

    #[Route('/societes', name: 'app_admin_societes', methods: ['GET'])]
    public function societes(
        SocieteRepository $societeRepository,
        DepotRepository $depotRepository,
        MouvementStockRepository $mouvementRepository
    ): Response {
        // Statistiques générales des sociétés
        $totalSocietes = $societeRepository->count([]);
        
        // Récupérer toutes les sociétés avec leurs données
        $societes = $societeRepository->findAll();
        $societesData = [];
        $totalDepots = 0;
        $totalMouvements = 0;
        $societesParVille = [];
        $nouvelleSocietes = 0;
        
        $dateDebutMois = new \DateTime('first day of this month');
        
        foreach ($societes as $societe) {
            $nbDepots = count($societe->getDepots());
            $nbMouvements = $this->countMovementsBySociete($societe, $mouvementRepository);
            
            // Compter les nouvelles sociétés ce mois (si createdAt existe)
            if (method_exists($societe, 'getCreatedAt') && $societe->getCreatedAt() && $societe->getCreatedAt() >= $dateDebutMois) {
                $nouvelleSocietes++;
            }
            
            // Grouper par ville
            $ville = $this->extractVilleFromAdresse($societe->getAdress());
            $societesParVille[$ville] = ($societesParVille[$ville] ?? 0) + 1;
            
            $societesData[] = [
                'societe' => $societe,
                'nb_depots' => $nbDepots,
                'nb_mouvements' => $nbMouvements,
                'ville' => $ville
            ];
            
            $totalDepots += $nbDepots;
            $totalMouvements += $nbMouvements;
        }
        
        // Trier les sociétés par nombre de mouvements (plus actives)
        usort($societesData, function($a, $b) {
            return $b['nb_mouvements'] <=> $a['nb_mouvements'];
        });
        
        // Top 5 des sociétés les plus actives
        $topSocietes = array_slice($societesData, 0, 5);
        
        // Moyennes
        $moyenneDepotsParSociete = $totalSocietes > 0 ? round($totalDepots / $totalSocietes, 1) : 0;
        $moyenneMouvementsParSociete = $totalSocietes > 0 ? round($totalMouvements / $totalSocietes, 1) : 0;
        
        // Trier les villes par nombre de sociétés
        arsort($societesParVille);
        
        return $this->render('admin/societes.html.twig', [
            'stats' => [
                'total_societes' => $totalSocietes,
                'total_depots' => $totalDepots,
                'total_mouvements' => $totalMouvements,
                'nouvelles_societes' => $nouvelleSocietes,
                'moyenne_depots' => $moyenneDepotsParSociete,
                'moyenne_mouvements' => $moyenneMouvementsParSociete
            ],
            'societes_data' => $societesData,
            'top_societes' => $topSocietes,
            'societes_par_ville' => $societesParVille
        ]);
    }

    #[Route('/stock', name: 'app_admin_stock', methods: ['GET'])]
    public function stock(
        EquipementRepository $equipementRepository,
        DepotRepository $depotRepository
    ): Response {
        $equipements = $equipementRepository->findAll();
        $depots = $depotRepository->findAll();
        
        // Statistiques par dépôt
        $stockByDepot = [];
        foreach ($depots as $depot) {
            $stockByDepot[$depot->getNom()] = 0;
            // Compter les équipements par dépôt via les mouvements
            // (logique simplifiée - à adapter selon vos besoins)
        }
        
        return $this->render('admin/stock.html.twig', [
            'equipements' => $equipements,
            'depots' => $depots,
            'stock_by_depot' => $stockByDepot,
        ]);
    }

    #[Route('/mouvements', name: 'app_admin_mouvements', methods: ['GET'])]
    public function mouvements(
        MouvementStockRepository $mouvementRepository,
        EquipementRepository $equipementRepository,
        DepotRepository $depotRepository,
        SocieteRepository $societeRepository
    ): Response {
        // Statistiques générales
        $totalMouvements = $mouvementRepository->count([]);
        $totalEquipements = $equipementRepository->count([]);
        $totalDepots = $depotRepository->count([]);
        $totalSocietes = $societeRepository->count([]);
        
        // Récupérer tous les mouvements avec leurs données
        $mouvements = $mouvementRepository->findBy([], ['date' => 'DESC']);
        $mouvementsData = [];
        $mouvementsParType = [];
        $mouvementsParMois = [];
        $mouvementsParDepot = [];
        $mouvementsParSociete = [];
        $mouvementsParEquipement = [];
        
        $dateDebutMois = new \DateTime('first day of this month');
        $mouvementsCeMois = 0;
        $mouvementsAujourdhui = 0;
        $dateAujourdhui = new \DateTime('today');
        
        foreach ($mouvements as $mouvement) {
            // Compter les mouvements ce mois
            if ($mouvement->getDate() >= $dateDebutMois) {
                $mouvementsCeMois++;
            }
            
            // Compter les mouvements aujourd'hui
            if ($mouvement->getDate()->format('Y-m-d') === $dateAujourdhui->format('Y-m-d')) {
                $mouvementsAujourdhui++;
            }
            
            // Grouper par type
            $type = $mouvement->getTypeMouvement() ?? 'Non spécifié';
            $mouvementsParType[$type] = ($mouvementsParType[$type] ?? 0) + 1;
            
            // Grouper par mois
            $mois = $mouvement->getDate()->format('Y-m');
            $mouvementsParMois[$mois] = ($mouvementsParMois[$mois] ?? 0) + 1;
            
            // Grouper par dépôt source
            if ($mouvement->getDepotSource()) {
                $depotNom = $mouvement->getDepotSource()->getNom();
                $mouvementsParDepot[$depotNom] = ($mouvementsParDepot[$depotNom] ?? 0) + 1;
            }
            
            // Grouper par dépôt destination
            if ($mouvement->getDepotDestination()) {
                $depotNom = $mouvement->getDepotDestination()->getNom();
                $mouvementsParDepot[$depotNom] = ($mouvementsParDepot[$depotNom] ?? 0) + 1;
            }
            
            // Grouper par société (via dépôt)
            $societe = null;
            if ($mouvement->getDepotSource() && $mouvement->getDepotSource()->getSociete()) {
                $societe = $mouvement->getDepotSource()->getSociete();
            } elseif ($mouvement->getDepotDestination() && $mouvement->getDepotDestination()->getSociete()) {
                $societe = $mouvement->getDepotDestination()->getSociete();
            }
            
            if ($societe) {
                $societeNom = $societe->getNom();
                $mouvementsParSociete[$societeNom] = ($mouvementsParSociete[$societeNom] ?? 0) + 1;
            }
            
            // Grouper par équipement
            if ($mouvement->getEquipement()) {
                $equipementNom = $mouvement->getEquipement()->getModel() ?? 'Modèle non spécifié';
                $mouvementsParEquipement[$equipementNom] = ($mouvementsParEquipement[$equipementNom] ?? 0) + 1;
            }
            
            $mouvementsData[] = [
                'mouvement' => $mouvement,
                'type' => $type,
                'societe' => $societe,
                'depot_source' => $mouvement->getDepotSource(),
                'depot_destination' => $mouvement->getDepotDestination(),
                'equipement' => $mouvement->getEquipement()
            ];
        }
        
        // Trier les groupements
        arsort($mouvementsParType);
        arsort($mouvementsParMois);
        arsort($mouvementsParDepot);
        arsort($mouvementsParSociete);
        arsort($mouvementsParEquipement);
        
        // Mouvements récents (20 derniers)
        $mouvementsRecents = array_slice($mouvementsData, 0, 20);
        
        // Calculer les moyennes
        $moyenneMouvementsParJour = $totalMouvements > 0 ? round($totalMouvements / 30, 1) : 0; // Approximation sur 30 jours
        
        return $this->render('admin/mouvements.html.twig', [
            'stats' => [
                'total_mouvements' => $totalMouvements,
                'total_equipements' => $totalEquipements,
                'total_depots' => $totalDepots,
                'total_societes' => $totalSocietes,
                'mouvements_ce_mois' => $mouvementsCeMois,
                'mouvements_aujourdhui' => $mouvementsAujourdhui,
                'moyenne_par_jour' => $moyenneMouvementsParJour
            ],
            'mouvements_data' => $mouvementsData,
            'mouvements_recents' => $mouvementsRecents,
            'mouvements_par_type' => $mouvementsParType,
            'mouvements_par_mois' => array_slice($mouvementsParMois, 0, 12, true), // 12 derniers mois
            'mouvements_par_depot' => array_slice($mouvementsParDepot, 0, 10, true), // Top 10
            'mouvements_par_societe' => array_slice($mouvementsParSociete, 0, 10, true), // Top 10
            'mouvements_par_equipement' => array_slice($mouvementsParEquipement, 0, 10, true) // Top 10
        ]);
    }

    #[Route('/rapports', name: 'app_admin_rapports', methods: ['GET'])]
    public function rapports(
        MouvementStockRepository $mouvementRepository,
        EquipementRepository $equipementRepository,
        DepotRepository $depotRepository,
        SocieteRepository $societeRepository
    ): Response {
        // Statistiques générales
        $totalMouvements = $mouvementRepository->count([]);
        $totalEquipements = $equipementRepository->count([]);
        $totalDepots = $depotRepository->count([]);
        $totalSocietes = $societeRepository->count([]);
        
        // Analyses temporelles
        $dateDebutMois = new \DateTime('first day of this month');
        $dateDebutAnnee = new \DateTime('first day of January this year');
        $dateDebutSemaine = new \DateTime('monday this week');
        
        $mouvementsCeMois = $mouvementRepository->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.date >= :debut')
            ->setParameter('debut', $dateDebutMois)
            ->getQuery()
            ->getSingleScalarResult();
            
        $mouvementsCetteAnnee = $mouvementRepository->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.date >= :debut')
            ->setParameter('debut', $dateDebutAnnee)
            ->getQuery()
            ->getSingleScalarResult();
            
        $mouvementsCetteSemaine = $mouvementRepository->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.date >= :debut')
            ->setParameter('debut', $dateDebutSemaine)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Top 5 des sociétés les plus actives
        $societes = $societeRepository->findAll();
        $societesActivite = [];
        foreach ($societes as $societe) {
            $nbMouvements = $this->countMovementsBySociete($societe, $mouvementRepository);
            if ($nbMouvements > 0) {
                $societesActivite[] = [
                    'societe' => $societe,
                    'nb_mouvements' => $nbMouvements,
                    'nb_depots' => count($societe->getDepots())
                ];
            }
        }
        usort($societesActivite, fn($a, $b) => $b['nb_mouvements'] <=> $a['nb_mouvements']);
        $topSocietes = array_slice($societesActivite, 0, 5);
        
        // Top 5 des dépôts les plus actifs
        $depots = $depotRepository->findAll();
        $depotsActivite = [];
        foreach ($depots as $depot) {
            $nbMouvementsSource = $mouvementRepository->count(['depotSource' => $depot]);
            $nbMouvementsDestination = $mouvementRepository->count(['depotDestination' => $depot]);
            $nbMouvementsTotal = $nbMouvementsSource + $nbMouvementsDestination;
            
            if ($nbMouvementsTotal > 0) {
                $depotsActivite[] = [
                    'depot' => $depot,
                    'nb_mouvements' => $nbMouvementsTotal,
                    'stock_actuel' => $this->calculateStockInDepot($depot, $mouvementRepository)
                ];
            }
        }
        usort($depotsActivite, fn($a, $b) => $b['nb_mouvements'] <=> $a['nb_mouvements']);
        $topDepots = array_slice($depotsActivite, 0, 5);
        
        // Top 5 des équipements les plus mobiles
        $equipements = $equipementRepository->findAll();
        $equipementsActivite = [];
        foreach ($equipements as $equipement) {
            $nbMouvements = $mouvementRepository->count(['equipement' => $equipement]);
            if ($nbMouvements > 0) {
                $equipementsActivite[] = [
                    'equipement' => $equipement,
                    'nb_mouvements' => $nbMouvements,
                    'depot_actuel' => $this->findCurrentDepotForEquipement($equipement, $mouvementRepository)
                ];
            }
        }
        usort($equipementsActivite, fn($a, $b) => $b['nb_mouvements'] <=> $a['nb_mouvements']);
        $topEquipements = array_slice($equipementsActivite, 0, 5);
        
        // Analyse des types de mouvements
        $mouvements = $mouvementRepository->findAll();
        $typesAnalyse = [];
        foreach ($mouvements as $mouvement) {
            $type = $mouvement->getTypeMouvement();
            if (!isset($typesAnalyse[$type])) {
                $typesAnalyse[$type] = [
                    'count' => 0,
                    'ce_mois' => 0,
                    'cette_semaine' => 0
                ];
            }
            $typesAnalyse[$type]['count']++;
            
            if ($mouvement->getDate() >= $dateDebutMois) {
                $typesAnalyse[$type]['ce_mois']++;
            }
            if ($mouvement->getDate() >= $dateDebutSemaine) {
                $typesAnalyse[$type]['cette_semaine']++;
            }
        }
        
        // Calcul des moyennes
        $moyenneParJour = $totalMouvements > 0 ? round($totalMouvements / 365, 1) : 0;
        $moyenneParMois = $totalMouvements > 0 ? round($totalMouvements / 12, 1) : 0;
        $moyenneParSociete = $totalSocietes > 0 ? round($totalMouvements / $totalSocietes, 1) : 0;
        $moyenneParDepot = $totalDepots > 0 ? round($totalMouvements / $totalDepots, 1) : 0;
        
        return $this->render('admin/rapports.html.twig', [
            'stats' => [
                'total_mouvements' => $totalMouvements,
                'total_equipements' => $totalEquipements,
                'total_depots' => $totalDepots,
                'total_societes' => $totalSocietes,
                'mouvements_ce_mois' => $mouvementsCeMois,
                'mouvements_cette_annee' => $mouvementsCetteAnnee,
                'mouvements_cette_semaine' => $mouvementsCetteSemaine,
                'moyenne_par_jour' => $moyenneParJour,
                'moyenne_par_mois' => $moyenneParMois,
                'moyenne_par_societe' => $moyenneParSociete,
                'moyenne_par_depot' => $moyenneParDepot
            ],
            'top_societes' => $topSocietes,
            'top_depots' => $topDepots,
            'top_equipements' => $topEquipements,
            'types_analyse' => $typesAnalyse
        ]);
    }

    #[Route('/parametres', name: 'app_admin_parametres', methods: ['GET'])]
    public function parametres(): Response
    {
        // Récupérer les paramètres actuels du système
        $parametres = [
            'roles' => [
                'ROLE_ADMIN' => 'Administrateur',
                'ROLE_MANAGER' => 'Gestionnaire'
            ],
            'session' => [
                'duree_max' => 3600, // 1 heure en secondes
                'inactivite_max' => 1800, // 30 minutes
                'remember_me' => true
            ],
            'password_policy' => [
                'longueur_min' => 8,
                'majuscules' => true,
                'minuscules' => true,
                'chiffres' => true,
                'caracteres_speciaux' => true,
                'historique' => 5 // Nombre de mots de passe précédents à retenir
            ],
            'two_factor' => [
                'active' => false,
                'methodes' => ['email', 'sms', 'app'],
                'methode_defaut' => 'email',
                'codes_backup' => 10
            ],
            'permissions' => [
                'ROLE_ADMIN' => [
                    'gestion_utilisateurs',
                    'gestion_societes',
                    'gestion_depots',
                    'gestion_equipements',
                    'gestion_mouvements',
                    'rapports_avances',
                    'parametres_systeme'
                ],
                'ROLE_MANAGER' => [
                    'gestion_depots',
                    'gestion_equipements',
                    'gestion_mouvements',
                    'rapports_standards'
                ],
                'ROLE_USER' => [
                    'consultation_equipements',
                    'consultation_mouvements',
                    'rapports_basiques'
                ]
            ]
        ];
        
        return $this->render('admin/parametres.html.twig', [
            'parametres' => $parametres
        ]);
    }

    #[Route('/parametres/update', name: 'app_admin_parametres_update', methods: ['POST'])]
    public function updateParametres(Request $request): Response
    {
        // Traitement de la mise à jour des paramètres
        // Pour l'instant, on simule juste la sauvegarde
        
        $this->addFlash('success', 'Paramètres mis à jour avec succès !');
        
        return $this->redirectToRoute('app_admin_parametres');
    }

    #[Route('/utilisateurs', name: 'app_admin_utilisateurs', methods: ['GET'])]
    public function utilisateurs(\App\Repository\UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        
        // Statistiques des utilisateurs
        $stats = [
            'total_users' => count($users),
            'admins' => count(array_filter($users, fn($u) => in_array('ROLE_ADMIN', $u->getRoles()))),
            'managers' => count(array_filter($users, fn($u) => in_array('ROLE_MANAGER', $u->getRoles()) && !in_array('ROLE_ADMIN', $u->getRoles())))
        ];
        
        return $this->render('admin/utilisateurs.html.twig', [
            'users' => $users,
            'stats' => $stats
        ]);
    }

    #[Route('/utilisateurs/nouveau', name: 'app_admin_utilisateur_nouveau', methods: ['GET', 'POST'])]
    public function nouveauUtilisateur(Request $request, \Doctrine\ORM\EntityManagerInterface $entityManager, \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new \App\Entity\User();
        
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->all('roles') ?? [];
            $enabled = $request->request->has('enabled');
            $twoFAEnabled = $request->request->has('two_factor_enabled');
            $twoFADefault = $this->sanitizeTwoFactorMethod((string) $request->request->get('two_factor_default_method', 'email'));
            $generateBackup = $request->request->has('generate_backup_codes');
            
            // Validation basique
            if (empty($email) || empty($password)) {
                $this->addFlash('error', 'Email et mot de passe sont obligatoires.');
                return $this->render('admin/utilisateur_form.html.twig', [
                    'user' => $user,
                    'mode' => 'create'
                ]);
            }
            
            // Vérifier si l'email existe déjà
            $existingUser = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $this->addFlash('error', 'Un utilisateur avec cet email existe déjà.');
                return $this->render('admin/utilisateur_form.html.twig', [
                    'user' => $user,
                    'mode' => 'create'
                ]);
            }
            
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setRoles($roles);
            $user->setEnabled($enabled);

            // 2FA settings
            $user->setTwoFactorEnabled($twoFAEnabled);
            $user->setTwoFactorDefaultMethod($twoFADefault);

            if ($twoFAEnabled && $generateBackup) {
                [$plainCodes, $hashedCodes] = $this->generateBackupCodes();
                $user->setTwoFactorBackupCodes($hashedCodes);
                $this->addFlash('success', 'Codes de secours générés: ' . implode(' ', $plainCodes));
            }
            
            $entityManager->persist($user);
            $entityManager->flush();
            
            $this->addFlash('success', 'Utilisateur créé avec succès !');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }
        
        return $this->render('admin/utilisateur_form.html.twig', [
            'user' => $user,
            'mode' => 'create'
        ]);
    }

    #[Route('/utilisateurs/{id}/modifier', name: 'app_admin_utilisateur_modifier', methods: ['GET', 'POST'])]
    public function modifierUtilisateur(int $id, Request $request, \Doctrine\ORM\EntityManagerInterface $entityManager, \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $entityManager->getRepository(\App\Entity\User::class)->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $roles = $request->request->all('roles') ?? [];
            $enabled = $request->request->has('enabled');
            $twoFAEnabled = $request->request->has('two_factor_enabled');
            $twoFADefault = $this->sanitizeTwoFactorMethod((string) $request->request->get('two_factor_default_method', 'email'));
            $generateBackup = $request->request->has('generate_backup_codes');
            
            if (empty($email)) {
                $this->addFlash('error', 'L\'email est obligatoire.');
                return $this->render('admin/utilisateur_form.html.twig', [
                    'user' => $user,
                    'mode' => 'edit'
                ]);
            }
            
            // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
            $existingUser = $entityManager->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $this->addFlash('error', 'Un autre utilisateur avec cet email existe déjà.');
                return $this->render('admin/utilisateur_form.html.twig', [
                    'user' => $user,
                    'mode' => 'edit'
                ]);
            }
            
            // Empêcher de désactiver son propre compte
            if ($user === $this->getUser() && $enabled === false) {
                $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
                return $this->render('admin/utilisateur_form.html.twig', [
                    'user' => $user,
                    'mode' => 'edit'
                ]);
            }

            $user->setEmail($email);
            $user->setRoles($roles);
            $user->setEnabled($enabled);
            
            // Mettre à jour le mot de passe seulement s'il est fourni
            if (!empty($password)) {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
            }

            // 2FA settings
            $user->setTwoFactorEnabled($twoFAEnabled);
            $user->setTwoFactorDefaultMethod($twoFADefault);

            if ($twoFAEnabled && $generateBackup) {
                [$plainCodes, $hashedCodes] = $this->generateBackupCodes();
                $user->setTwoFactorBackupCodes($hashedCodes);
                $this->addFlash('success', 'Nouveaux codes de secours: ' . implode(' ', $plainCodes));
            }
            
            $entityManager->flush();
            
            $this->addFlash('success', 'Utilisateur modifié avec succès !');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }
        
        return $this->render('admin/utilisateur_form.html.twig', [
            'user' => $user,
            'mode' => 'edit'
        ]);
    }

    private function sanitizeTwoFactorMethod(string $method): string
    {
        $allowed = ['email', 'totp', 'sms'];
        return in_array($method, $allowed, true) ? $method : 'email';
    }

    /**
     * @return array{0: array<int,string>, 1: array<int,string>} [plainCodes, hashedCodes]
     */
    private function generateBackupCodes(int $count = 10): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
            $plain[] = $code;
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }
        return [$plain, $hashed];
    }

    #[Route('/utilisateurs/{id}/supprimer', name: 'app_admin_utilisateur_supprimer', methods: ['POST'])]
    public function supprimerUtilisateur(int $id, \Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(\App\Entity\User::class)->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        // Empêcher la suppression de son propre compte
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_utilisateurs');
        }
        
        $entityManager->remove($user);
        $entityManager->flush();
        
        $this->addFlash('success', 'Utilisateur supprimé avec succès !');
        return $this->redirectToRoute('app_admin_utilisateurs');
    }

    #[Route('/rapports/export/{type}', name: 'app_admin_export_report', methods: ['GET'])]
    public function exportReport(
        string $type,
        MouvementStockRepository $mouvementRepository,
        EquipementRepository $equipementRepository,
        DepotRepository $depotRepository,
        SocieteRepository $societeRepository
    ): Response {
        // Générer les données selon le type de rapport
        $data = $this->generateReportData($type, $mouvementRepository, $equipementRepository, $depotRepository, $societeRepository);
        
        // Générer le contenu CSV
        $csvContent = $this->generateCsvContent($data, $type);
        
        // Créer la réponse avec les en-têtes appropriés
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="rapport_' . $type . '_' . date('Y-m-d') . '.csv"');
        
        return $response;
    }

    #[Route('/rapports/export-format/{format}', name: 'app_admin_export_format', methods: ['GET'])]
    public function exportFormat(
        string $format,
        MouvementStockRepository $mouvementRepository,
        EquipementRepository $equipementRepository,
        DepotRepository $depotRepository,
        SocieteRepository $societeRepository
    ): Response {
        // Générer les données pour l'export complet
        $data = $this->generateCompleteReportData($mouvementRepository, $equipementRepository, $depotRepository, $societeRepository);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data);
            case 'excel':
                return $this->exportToExcel($data);
            case 'pdf':
                return $this->exportToPdf($data);
            default:
                throw $this->createNotFoundException('Format d\'export non supporté');
        }
    }

    private function generateReportData(string $type, $mouvementRepository, $equipementRepository, $depotRepository, $societeRepository): array
    {
        switch ($type) {
            case 'monthly':
                return $this->generateMonthlyReport($mouvementRepository);
            case 'yearly':
                return $this->generateYearlyReport($mouvementRepository);
            case 'activity':
                return $this->generateActivityReport($mouvementRepository, $equipementRepository);
            case 'performance':
                return $this->generatePerformanceReport($mouvementRepository, $depotRepository, $societeRepository);
            case 'trends':
                return $this->generateTrendsReport($mouvementRepository);
            case 'audit':
                return $this->generateAuditReport($mouvementRepository);
            default:
                return [];
        }
    }

    private function generateMonthlyReport($mouvementRepository): array
    {
        $dateDebut = new \DateTime('first day of this month');
        $dateFin = new \DateTime('last day of this month');
        
        $mouvements = $mouvementRepository->createQueryBuilder('m')
            ->where('m.date >= :debut AND m.date <= :fin')
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->orderBy('m.date', 'DESC')
            ->getQuery()
            ->getResult();
        
        $data = [];
        foreach ($mouvements as $mouvement) {
            $data[] = [
                'Date' => $mouvement->getDate()->format('d/m/Y H:i'),
                'Type' => $mouvement->getTypeMouvement(),
                'Equipement' => $mouvement->getEquipement() ? $mouvement->getEquipement()->getModel() : 'N/A',
                'Dépôt Source' => $mouvement->getDepotSource() ? $mouvement->getDepotSource()->getNom() : 'N/A',
                'Dépôt Destination' => $mouvement->getDepotDestination() ? $mouvement->getDepotDestination()->getNom() : 'N/A',
                // Quantité supprimée: chaque mouvement correspond à 1 unité implicite
                'Commentaire' => $mouvement->getCommentaire() ?? ''
            ];
        }
        
        return $data;
    }

    private function generateYearlyReport($mouvementRepository): array
    {
        $dateDebut = new \DateTime('first day of January this year');
        $dateFin = new \DateTime('last day of December this year');
        
        $mouvements = $mouvementRepository->createQueryBuilder('m')
            ->where('m.date >= :debut AND m.date <= :fin')
            ->setParameter('debut', $dateDebut)
            ->setParameter('fin', $dateFin)
            ->orderBy('m.date', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Grouper par mois
        $data = [];
        $moisStats = [];
        
        foreach ($mouvements as $mouvement) {
            $mois = $mouvement->getDate()->format('Y-m');
            if (!isset($moisStats[$mois])) {
                $moisStats[$mois] = [
                    'mois' => $mouvement->getDate()->format('F Y'),
                    'total' => 0,
                    'entrees' => 0,
                    'sorties' => 0,
                    'transferts' => 0
                ];
            }
            
            $moisStats[$mois]['total']++;
            $type = $mouvement->getTypeMouvement();
            if ($type === 'entree') $moisStats[$mois]['entrees']++;
            elseif ($type === 'sortie') $moisStats[$mois]['sorties']++;
            elseif ($type === 'transfert') $moisStats[$mois]['transferts']++;
        }
        
        foreach ($moisStats as $stats) {
            $data[] = $stats;
        }
        
        return $data;
    }

    private function generateActivityReport($mouvementRepository, $equipementRepository): array
    {
        $equipements = $equipementRepository->findAll();
        $data = [];
        
        foreach ($equipements as $equipement) {
            $nbMouvements = $mouvementRepository->count(['equipement' => $equipement]);
            $dernierMouvement = $mouvementRepository->findOneBy(
                ['equipement' => $equipement],
                ['date' => 'DESC']
            );
            
            $data[] = [
                'Equipement' => $equipement->getModel() ?? 'Modèle non spécifié',
                'Type' => $equipement->getTypeEquipement(),
                'Série' => $equipement->getSn() ?? 'N/A',
                'IMEI' => $equipement->getImei() ?? 'N/A',
                'Nb Mouvements' => $nbMouvements,
                'Dernier Mouvement' => $dernierMouvement ? $dernierMouvement->getDate()->format('d/m/Y') : 'Jamais',
                'Etat' => $equipement->getEtat()
            ];
        }
        
        return $data;
    }

    private function generateCsvContent(array $data, string $type): string
    {
        if (empty($data)) {
            return "Aucune donnée disponible pour le rapport $type\n";
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Ajouter l'en-tête
        fputcsv($output, array_keys($data[0]), ';');
        
        // Ajouter les données
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }

    private function exportToCsv(array $data): Response
    {
        $csvContent = $this->generateCsvContent($data, 'complet');
        
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="rapport_complet_' . date('Y-m-d') . '.csv"');
        
        return $response;
    }

    private function exportToExcel(array $data): Response
    {
        // Pour l'instant, on utilise CSV comme fallback
        // Vous pouvez installer PhpSpreadsheet pour un vrai support Excel
        return $this->exportToCsv($data);
    }

    private function exportToPdf(array $data): Response
    {
        // Générer un HTML simple pour le PDF
        $html = $this->renderView('admin/pdf_report.html.twig', [
            'data' => $data,
            'date' => new \DateTime()
        ]);
        
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html');
        
        return $response;
    }

    private function generateCompleteReportData($mouvementRepository, $equipementRepository, $depotRepository, $societeRepository): array
    {
        // Combiner toutes les données importantes
        $mouvements = $mouvementRepository->findBy([], ['date' => 'DESC'], 100);
        $data = [];
        
        foreach ($mouvements as $mouvement) {
            $data[] = [
                'Date' => $mouvement->getDate()->format('d/m/Y H:i'),
                'Type' => $mouvement->getTypeMouvement(),
                'Equipement' => $mouvement->getEquipement() ? $mouvement->getEquipement()->getModel() : 'N/A',
                'Type Equipement' => $mouvement->getEquipement() ? $mouvement->getEquipement()->getTypeEquipement() : 'N/A',
                'Dépôt Source' => $mouvement->getDepotSource() ? $mouvement->getDepotSource()->getNom() : 'N/A',
                'Dépôt Destination' => $mouvement->getDepotDestination() ? $mouvement->getDepotDestination()->getNom() : 'N/A',
                'Société' => $mouvement->getDepotDestination() && $mouvement->getDepotDestination()->getSociete() ? 
                    $mouvement->getDepotDestination()->getSociete()->getNom() : 'N/A',
                // Quantité supprimée: chaque mouvement = 1 unité implicite
                'Commentaire' => $mouvement->getCommentaire() ?? ''
            ];
        }
        
        return $data;
    }

    // Méthodes manquantes pour les autres types de rapports
    private function generatePerformanceReport($mouvementRepository, $depotRepository, $societeRepository): array
    {
        return []; // À implémenter selon vos besoins
    }

    private function generateTrendsReport($mouvementRepository): array
    {
        return []; // À implémenter selon vos besoins
    }

    private function generateAuditReport($mouvementRepository): array
    {
        return []; // À implémenter selon vos besoins
    }

    #[Route('/depots', name: 'app_admin_depots', methods: ['GET'])]
    public function depots(
        DepotRepository $depotRepository,
        SocieteRepository $societeRepository,
        MouvementStockRepository $mouvementRepository,
        EquipementRepository $equipementRepository
    ): Response {
        // Statistiques générales des dépôts
        $totalDepots = $depotRepository->count([]);
        $totalSocietes = $societeRepository->count([]);
        
        // Récupérer tous les dépôts avec leurs données
        $depots = $depotRepository->findAll();
        $depotsData = [];
        $totalMouvements = 0;
        $depotsParSociete = [];
        $depotsParVille = [];
        $stockParDepot = [];
        
        foreach ($depots as $depot) {
            // Compter les mouvements pour ce dépôt
            $nbMouvementsEntree = $mouvementRepository->count(['depotDestination' => $depot]);
            $nbMouvementsSortie = $mouvementRepository->count(['depotSource' => $depot]);
            $nbMouvements = $nbMouvementsEntree + $nbMouvementsSortie;
            
            // Calculer le stock actuel dans ce dépôt
            $stockActuel = $this->calculateStockInDepot($depot, $mouvementRepository);
            $stockParDepot[$depot->getId()] = $stockActuel;
            
            // Grouper par société
            $societeNom = $depot->getSociete() ? $depot->getSociete()->getNom() : 'Non assigné';
            $depotsParSociete[$societeNom] = ($depotsParSociete[$societeNom] ?? 0) + 1;
            
            // Grouper par ville
            $ville = $this->extractVilleFromAdresse($depot->getAdresse());
            $depotsParVille[$ville] = ($depotsParVille[$ville] ?? 0) + 1;
            
            $depotsData[] = [
                'depot' => $depot,
                'nb_mouvements' => $nbMouvements,
                'nb_mouvements_entree' => $nbMouvementsEntree,
                'nb_mouvements_sortie' => $nbMouvementsSortie,
                'stock_actuel' => $stockActuel,
                'ville' => $ville,
                'societe_nom' => $societeNom
            ];
            
            $totalMouvements += $nbMouvements;
        }
        
        // Trier les dépôts par stock (plus fournis)
        usort($depotsData, function($a, $b) {
            return $b['stock_actuel'] <=> $a['stock_actuel'];
        });
        
        // Top 5 des dépôts les plus fournis
        $topDepots = array_slice($depotsData, 0, 5);
        
        // Calculer les moyennes
        $moyenneStockParDepot = $totalDepots > 0 ? round(array_sum($stockParDepot) / $totalDepots, 1) : 0;
        $moyenneMouvementsParDepot = $totalDepots > 0 ? round($totalMouvements / $totalDepots, 1) : 0;
        
        // Trier les groupements
        arsort($depotsParSociete);
        arsort($depotsParVille);
        
        // Calculer les statistiques d'activité
        $depotsActifs = count(array_filter($depotsData, fn($d) => $d['nb_mouvements'] > 0));
        $depotsAvecStock = count(array_filter($stockParDepot, fn($stock) => $stock > 0));
        
        return $this->render('admin/depots.html.twig', [
            'stats' => [
                'total_depots' => $totalDepots,
                'total_societes' => $totalSocietes,
                'total_mouvements' => $totalMouvements,
                'total_stock' => array_sum($stockParDepot),
                'moyenne_stock' => $moyenneStockParDepot,
                'moyenne_mouvements' => $moyenneMouvementsParDepot,
                'depots_actifs' => $depotsActifs,
                'depots_avec_stock' => $depotsAvecStock
            ],
            'depots_data' => $depotsData,
            'top_depots' => $topDepots,
            'depots_par_societe' => $depotsParSociete,
            'depots_par_ville' => $depotsParVille
        ]);
    }

    #[Route('/equipements', name: 'app_admin_equipements', methods: ['GET'])]
    public function equipements(
        EquipementRepository $equipementRepository,
        SocieteRepository $societeRepository,
        DepotRepository $depotRepository,
        MouvementStockRepository $mouvementRepository
    ): Response {
        // Statistiques générales des équipements
        $totalEquipements = $equipementRepository->count([]);
        $totalSocietes = $societeRepository->count([]);
        $totalDepots = $depotRepository->count([]);
        
        // Récupérer tous les équipements avec leurs données
        $equipements = $equipementRepository->findAll();
        $equipementsData = [];
        $equipementsParType = [];
        $equipementsParEtat = [];
        $equipementsParSociete = [];
        $equipementsParDepot = [];
        $totalMouvements = 0;
        
        foreach ($equipements as $equipement) {
            // Compter les mouvements pour cet équipement
            $nbMouvements = $mouvementRepository->count(['equipement' => $equipement]);
            $totalMouvements += $nbMouvements;
            
            // Trouver le dépôt actuel de l'équipement
            $depotActuel = $this->findCurrentDepotForEquipement($equipement, $mouvementRepository);
            $societeActuelle = $depotActuel ? $depotActuel->getSociete() : null;
            
            // Grouper par type
            $type = $equipement->getTypeEquipement() ?? 'Non spécifié';
            $equipementsParType[$type] = ($equipementsParType[$type] ?? 0) + 1;
            
            // Grouper par état
            $etat = $equipement->getEtat() ?? 'Non spécifié';
            $equipementsParEtat[$etat] = ($equipementsParEtat[$etat] ?? 0) + 1;
            
            // Grouper par société
            $societeNom = $societeActuelle ? $societeActuelle->getNom() : 'Non assigné';
            $equipementsParSociete[$societeNom] = ($equipementsParSociete[$societeNom] ?? 0) + 1;
            
            // Grouper par dépôt
            $depotNom = $depotActuel ? $depotActuel->getNom() : 'Non assigné';
            $equipementsParDepot[$depotNom] = ($equipementsParDepot[$depotNom] ?? 0) + 1;
            
            $equipementsData[] = [
                'equipement' => $equipement,
                'nb_mouvements' => $nbMouvements,
                'depot_actuel' => $depotActuel,
                'societe_actuelle' => $societeActuelle,
                'type' => $type,
                'etat' => $etat
            ];
        }
        
        // Trier les équipements par nombre de mouvements (plus actifs)
        usort($equipementsData, function($a, $b) {
            return $b['nb_mouvements'] <=> $a['nb_mouvements'];
        });
        
        // Top 5 des équipements les plus actifs
        $topEquipements = array_slice($equipementsData, 0, 5);
        
        // Calculer les moyennes
        $moyenneMouvementsParEquipement = $totalEquipements > 0 ? round($totalMouvements / $totalEquipements, 1) : 0;
        
        // Trier les groupements
        arsort($equipementsParType);
        arsort($equipementsParEtat);
        arsort($equipementsParSociete);
        arsort($equipementsParDepot);
        
        // Calculer les statistiques d'état
        $equipementsActifs = count(array_filter($equipementsData, fn($e) => $e['etat'] === 'Actif'));
        $equipementsEnStock = count(array_filter($equipementsData, fn($e) => $e['depot_actuel'] !== null));
        
        return $this->render('admin/equipements.html.twig', [
            'stats' => [
                'total_equipements' => $totalEquipements,
                'total_societes' => $totalSocietes,
                'total_depots' => $totalDepots,
                'total_mouvements' => $totalMouvements,
                'moyenne_mouvements' => $moyenneMouvementsParEquipement,
                'equipements_actifs' => $equipementsActifs,
                'equipements_en_stock' => $equipementsEnStock
            ],
            'equipements_data' => $equipementsData,
            'top_equipements' => $topEquipements,
            'equipements_par_type' => $equipementsParType,
            'equipements_par_etat' => $equipementsParEtat,
            'equipements_par_societe' => $equipementsParSociete,
            'equipements_par_depot' => $equipementsParDepot
        ]);
    }



    private function countMovementsBySociete($societe, MouvementStockRepository $mouvementRepository): int
    {
        $count = 0;
        foreach ($societe->getDepots() as $depot) {
            $count += $mouvementRepository->count(['depotSource' => $depot]);
            $count += $mouvementRepository->count(['depotDestination' => $depot]);
        }
        return $count;
    }

    private function extractVilleFromAdresse(?string $adresse): string
    {
        if (!$adresse) {
            return 'Non spécifiée';
        }
        
        // Extraire la ville de l'adresse (dernière partie après la virgule)
        $parts = explode(',', $adresse);
        $ville = trim(end($parts));
        
        // Si pas de virgule, prendre les derniers mots
        if (count($parts) === 1) {
            $words = explode(' ', trim($adresse));
            $ville = implode(' ', array_slice($words, -2)); // Prendre les 2 derniers mots
        }
        
        return $ville ?: 'Non spécifiée';
    }

    private function calculateStockInDepot($depot, MouvementStockRepository $mouvementRepository): int
    {
        $stockTotal = 0;
        
        // Récupérer tous les mouvements d'entrée dans ce dépôt
        $mouvementsEntree = $mouvementRepository->findBy(['depotDestination' => $depot]);
        foreach ($mouvementsEntree as $mouvement) {
            $stockTotal += 1; // chaque mouvement = 1 unité
        }
        
        // Soustraire tous les mouvements de sortie de ce dépôt
        $mouvementsSortie = $mouvementRepository->findBy(['depotSource' => $depot]);
        foreach ($mouvementsSortie as $mouvement) {
            $stockTotal -= 1; // chaque mouvement = 1 unité
        }
        
        return max(0, $stockTotal); // Ne pas retourner de stock négatif
    }

    private function findCurrentDepotForEquipement($equipement, MouvementStockRepository $mouvementRepository)
    {
        // Récupérer le dernier mouvement de cet équipement
        $dernierMouvement = $mouvementRepository->findOneBy(
            ['equipement' => $equipement],
            ['date' => 'DESC']
        );
        
        if (!$dernierMouvement) {
            return null;
        }
        
        // Si c'est un mouvement d'entrée, l'équipement est dans le dépôt de destination
        if ($dernierMouvement->getTypeMouvement() === 'Entrée') {
            return $dernierMouvement->getDepotDestination();
        }
        
        // Si c'est un mouvement de sortie, l'équipement n'est plus dans le dépôt
        if ($dernierMouvement->getTypeMouvement() === 'Sortie') {
            return null;
        }
        
        // Si c'est un transfert, l'équipement est dans le dépôt de destination
        if ($dernierMouvement->getTypeMouvement() === 'Transfert') {
            return $dernierMouvement->getDepotDestination();
        }
        
        // Par défaut, retourner le dépôt de destination du dernier mouvement
        return $dernierMouvement->getDepotDestination();
    }

    /**
     * Affiche l'historique des activités de tous les utilisateurs
     */
    #[Route('/historique', name: 'app_admin_historique', methods: ['GET'])]
    public function historique(UserActivityRepository $activityRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Récupérer toutes les activités, triées par date décroissante
        $activities = $activityRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            // Exclure l'historique de connexion/déconnexion
            ->andWhere('a.action NOT IN (:excludedActions)')
            ->setParameter('excludedActions', [UserActivity::ACTION_LOGIN, UserActivity::ACTION_LOGOUT])
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/historique.html.twig', [
            'activities' => $activities,
        ]);
    }

    /**
     * Supprime une activité de l'historique
     */
    #[Route('/historique/{id}/supprimer', name: 'app_admin_historique_delete', methods: ['POST'])]
    public function deleteActivity(int $id, UserActivityRepository $activityRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $activity = $activityRepository->find($id);
        
        if (!$activity) {
            $this->addFlash('error', 'Activité introuvable.');
            return $this->redirectToRoute('app_admin_historique');
        }
        
        $entityManager->remove($activity);
        $entityManager->flush();
        
        $this->addFlash('success', 'L\'activité a été supprimée avec succès.');
        return $this->redirectToRoute('app_admin_historique');
    }

    /**
     * Supprime plusieurs activités de l'historique en lot
     */
    #[Route('/historique/supprimer-selection', name: 'app_admin_historique_delete_bulk', methods: ['POST'])]
    public function deleteBulkActivities(Request $request, UserActivityRepository $activityRepository, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete_activities', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_admin_historique');
        }
        
        $activityIds = $request->request->all('activity_ids');
        
        if (empty($activityIds)) {
            $this->addFlash('error', 'Aucune activité sélectionnée pour la suppression.');
            return $this->redirectToRoute('app_admin_historique');
        }
        
        $deletedCount = 0;
        
        foreach ($activityIds as $id) {
            $activity = $activityRepository->find((int)$id);
            if ($activity) {
                $entityManager->remove($activity);
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            $entityManager->flush();
            $this->addFlash('success', sprintf('%d activité(s) supprimée(s) avec succès.', $deletedCount));
        } else {
            $this->addFlash('warning', 'Aucune activité n\'a pu être supprimée.');
        }
        
        return $this->redirectToRoute('app_admin_historique');
    }
}
