<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserActivity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class UserActivityLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private Security $security
    ) {
    }

    /**
     * Log une activité utilisateur
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $entityName = null,
        ?string $details = null,
        ?User $user = null
    ): void {
        // Utiliser l'utilisateur fourni ou l'utilisateur connecté
        $user = $user ?? $this->security->getUser();
        
        if (!$user instanceof User) {
            return; // Pas d'utilisateur connecté, on ne log pas
        }

        $request = $this->requestStack->getCurrentRequest();
        
        $activity = new UserActivity();
        $activity->setUser($user)
            ->setAction($action)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setEntityName($entityName)
            ->setDetails($details);

        if ($request) {
            $activity->setIpAddress($request->getClientIp())
                ->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();
    }

    /**
     * Log la création d'une entité
     */
    public function logCreate(object $entity, ?string $details = null): void
    {
        $this->log(
            UserActivity::ACTION_CREATE,
            $this->getEntityType($entity),
            $this->getEntityId($entity),
            $this->getEntityName($entity),
            $details
        );
    }

    /**
     * Log la modification d'une entité
     */
    public function logUpdate(object $entity, ?string $details = null): void
    {
        $this->log(
            UserActivity::ACTION_UPDATE,
            $this->getEntityType($entity),
            $this->getEntityId($entity),
            $this->getEntityName($entity),
            $details
        );
    }

    /**
     * Log la suppression d'une entité
     */
    public function logDelete(object $entity, ?string $details = null): void
    {
        $this->log(
            UserActivity::ACTION_DELETE,
            $this->getEntityType($entity),
            $this->getEntityId($entity),
            $this->getEntityName($entity),
            $details
        );
    }

    /**
     * Log une connexion utilisateur
     */
    public function logLogin(?User $user = null): void
    {
        $this->log(
            UserActivity::ACTION_LOGIN,
            'User',
            null,
            null,
            'Connexion réussie',
            $user
        );
    }

    /**
     * Log une déconnexion utilisateur
     */
    public function logLogout(?User $user = null): void
    {
        $this->log(
            UserActivity::ACTION_LOGOUT,
            'User',
            null,
            null,
            'Déconnexion',
            $user
        );
    }

    /**
     * Log un changement de préférences
     */
    public function logPreferences(?string $details = null): void
    {
        $this->log(
            UserActivity::ACTION_PREFERENCES,
            'User',
            null,
            null,
            $details ?? 'Modification des préférences'
        );
    }

    /**
     * Log un changement de mot de passe
     */
    public function logPasswordChange(): void
    {
        $this->log(
            UserActivity::ACTION_PASSWORD,
            'User',
            null,
            null,
            'Changement de mot de passe'
        );
    }

    /**
     * Log un mouvement de stock
     */
    public function logStockMovement(object $movement, ?string $details = null): void
    {
        $this->log(
            UserActivity::ACTION_STOCK_MOVEMENT,
            'MouvementStock',
            $this->getEntityId($movement),
            $this->getEntityName($movement),
            $details
        );
    }

    /**
     * Obtient le type d'entité (nom de classe sans namespace)
     */
    private function getEntityType(object $entity): string
    {
        $reflection = new \ReflectionClass($entity);
        return $reflection->getShortName();
    }

    /**
     * Obtient l'ID de l'entité
     */
    private function getEntityId(object $entity): ?int
    {
        if (method_exists($entity, 'getId')) {
            return $entity->getId();
        }
        return null;
    }

    /**
     * Obtient le nom de l'entité pour l'affichage
     */
    private function getEntityName(object $entity): ?string
    {
        // Essayer différentes méthodes pour obtenir un nom lisible
        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Cas particulier: Equipement -> construire un libellé riche
        if ($entity instanceof \App\Entity\Equipement) {
            $type = trim((string) $entity->getTypeEquipement());
            $parts = [];

            // Identifiant principal affiché après le type
            $primary = $entity->getSn() ?: $entity->getModel();

            if ($primary) {
                $parts[] = $primary;
            }

            // Détails spécifiques selon le type
            $typeLower = mb_strtolower($type);
            if (str_contains($typeLower, 'sim') && $entity->getImei()) {
                $parts[] = 'IMEI ' . $entity->getImei();
            }
            if (str_contains($typeLower, 'carte') && $entity->getReferenceCarte()) {
                $parts[] = 'Réf. ' . $entity->getReferenceCarte();
            }

            $suffix = $parts ? ' ' . implode(' ', $parts) : ($entity->getImei() ?: $entity->getReferenceCarte() ?: ('#' . $entity->getId()));
            return trim($type . ' ' . $suffix);
        }

        if (method_exists($entity, 'getNom')) {
            return $entity->getNom();
        }
        
        if (method_exists($entity, 'getName')) {
            return $entity->getName();
        }
        
        if (method_exists($entity, 'getLibelle')) {
            return $entity->getLibelle();
        }
        
        if (method_exists($entity, 'getTitle')) {
            return $entity->getTitle();
        }

        // Pour MouvementStock, créer une description sans quantité (1 unité implicite)
        if ((method_exists($entity, 'getTypeMouvement') || method_exists($entity, 'getType'))) {
            $type = method_exists($entity, 'getTypeMouvement') ? $entity->getTypeMouvement() : $entity->getType();
            $equipementLabel = null;
            if (method_exists($entity, 'getEquipement') && $entity->getEquipement()) {
                $eq = $entity->getEquipement();
                $equipementLabel = $eq->getModel() ?: ($eq->getSn() ?: ('#' . $eq->getId()));
            }
            return trim($type . ($equipementLabel ? ' - ' . $equipementLabel : ''));
        }
        
        return null;
    }
}
