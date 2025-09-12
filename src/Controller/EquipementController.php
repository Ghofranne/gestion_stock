<?php

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Entity\Equipement;
use App\Entity\Depot;
use App\Form\EquipementType;
use App\Repository\EquipementRepository;
use App\Repository\MouvementStockRepository;
use App\Service\UserActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/equipement')]
final class EquipementController extends AbstractController
{
    #[Route(name: 'app_equipement_index', methods: ['GET'])]
    public function index(EquipementRepository $equipementRepository, MouvementStockRepository $mouvRepo): Response
    {
        $equipements = $equipementRepository->findAll();
        // Calculer l'état courant à partir des mouvements: net>0 => Affecté, sinon Non disponible
        // MAIS: ne pas écraser explicitement l'état 'Retour' s'il est déjà défini sur l'entité
        $net = $mouvRepo->getNetStockByEquipement(); // [id => net]
        $statusById = [];
        foreach ($equipements as $e) {
            $id = $e->getId();
            $etatEntity = $e->getEtat();
            // Si l'entité est marquée 'Retour', on respecte cette valeur
            if ($etatEntity === 'Retour') {
                $statusById[$id] = 'Retour';
                continue;
            }
            // Sinon, ne surcharger l'état que si des mouvements existent pour cet équipement
            if (array_key_exists($id, $net)) {
                $statusById[$id] = ((int)$net[$id] > 0) ? 'Affecté' : 'Non disponible';
            }
        }

        return $this->render('equipement/index.html.twig', [
            'equipements' => $equipements,
            'statusById' => $statusById,
        ]);
    }

    #[Route('/new', name: 'app_equipement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserActivityLogger $activityLogger): Response
    {
        $depotId = $request->query->get('depot_id');
        $embedded = $request->query->get('embedded');
        $equipement = new Equipement();
        
        // Cacher le champ 'etat' lors de la création, il est défini par défaut à 'Disponible'
        $form = $this->createForm(EquipementType::class, $equipement, [
            'show_state' => false,
            'action' => $this->generateUrl('app_equipement_new', [
                'depot_id' => $depotId,
                'embedded' => $embedded
            ])
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($equipement);
            $entityManager->flush();
            
            // Logger l'activité
            $activityLogger->logCreate($equipement, sprintf('Équipement %s créé', $equipement->getModel() ?? 'sans modèle'));
            
            // Si on est en mode embarqué avec un dépôt, créer un mouvement d'entrée
            if ($embedded && $depotId) {
                $depot = $entityManager->getRepository(Depot::class)->find($depotId);
                if ($depot) {
                    $mouvement = new MouvementStock();
                    $mouvement->setTypeMouvement(MouvementStock::TYPE_ENTREE);
                    $mouvement->setDate(new \DateTime());
                    $mouvement->setDepotDestination($depot);
                    $mouvement->setCreatedBy($this->getUser());
                    $mouvement->setEquipement($equipement);
                    
                    // Définir la description du mouvement
                    $description = 'Ajout de l\'équipement ';
                    if ($equipement->getTypeEquipement() === 'Autres' && $equipement->getDescription()) {
                        $description .= '\"' . $equipement->getDescription() . '\" ';
                    } else {
                        $description .= 'ID: ' . $equipement->getId() . ' ';
                    }
                    $description .= 'dans le dépôt ' . $depot->getNom();
                    $mouvement->setCommentaire($description);
                    
                    $entityManager->persist($mouvement);
                    $entityManager->flush();
                }
            }
            
            // Si c'est une requête AJAX ou une requête API, retourner une réponse JSON
            if ($request->isXmlHttpRequest() || $request->get('_format') === 'json') {
                $response = [
                    'success' => true,
                    'equipement' => $equipement,
                ];
                if (!$embedded) {
                    $response['redirect'] = $this->generateUrl('app_equipement_show', ['id' => $equipement->getId()]);
                }
                return new JsonResponse($response);
            }
            
            $this->addFlash('success', 'notifications.equipement.created');
            // Si on est dans une modale (embedded), renvoyer une petite page qui notifie le parent
            // Si on est dans une modale (embedded), renvoyer une page qui notifie le parent et ferme la modale
            if ($embedded) {
                return $this->render('equipement/created_redirect.html.twig', [
                    'depot_id' => $depotId
                ]);
            }
            return $this->redirectToRoute('app_equipement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('equipement/new.html.twig', [
            'equipement' => $equipement,
            'form' => $form->createView(),
            'depot_id' => $depotId,
            'embedded' => $embedded
        ]);
    }

    #[Route('/{id}', name: 'app_equipement_show', methods: ['GET'])]
    public function show(Equipement $equipement): Response
    {
        return $this->render('equipement/show.html.twig', [
            'equipement' => $equipement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_equipement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Equipement $equipement, EntityManagerInterface $entityManager, UserActivityLogger $activityLogger): Response
    {
        $form = $this->createForm(EquipementType::class, $equipement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            // Logger l'activité
            $activityLogger->logUpdate($equipement, sprintf('Modification de l\'équipement %s', $equipement->getModel() ?? 'ID ' . $equipement->getId()));
            
            $this->addFlash('success', 'notifications.equipement.updated');
            return $this->redirectToRoute('app_equipement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('equipement/edit.html.twig', [
            'equipement' => $equipement,
            'form' => $form,
            'equipement_type' => $equipement->getTypeEquipement(),
        ]);
    }

    #[Route('/{id}', name: 'app_equipement_delete', methods: ['POST'])]
    public function delete(Request $request, Equipement $equipement, EntityManagerInterface $entityManager, UserActivityLogger $activityLogger, MouvementStockRepository $mouvRepo): Response
    {
        if ($this->isCsrfTokenValid('delete'.$equipement->getId(), $request->getPayload()->getString('_token'))) {
            // Vérifier s'il existe des mouvements liés à cet équipement
            $movesCount = $mouvRepo->count(['equipement' => $equipement]);
            if ($movesCount > 0) {
                $this->addFlash('warning', 'Impossible de supprimer cet équipement car des mouvements de stock y sont liés. Supprimez/annulez les mouvements concernés ou archivez l\'équipement.');
                return $this->redirectToRoute('app_equipement_index', [], Response::HTTP_SEE_OTHER);
            }

            // Logger l'activité avant suppression
            $activityLogger->logDelete($equipement, sprintf('Suppression de l\'équipement %s', $equipement->getModel() ?? 'ID ' . $equipement->getId()));

            $entityManager->remove($equipement);
            $entityManager->flush();
            $this->addFlash('success', 'notifications.equipement.deleted');
        }

        return $this->redirectToRoute('app_equipement_index', [], Response::HTTP_SEE_OTHER);
    }
}
