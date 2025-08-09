<?php

namespace App\Controller;

use App\Entity\Depot;
use App\Form\DepotType;
use App\Repository\DepotRepository;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/depot')]
final class DepotController extends AbstractController
{
    #[Route(name: 'app_depot_index', methods: ['GET'])]
    public function index(DepotRepository $depotRepository): Response
    {
        return $this->render('depot/index.html.twig', [
            'depots' => $depotRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_depot_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $depot = new Depot();
        $form = $this->createForm(DepotType::class, $depot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($depot);
            $entityManager->flush();

            return $this->redirectToRoute('app_depot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot/new.html.twig', [
            'depot' => $depot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_show', methods: ['GET'])]
    public function show(Depot $depot, MouvementStockRepository $mouvementRepository): Response
    {
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
        
        return $this->render('depot/show.html.twig', [
            'depot' => $depot,
            'equipements_data' => $equipementsAvecQuantite,
            'total_equipements' => $totalQuantite
        ]);
    }

    #[Route('/{id}/edit', name: 'app_depot_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Depot $depot, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DepotType::class, $depot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_depot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot/edit.html.twig', [
            'depot' => $depot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_delete', methods: ['POST'])]
    public function delete(Request $request, Depot $depot, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$depot->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($depot);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_depot_index', [], Response::HTTP_SEE_OTHER);
    }
}
