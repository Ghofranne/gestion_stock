<?php

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Form\MouvementStockType;
use App\Repository\MouvementStockRepository;
use App\Repository\DepotRepository;
use App\Repository\EquipementRepository;
use App\Service\UserActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mouvement/stock')]
final class MouvementStockController extends AbstractController
{
    #[Route(name: 'app_mouvement_stock_index', methods: ['GET'])]
    public function index(MouvementStockRepository $mouvementStockRepository): Response
    {
        return $this->render('mouvement_stock/index.html.twig', [
            'mouvement_stocks' => $mouvementStockRepository->findAll(),
        ]);
    }

    #[Route('/equipements-par-depot/{id}', name: 'app_mouvement_stock_equipements_par_depot', methods: ['GET'])]
    public function equipementsParDepot(
        int $id,
        Request $request,
        DepotRepository $depotRepository,
        MouvementStockRepository $mouvementRepository
    ): JsonResponse {
        $depot = $depotRepository->find($id);
        if (!$depot) {
            return new JsonResponse(['error' => 'Depot not found'], Response::HTTP_NOT_FOUND);
        }
        $type = $request->query->get('type');
        $equipements = $mouvementRepository->getEquipementsInDepot($depot, $type ?: null);
        $data = array_map(function($e) {
            $idMain = $e->getImei() ?? $e->getSn() ?? $e->getReferenceCarte() ?? ('ID: ' . $e->getId());
            $labelMain = $e->getDescription() ?: $idMain;
            return [
                'id' => $e->getId(),
                'label' => ($e->getTypeEquipement() . ' - ' . $labelMain),
                // Champs pour l'affichage carte
                'type' => $e->getTypeEquipement(),
                'imei' => $e->getImei(),
                'sn' => $e->getSn(),
                'referenceCarte' => $e->getReferenceCarte(),
                'description' => $e->getDescription(),
                'operateur' => $e->getOperateur(),
                'etat' => $e->getEtat(),
                'model' => $e->getModel(),
                'numAppel' => $e->getNumAppel(),
            ];
        }, $equipements);
        return new JsonResponse(['items' => $data]);
    }

    #[Route('/equipements-non-assignes', name: 'app_mouvement_stock_equipements_non_assignes', methods: ['GET'])]
    public function equipementsNonAssignes(
        Request $request,
        EquipementRepository $equipementRepository,
        MouvementStockRepository $mouvementRepository
    ): JsonResponse {
        $type = $request->query->get('type');
        // Calculer le stock net par équipement (entrées - sorties) pour identifier ceux présents dans un dépôt
        // Tout équipement avec un net > 0 est considéré comme actuellement affecté dans un dépôt
        $netByEq = $mouvementRepository->getNetStockByEquipement($type ?: null); // [equipementId => net]

        // Récupérer tous les équipements (optionnellement par type) marqués 'Disponible'
        $qb = $equipementRepository->createQueryBuilder('e');
        if ($type) {
            $qb->andWhere('e.typeEquipement = :t')->setParameter('t', $type);
        }
        $qb->andWhere('e.etat = :etat')->setParameter('etat', 'Disponible');
        $allEquipements = $qb->getQuery()->getResult();

        // Exclure ceux actuellement affectés (net > 0)
        $equipements = array_filter($allEquipements, function($e) use ($netByEq) {
            $id = $e->getId();
            $net = (int)($netByEq[$id] ?? 0);
            return $net <= 0; // on ne garde que ceux qui ne sont présents dans aucun dépôt
        });
        $data = array_map(function($e) use ($mouvementRepository) {
            $idMain = $e->getImei() ?? $e->getSn() ?? $e->getReferenceCarte() ?? ('ID: ' . $e->getId());
            $labelMain = $e->getDescription() ?: $idMain;
            // Trouver le dernier mouvement pour déterminer le dernier dépôt source
            $qb = $mouvementRepository->createQueryBuilder('m')
                ->andWhere('m.equipement = :eq')
                ->setParameter('eq', $e)
                ->orderBy('m.date', 'DESC')
                ->addOrderBy('m.id', 'DESC')
                ->setMaxResults(1);
            $last = $qb->getQuery()->getOneOrNullResult();
            $lastSourceId = $last && $last->getDepotSource() ? $last->getDepotSource()->getId() : null;
            return [
                'id' => $e->getId(),
                'label' => ($e->getTypeEquipement() . ' - ' . $labelMain),
                'type' => $e->getTypeEquipement(),
                'imei' => $e->getImei(),
                'sn' => $e->getSn(),
                'referenceCarte' => $e->getReferenceCarte(),
                'description' => $e->getDescription(),
                'operateur' => $e->getOperateur(),
                'etat' => $e->getEtat(),
                'model' => $e->getModel(),
                'numAppel' => $e->getNumAppel(),
                'lastDepotSourceId' => $lastSourceId,
            ];
        }, $equipements);
        // Réindexer pour garantir un tableau JSON (et non un objet) après array_filter
        $data = array_values($data);
        return new JsonResponse(['items' => $data]);
    }

