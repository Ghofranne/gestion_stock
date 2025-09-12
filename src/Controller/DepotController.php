<?php

namespace App\Controller;

use App\Entity\Depot;
use App\Form\DepotType;
use App\Repository\DepotRepository;
use App\Repository\SocieteRepository;
use App\Repository\MouvementStockRepository;
use App\Service\UserActivityLogger;
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
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger,
        SocieteRepository $societeRepository
    ): Response {
        $depot = new Depot();

        // Prefill Societe if provided (e.g., from societe page modal)
        $societeId = $request->query->getInt('societe', 0);
        $prefilledSociete = null;
        if ($societeId > 0) {
            $prefilledSociete = $societeRepository->find($societeId);
            if ($prefilledSociete) {
                $depot->setSociete($prefilledSociete);
            }
        }

        $form = $this->createForm(DepotType::class, $depot, [
            'lock_societe' => (bool) $prefilledSociete,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure societe stays set when coming from a prefilled context
            if ($prefilledSociete && !$depot->getSociete()) {
                $depot->setSociete($prefilledSociete);
            }

            // Extra guard: Societe is required in our context
            if (!$depot->getSociete()) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => false,
                        'error' => 'societe_required',
                    ], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'notifications.depot.societe_required');
                return $this->render('depot/new.html.twig', [
                    'depot' => $depot,
                    'form' => $form,
                ]);
            }
            try {
                $entityManager->persist($depot);
                $entityManager->flush();
            } catch (\Throwable $e) {
                if ($request->isXmlHttpRequest()) {
                    return $this->json([
                        'success' => false,
                        'error' => 'persist_failed',
                        'message' => $e->getMessage(),
                    ], Response::HTTP_BAD_REQUEST);
                }
                $this->addFlash('error', 'notifications.depot.persist_failed');
                return $this->render('depot/new.html.twig', [
                    'depot' => $depot,
                    'form' => $form,
                ]);
            }
            
            // Log the creation activity
            $activityLogger->logCreate($depot, sprintf('Dépôt "%s" créé dans la société "%s"', 
                $depot->getNom(), 
                $depot->getSociete() ? $depot->getSociete()->getNom() : 'Aucune société'
            ));
            
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'id' => $depot->getId(),
                    'nom' => $depot->getNom(),
                    'adresse' => $depot->getAdresse(),
                ]);
            }

            $this->addFlash('success', 'notifications.depot.created');
            return $this->redirectToRoute('app_depot_index', [], Response::HTTP_SEE_OTHER);
        }

        // For embedded/AJAX usage, return only the form HTML
        if ($request->isXmlHttpRequest() || $request->query->getBoolean('embedded', false)) {
            return $this->render('depot/_form.html.twig', [
                'form' => $form,
                'button_label' => 'depot.form.save',
            ]);
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
                // Chaque mouvement concerne une unité (quantité implicite = 1)
                $stockParEquipement[$equipementId] += 1;
            }
        }
        
        // Soustraire les sorties
        foreach ($mouvementsSortie as $mouvement) {
            $equipement = $mouvement->getEquipement();
            if ($equipement) {
                $equipementId = $equipement->getId();
                if (isset($stockParEquipement[$equipementId])) {
                    // Chaque mouvement concerne une unité (quantité implicite = 1)
                    $stockParEquipement[$equipementId] -= 1;
                    
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
    public function edit(
        Request $request, 
        Depot $depot, 
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $form = $this->createForm(DepotType::class, $depot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $oldSociete = $depot->getSociete();
            $entityManager->flush();
            
            // Log the update activity
            $activityLogger->logUpdate($depot, sprintf('Dépôt "%s" mis à jour', $depot->getNom()));
            
            // Log if the societe has changed
            if ($oldSociete !== $depot->getSociete()) {
                $activityLogger->log(
                    $depot,
                    'update',
                    sprintf('Changement de société pour le dépôt "%s" de "%s" à "%s"', 
                        $depot->getNom(),
                        $oldSociete ? $oldSociete->getNom() : 'Aucune société',
                        $depot->getSociete() ? $depot->getSociete()->getNom() : 'Aucune société'
                    )
                );
            }
            
            $this->addFlash('success', 'notifications.depot.updated');
            return $this->redirectToRoute('app_depot_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('depot/edit.html.twig', [
            'depot' => $depot,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_depot_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        Depot $depot, 
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$depot->getId(), $request->getPayload()->getString('_token'))) {
            // Log the delete activity before removing the entity
            $activityLogger->logDelete(
                $depot, 
                sprintf('Dépôt "%s" supprimé de la société "%s"', 
                    $depot->getNom(),
                    $depot->getSociete() ? $depot->getSociete()->getNom() : 'Aucune société'
                )
            );
            
            $entityManager->remove($depot);
            $entityManager->flush();
            $this->addFlash('success', 'notifications.depot.deleted');
        }

        return $this->redirectToRoute('app_depot_index', [], Response::HTTP_SEE_OTHER);
    }
}
