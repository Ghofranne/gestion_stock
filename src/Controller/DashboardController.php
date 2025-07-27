<?php
// src/Controller/DashboardController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard.html.twig');
    }

    #[Route('/dashboard/refresh', name: 'dashboard_refresh', methods: ['GET'])]
    public function refresh(): Response
    {
        // Ici, vous pouvez remplacer les valeurs par des vraies stats dynamiques
        $data = [
            'totalEquipments' => 1247,
            'totalDepots' => 15,
            'todayMovements' => 89,
            'stockAlerts' => 5,
        ];
        return $this->json($data);
    }
}