    #[Route('/equipements-sortis', name: 'app_mouvement_stock_equipements_sortis', methods: ['GET'])]
    public function equipementsSortis(
        Request $request,
        EquipementRepository $equipementRepository,
        MouvementStockRepository $mouvementRepository
    ): JsonResponse {
        $type = $request->query->get('type');
        // Calcul net pour identifier ceux non présents dans un dépôt
        $netByEq = $mouvementRepository->getNetStockByEquipement($type ?: null);

        // Récupérer tous les équipements marqués 'Non disponible' (optionnellement par type)
        $qb = $equipementRepository->createQueryBuilder('e');
        if ($type) {
            $qb->andWhere('e.typeEquipement = :t')->setParameter('t', $type);
        }
        $qb->andWhere('e.etat = :etat')->setParameter('etat', 'Non disponible');
        $allEquipements = $qb->getQuery()->getResult();

        // Garder uniquement ceux hors dépôt (net <= 0)
        $equipements = array_filter($allEquipements, function($e) use ($netByEq) {
            $id = $e->getId();
            $net = (int)($netByEq[$id] ?? 0);
            return $net <= 0;
        });

        $data = array_map(function($e) use ($mouvementRepository) {
            $idMain = $e->getImei() ?? $e->getSn() ?? $e->getReferenceCarte() ?? ('ID: ' . $e->getId());
            $labelMain = $e->getDescription() ?: $idMain;
            // Dernier mouvement pour déterminer le dernier dépôt source
            $qb = $mouvementRepository->createQueryBuilder('m')
                ->andWhere('m.equipement = :eq')
                ->setParameter('eq', $e)
                ->orderBy('m.date', 'DESC')
                ->addOrderBy('m.id', 'DESC')
                ->setMaxResults(1);
            $last = $qb->getQuery()->getOneOrNullResult();
            $lastSourceId = $last && $last->getDepotSource() ? $last->getDepotSource()->getId() : null;
            return [
                'id' => $e->getId(),
                'label' => ($e->getTypeEquipement() . ' - ' . $labelMain),
                'type' => $e->getTypeEquipement(),
                'imei' => $e->getImei(),
                'sn' => $e->getSn(),
                'referenceCarte' => $e->getReferenceCarte(),
                'description' => $e->getDescription(),
                'operateur' => $e->getOperateur(),
                'etat' => $e->getEtat(),
                'model' => $e->getModel(),
                'numAppel' => $e->getNumAppel(),
                'lastDepotSourceId' => $lastSourceId,
            ];
        }, $equipements);

        $data = array_values($data);
        return new JsonResponse(['items' => $data]);
    }

