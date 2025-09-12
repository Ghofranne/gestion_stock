<?php

namespace App\Controller;

use App\Repository\UserActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ExportController extends AbstractController
{
    /**
     * Exporte l'historique des activités au format CSV
     */
    #[Route('/export/activities.{_format}', name: 'app_export_activities', requirements: ['_format' => 'csv'], defaults: ['_format' => 'csv'])]
    public function exportActivities(UserActivityRepository $activityRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Récupérer toutes les activités, triées par date décroissante
        $activities = $activityRepository->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Préparer les données CSV
        $csvData = [];
        $csvData[] = [
            'Date',
            'Utilisateur',
            'Action',
            'Type d\'entité',
            'ID de l\'entité',
            'Détails'
        ];
        
        foreach ($activities as $activity) {
            $csvData[] = [
                $activity->getCreatedAt()->format('Y-m-d H:i:s'),
                $activity->getUser() ? $activity->getUser()->getFullName() : 'N/A',
                $activity->getAction(),
                $activity->getEntityType(),
                $activity->getEntityId() ?? 'N/A',
                str_replace("\n", ' ', strip_tags($activity->getDetails() ?? ''))
            ];
        }
        
        // Générer le contenu CSV
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        // Créer la réponse
        $response = new Response($csvContent);
        
        // Définir les en-têtes pour le téléchargement
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="historique_activites_' . date('Y-m-d') . '.csv"');
        
        return $response;
    }
}
