<?php

namespace App\Controller;

use App\Entity\MouvementStock;
use App\Form\MouvementStockType;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/new', name: 'app_mouvement_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $mouvementStock = new MouvementStock();
        // Définir automatiquement la date actuelle
        $mouvementStock->setDate(new \DateTime());
        $form = $this->createForm(MouvementStockType::class, $mouvementStock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($mouvementStock);
            $entityManager->flush();
            $this->addFlash('success', 'notifications.mouvement.created');
            return $this->redirectToRoute('app_mouvement_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mouvement_stock/new.html.twig', [
            'mouvement_stock' => $mouvementStock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_mouvement_stock_show', methods: ['GET'])]
    public function show(MouvementStock $mouvementStock): Response
    {
        return $this->render('mouvement_stock/show.html.twig', [
            'mouvement_stock' => $mouvementStock,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_mouvement_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, MouvementStock $mouvementStock, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MouvementStockType::class, $mouvementStock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'notifications.mouvement.updated');
            return $this->redirectToRoute('app_mouvement_stock_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mouvement_stock/edit.html.twig', [
            'mouvement_stock' => $mouvementStock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_mouvement_stock_delete', methods: ['POST'])]
    public function delete(Request $request, MouvementStock $mouvementStock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$mouvementStock->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($mouvementStock);
            $entityManager->flush();
            $this->addFlash('success', 'notifications.mouvement.deleted');
        }

        return $this->redirectToRoute('app_mouvement_stock_index', [], Response::HTTP_SEE_OTHER);
    }
}