    #[Route('/new', name: 'app_mouvement_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserActivityLogger $activityLogger, MouvementStockRepository $mouvRepo, DepotRepository $depotRepo): Response
    {
        $mouvementStock = new MouvementStock();
        // Définir automatiquement la date actuelle
        $mouvementStock->setDate(new \DateTime());
        // Pré-remplir via params de requête (utile pour modale depuis une page dépôt)
        $prefType = (string)$request->query->get('type', '');
        if ($prefType) {
            $mouvementStock->setTypeMouvement($prefType);
        }
        $prefDestId = $request->query->get('depotDestination');
        if ($prefDestId) {
            $dest = $depotRepo->find((int)$prefDestId);
            if ($dest) {
                $mouvementStock->setDepotDestination($dest);
            }
        }
        $form = $this->createForm(MouvementStockType::class, $mouvementStock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $equipementsSelectionnes = $form->get('equipements')->getData();
            // Si aucun équipement sélectionné, on crée quand même un mouvement si l'entité contient déjà un équipement (cas futur) sinon erreur douce
            if (empty($equipementsSelectionnes)) {
                $this->addFlash('warning', 'Veuillez sélectionner au moins un équipement.');
                return $this->render('mouvement_stock/new.html.twig', [
                    'mouvement_stock' => $mouvementStock,
                    'form' => $form,
                ]);
            }

            // Préparer des validations côté serveur pour éviter l'affectation multiple
            $type = $mouvementStock->getTypeMouvement();
            $netByEq = $mouvRepo->getNetStockByEquipement(); // [equipementId => net]

            // Si c'est une entrée simple (affectation) ou un retour, vérifier qu'on n'affecte pas des équipements déjà présents
            if (in_array($type, [MouvementStock::TYPE_ENTREE, MouvementStock::TYPE_RETOUR], true)) {
                $alreadyAssigned = [];
                foreach ($equipementsSelectionnes as $eq) {
                    $net = (int)($netByEq[$eq->getId()] ?? 0);
                    if ($net > 0) {
                        $alreadyAssigned[] = $eq;
                    }
                }
                if (!empty($alreadyAssigned)) {
                    // Filtrer pour ne garder que les valides
                    $blockedIds = array_map(static fn($e) => $e->getId(), $alreadyAssigned);
                    $equipementsSelectionnes = array_values(array_filter($equipementsSelectionnes, static fn($e) => !in_array($e->getId(), $blockedIds, true)));

                    if (empty($equipementsSelectionnes)) {
                        // Tous invalides: on reste sur le formulaire
                        $this->addFlash('warning', sprintf('%d équipement(s) déjà affecté(s) — aucun mouvement créé.', count($alreadyAssigned)));
                        return $this->render('mouvement_stock/new.html.twig', [
                            'mouvement_stock' => $mouvementStock,
                            'form' => $form,
                        ]);
                    }

                    // Partiellement valides: on continue mais on informe
                    $this->addFlash('warning', sprintf('%d équipement(s) ignoré(s) car déjà affecté(s).', count($alreadyAssigned)));
                }
            }

            $persistedMouvements = [];
            foreach ($equipementsSelectionnes as $equipement) {
                $m = new MouvementStock();
                $m->setTypeMouvement($mouvementStock->getTypeMouvement());
                $m->setDate($mouvementStock->getDate());
                $m->setDepotSource($mouvementStock->getDepotSource());
                $m->setDepotDestination($mouvementStock->getDepotDestination());
                $m->setCommentaire($mouvementStock->getCommentaire());
                $m->setSensAjustement($mouvementStock->getSensAjustement());
                $m->setRaisonAjustement($mouvementStock->getRaisonAjustement());
                $m->setEquipement($equipement);
                $m->setCreatedBy($this->getUser());
                $entityManager->persist($m);
                $persistedMouvements[] = $m;
                // On flush plus bas pour avoir les IDs, mais on peut aussi les collecter après flush

                // Mettre à jour l'état de l'équipement automatiquement
                $hasSource = (null !== $m->getDepotSource());
                $hasDest = (null !== $m->getDepotDestination());
                
                // Logique spécifique pour chaque type de mouvement
                switch ($m->getTypeMouvement()) {
                    case MouvementStock::TYPE_RETOUR:
                        // Priorité: si mouvement de type "retour", on met l'état explicitement à "Retour"
                        $equipement->setEtat('Retour');
                        $entityManager->persist($equipement);
                        break;
                        
                    case MouvementStock::TYPE_AJUSTEMENT:
                        $sensAjustement = $m->getSensAjustement();
                        
                        // Logique pour un ajustement d'entrée
                        if ($sensAjustement === 'entree') {
                            if ($hasDest) {
                                // Ajustement + avec dépôt de destination → Affecté
                                $equipement->setEtat('Affecté');
                            } else {
                                // Ajustement + sans dépôt → Disponible
                                $equipement->setEtat('Disponible');
                            }
                        } 
                        // Logique pour un ajustement de sortie
                        elseif ($sensAjustement === 'sortie') {
                            // Si la raison est 'maintenance', on met en maintenance
                            if (stripos($m->getRaisonAjustement() ?? '', 'maintenance') !== false || 
                                stripos($m->getRaisonAjustement() ?? '', 'sav') !== false) {
                                $equipement->setEtat('En maintenance');
                            } 
                            // Sinon, on marque comme non disponible
                            else {
                                $equipement->setEtat('Non disponible');
                            }
                        }
                        $entityManager->persist($equipement);
                        break;
                        
                    default:
                        // Logique pour les autres types de mouvements
                        if ($hasDest && !$hasSource) {
                            // Affectation dans un dépôt
                            $equipement->setEtat('Affecté');
                            $entityManager->persist($equipement);
                        } elseif ($hasSource && !$hasDest) {
                            // Sortie simple d'un dépôt => devient non disponible
                            $equipement->setEtat('Non disponible');
                            $entityManager->persist($equipement);
                        } elseif ($hasSource && $hasDest) {
                            // Transfert: reste affecté
                            $equipement->setEtat('Affecté');
                            $entityManager->persist($equipement);
                        }
                }
            }

            $entityManager->flush();
            // Construire la liste des IDs créés à partir des entités persistées
            $createdIds = array_map(static fn(MouvementStock $mm) => $mm->getId(), $persistedMouvements);

            // S'il n'y a aucun ID (rien créé), ne pas envoyer vers le reçu vide
            if (empty($createdIds)) {
                $this->addFlash('warning', 'Aucun mouvement créé. Veuillez sélectionner des équipements valides.');
                return $this->redirectToRoute('app_mouvement_stock_new');
            }

            // Logger l'activité (un log global)
            $activityLogger->logCreate($mouvementStock, sprintf('Mouvements multiples: %s (%d équipements)', $mouvementStock->getTypeMouvement(), count($equipementsSelectionnes)));

            $this->addFlash('success', 'notifications.mouvement.created');
            // Passer par une page intermédiaire: télécharge le PDF puis redirige vers la liste
            return $this->redirectToRoute('app_mouvement_stock_receipt_then_redirect', [
                'ids' => implode(',', $createdIds)
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mouvement_stock/new.html.twig', [
            'mouvement_stock' => $mouvementStock,
            'form' => $form,
        ]);
    }

    #[Route('/receipt', name: 'app_mouvement_stock_receipt', methods: ['GET'])]
    public function receipt(Request $request, MouvementStockRepository $mouvementStockRepository): Response
    {
        $idsParam = $request->query->get('ids', '');
        $ids = array_values(array_filter(array_map('intval', explode(',', (string)$idsParam)), fn($v) => $v > 0));
        if (empty($ids)) {
            return $this->redirectToRoute('app_mouvement_stock_index');
        }

        $mouvements = $mouvementStockRepository->createQueryBuilder('m')
            ->andWhere('m.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.id', 'ASC')
            ->getQuery()->getResult();

        // Rendu HTML via Twig
        $html = $this->renderView('mouvement_stock/receipt.html.twig', [
            'mouvements' => $mouvements,
        ]);

        // Génération PDF avec Dompdf
        // Assurez-vous d'installer dompdf/dompdf
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        // Use landscape to increase horizontal space so table columns have more width
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'mouvement_stock_' . (new \DateTime())->format('Ymd_His') . '.pdf';
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    // Page intermédiaire: déclenche le téléchargement du reçu puis redirige vers l'index
    #[Route('/receipt/download-then-redirect', name: 'app_mouvement_stock_receipt_then_redirect', methods: ['GET'])]
    public function receiptThenRedirect(Request $request): Response
    {
        $ids = (string)$request->query->get('ids', '');
        if ($ids === '') {
            return $this->redirectToRoute('app_mouvement_stock_index');
        }
        $receiptUrl = $this->generateUrl('app_mouvement_stock_receipt', ['ids' => $ids]);
        $indexUrl = $this->generateUrl('app_mouvement_stock_index');
        return $this->render('mouvement_stock/receipt_redirect.html.twig', [
            'receipt_url' => $receiptUrl,
            'redirect_url' => $indexUrl,
        ]);
    }

    #[Route('/{id}', name: 'app_mouvement_stock_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(MouvementStock $mouvementStock): Response
    {
        return $this->render('mouvement_stock/show.html.twig', [
            'mouvement_stock' => $mouvementStock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_mouvement_stock_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Request $request, MouvementStock $mouvementStock, EntityManagerInterface $entityManager, UserActivityLogger $activityLogger): Response
    {
        $form = $this->createForm(MouvementStockType::class, $mouvementStock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            // Logger l'activité
            $activityLogger->logUpdate($mouvementStock, sprintf('Modification du mouvement %s', $mouvementStock->getTypeMouvement()));
            
            $this->addFlash('success', 'notifications.mouvement.updated');
            return $this->redirectToRoute('app_mouvement_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mouvement_stock/edit.html.twig', [
            'mouvement_stock' => $mouvementStock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_mouvement_stock_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, MouvementStock $mouvementStock, EntityManagerInterface $entityManager, UserActivityLogger $activityLogger): Response
    {
        if ($this->isCsrfTokenValid('delete'.$mouvementStock->getId(), $request->getPayload()->getString('_token'))) {
            // Logger l'activité avant suppression (sans quantité)
            $activityLogger->logDelete($mouvementStock, sprintf('Suppression du mouvement %s', $mouvementStock->getTypeMouvement()));
            
            $entityManager->remove($mouvementStock);
            $entityManager->flush();
            $this->addFlash('success', 'notifications.mouvement.deleted');
        }

        return $this->redirectToRoute('app_mouvement_stock_index', [], Response::HTTP_SEE_OTHER);
    }

    // Endpoint JSON léger pour transferts via drag & drop
    #[Route('/quick-transfer', name: 'app_mouvement_quick_transfer', methods: ['POST'])]
    public function quickTransfer(
        Request $request,
        EntityManagerInterface $em,
        DepotRepository $depotRepo,
        EquipementRepository $equipRepo
    ): JsonResponse {
        $data = json_decode((string)$request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'code' => 'bad_request', 'message' => 'Payload JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        // Accept either single equipementId or multiple equipementIds
        $idsFromArray    = isset($data['equipementIds']) && is_array($data['equipementIds']) ? array_values(array_map('intval', $data['equipementIds'])) : [];
        $singleId        = (int)($data['equipementId'] ?? 0);
        $equipementIds   = !empty($idsFromArray) ? array_filter($idsFromArray, static fn($v) => $v > 0) : ($singleId > 0 ? [$singleId] : []);
        $sourceDepotId   = (int)($data['sourceDepotId'] ?? 0);
        $destDepotId     = (int)($data['destDepotId'] ?? 0);

        if (empty($equipementIds) || $sourceDepotId <= 0 || $destDepotId <= 0) {
            return new JsonResponse(['success' => false, 'code' => 'missing_params', 'message' => 'Paramètres requis manquants'], Response::HTTP_BAD_REQUEST);
        }
        if ($sourceDepotId === $destDepotId) {
            return new JsonResponse(['success' => false, 'code' => 'same_depot', 'message' => 'Le dépôt source et destination doivent être différents'], Response::HTTP_BAD_REQUEST);
        }
        
        $source = $depotRepo->find($sourceDepotId);
        $dest   = $depotRepo->find($destDepotId);
        if (!$source || !$dest) {
            return new JsonResponse(['success' => false, 'code' => 'depot_not_found', 'message' => 'Dépôt introuvable'], Response::HTTP_NOT_FOUND);
        }
        // Validation simple: même société si relation disponible (comparer par ID pour éviter les proxys Doctrine)
        if (method_exists($source, 'getSociete') && method_exists($dest, 'getSociete')) {
            $srcSoc = $source->getSociete();
            $dstSoc = $dest->getSociete();
            if ($srcSoc && $dstSoc) {
                $srcId = method_exists($srcSoc, 'getId') ? (int)$srcSoc->getId() : null;
                $dstId = method_exists($dstSoc, 'getId') ? (int)$dstSoc->getId() : null;
                if ($srcId !== null && $dstId !== null && $srcId !== $dstId) {
                    return new JsonResponse(['success' => false, 'code' => 'different_company', 'message' => 'Transfert entre sociétés interdit'], Response::HTTP_BAD_REQUEST);
                }
            }
        }

        // Créer un mouvement par équipement
        $created = [];
        foreach ($equipementIds as $eid) {
            $equipement = $equipRepo->find((int)$eid);
            if (!$equipement) {
                // ignorer silencieusement les IDs invalides, mais on pourrait aussi échouer
                continue;
            }
            $m = new MouvementStock();
            $m->setTypeMouvement(MouvementStock::TYPE_TRANSFERT);
            $m->setDate(new \DateTime());
            $m->setDepotSource($source);
            $m->setDepotDestination($dest);
            $m->setEquipement($equipement);
            $m->setCreatedBy($this->getUser());
            $m->setCommentaire('Transfert via drag & drop');
            $em->persist($m);
            // État équipement
            if (method_exists($equipement, 'setEtat')) {
                $equipement->setEtat('Affecté');
                $em->persist($equipement);
            }
            $created[] = $m;
        }

        if (empty($created)) {
            return new JsonResponse(['success' => false, 'code' => 'no_valid_equipment', 'message' => 'Aucun équipement valide à transférer'], Response::HTTP_BAD_REQUEST);
        }

        $em->flush();

        $idsParam = implode(',', array_map(static fn(MouvementStock $mm) => (string)$mm->getId(), $created));
        $receiptUrl = $this->generateUrl('app_mouvement_stock_receipt', ['ids' => $idsParam]);

        return new JsonResponse([
            'success' => true,
            'count' => count($created),
            'mouvementIds' => array_map(static fn(MouvementStock $mm) => $mm->getId(), $created),
            'equipementIds' => array_map(static fn(MouvementStock $mm) => $mm->getEquipement() ? $mm->getEquipement()->getId() : null, $created),
            'sourceDepotId' => $source->getId(),
            'destDepotId' => $dest->getId(),
            'receiptUrl' => $receiptUrl,
        ]);
    }
}
