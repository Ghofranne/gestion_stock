<?php

namespace App\Controller;

use App\Entity\Societe;
use App\Form\SocieteType;
use App\Repository\SocieteRepository;
use App\Repository\MouvementStockRepository;
use App\Repository\EquipementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/societe')]
final class SocieteController extends AbstractController
{
    #[Route(name: 'app_societe_index', methods: ['GET'])]
    public function index(SocieteRepository $societeRepository): Response
    {
        return $this->render('societe/index.html.twig', [
            'societes' => $societeRepository->findAll(),
        ]);
    }

    #[Route('/overview', name: 'app_societe_overview', methods: ['GET'])]
    public function overview(
        SocieteRepository $societeRepository,
        MouvementStockRepository $mouvementRepository,
        EquipementRepository $equipementRepository
    ): Response {
        $societes = $societeRepository->findAll();
        $data = [];

        foreach ($societes as $societe) {
            $depots = $societe->getDepots();
            $mouvements = [];
            $equipements = [];

            // Récupérer tous les mouvements liés aux dépôts de cette société
            foreach ($depots as $depot) {
                $depotMouvements = $mouvementRepository->findBy([
                    'depotSource' => $depot
                ]);
                $depotMouvementsDestination = $mouvementRepository->findBy([
                    'depotDestination' => $depot
                ]);
                $mouvements = array_merge($mouvements, $depotMouvements, $depotMouvementsDestination);
            }

            // Récupérer tous les équipements impliqués dans les mouvements
            $equipementIds = [];
            foreach ($mouvements as $mouvement) {
                if ($mouvement->getEquipement()) {
                    $equipementIds[] = $mouvement->getEquipement()->getId();
                }
            }
            
            if (!empty($equipementIds)) {
                $equipements = $equipementRepository->findBy([
                    'id' => array_unique($equipementIds)
                ]);
            }

            $data[] = [
                'societe' => $societe,
                'depots' => $depots,
                'mouvements' => array_unique($mouvements, SORT_REGULAR),
                'equipements' => $equipements,
                'stats' => [
                    'nb_depots' => count($depots),
                    'nb_mouvements' => count(array_unique($mouvements, SORT_REGULAR)),
                    'nb_equipements' => count($equipements)
                ]
            ];
        }

        return $this->render('societe/overview.html.twig', [
            'societes_data' => $data,
        ]);
    }

    #[Route('/new', name: 'app_societe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $societe = new Societe();
        $form = $this->createForm(SocieteType::class, $societe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($societe);
            $entityManager->flush();

            return $this->redirectToRoute('app_societe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('societe/new.html.twig', [
            'societe' => $societe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_societe_show', methods: ['GET'])]
    public function show(Societe $societe, MouvementStockRepository $mouvementRepository): Response
    {
        // Récupérer tous les dépôts de cette société
        $depots = $societe->getDepots();
        
        // Organiser les équipements par dépôt avec quantités
        $depotsData = [];
        foreach ($depots as $depot) {
            // Calculer le stock actuel pour chaque type d'équipement dans ce dépôt
            $stockParEquipement = [];
            $equipementsDetails = [];
            
            // Récupérer tous les mouvements liés à ce dépôt
            $mouvementsEntree = $mouvementRepository->findBy(['depotDestination' => $depot]);
            $mouvementsSortie = $mouvementRepository->findBy(['depotSource' => $depot]);
            
            // Calculer les entrées
            foreach ($mouvementsEntree as $mouvement) {
                $equipement = $mouvement->getEquipement();
                if ($equipement) {
                    $equipementId = $equipement->getId();
                    if (!isset($stockParEquipement[$equipementId])) {
                        $stockParEquipement[$equipementId] = 0;
                        $equipementsDetails[$equipementId] = $equipement;
                    }
                    // Ajouter la quantité d'entrée
                    $quantite = $mouvement->getQuantite() ?? 1;
                    $stockParEquipement[$equipementId] += $quantite;
                }
            }
            
            // Soustraire les sorties
            foreach ($mouvementsSortie as $mouvement) {
                $equipement = $mouvement->getEquipement();
                if ($equipement) {
                    $equipementId = $equipement->getId();
                    if (isset($stockParEquipement[$equipementId])) {
                        // Soustraire la quantité de sortie
                        $quantite = $mouvement->getQuantite() ?? 1;
                        $stockParEquipement[$equipementId] -= $quantite;
                        
                        // Supprimer si le stock devient négatif ou nul
                        if ($stockParEquipement[$equipementId] <= 0) {
                            unset($stockParEquipement[$equipementId]);
                            unset($equipementsDetails[$equipementId]);
                        }
                    }
                }
            }
            
            // Préparer les données pour l'affichage
            $equipementsAvecQuantite = [];
            $totalQuantite = 0;
            
            foreach ($stockParEquipement as $equipementId => $quantite) {
                if ($quantite > 0) {
                    $equipementsAvecQuantite[] = [
                        'equipement' => $equipementsDetails[$equipementId],
                        'quantite' => $quantite
                    ];
                    $totalQuantite += $quantite;
                }
            }
            
            $depotsData[] = [
                'depot' => $depot,
                'equipements' => $equipementsAvecQuantite,
                'nb_equipements' => $totalQuantite
            ];
        }
        
        return $this->render('societe/show.html.twig', [
            'societe' => $societe,
            'depots_data' => $depotsData,
            'total_depots' => count($depots),
            'total_equipements' => array_sum(array_column($depotsData, 'nb_equipements'))
        ]);
    }

    #[Route('/{id}/edit', name: 'app_societe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Societe $societe, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SocieteType::class, $societe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_societe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('societe/edit.html.twig', [
            'societe' => $societe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_societe_delete', methods: ['POST'])]
    public function delete(Request $request, Societe $societe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$societe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($societe);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_societe_index', [], Response::HTTP_SEE_OTHER);
    }
}
