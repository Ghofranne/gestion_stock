<?php

namespace App\Entity;

use App\Repository\UserActivityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityRepository::class)]
class UserActivity
{
    // Types d'actions disponibles
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_PREFERENCES = 'preferences';
    public const ACTION_PASSWORD = 'password';
    public const ACTION_STOCK_MOVEMENT = 'stock_movement';
    
    public static function getActionTypes(): array
    {
        return [
            'Création' => self::ACTION_CREATE,
            'Modification' => self::ACTION_UPDATE,
            'Suppression' => self::ACTION_DELETE,
            'Connexion' => self::ACTION_LOGIN,
            'Déconnexion' => self::ACTION_LOGOUT,
            'Préférences' => self::ACTION_PREFERENCES,
            'Mot de passe' => self::ACTION_PASSWORD,
            'Mouvement de stock' => self::ACTION_STOCK_MOVEMENT,
        ];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 100)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function setEntityName(?string $entityName): static
    {
        $this->entityName = $entityName;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    /**
     * Obtient une description lisible de l'action
     */
    public function getActionDescription(): string
    {
        $actionLabels = [
            self::ACTION_CREATE => 'a créé',
            self::ACTION_UPDATE => 'a modifié',
            self::ACTION_DELETE => 'a supprimé',
            self::ACTION_LOGIN => 's\'est connecté',
            self::ACTION_LOGOUT => 's\'est déconnecté',
            self::ACTION_PREFERENCES => 'a modifié ses préférences',
            self::ACTION_PASSWORD => 'a changé son mot de passe',
            self::ACTION_STOCK_MOVEMENT => 'a effectué un mouvement de stock',
        ];

        $actionLabel = $actionLabels[$this->action] ?? $this->action;
        
        if ($this->entityName) {
            return sprintf('%s %s "%s"', $actionLabel, $this->getEntityTypeLabel(), $this->entityName);
        } elseif ($this->entityType) {
            return sprintf('%s %s', $actionLabel, $this->getEntityTypeLabel());
        }
        
        return $actionLabel;
    }

    /**
     * Obtient le libellé de l'entité en français
     */
    public function getEntityTypeLabel(): string
    {
        $entityLabels = [
            'Equipement' => 'l\'équipement',
            'Societe' => 'la société',
            'Depot' => 'le dépôt',
            'MouvementStock' => 'le mouvement de stock',
            'User' => 'l\'utilisateur',
        ];

        return $entityLabels[$this->entityType] ?? strtolower($this->entityType);
    }

    /**
     * Obtient l'icône correspondant au type d'action
     */
    public function getActionIcon(): string
    {
        $icons = [
            self::ACTION_CREATE => '➕',
            self::ACTION_UPDATE => '✏️',
            self::ACTION_DELETE => '🗑️',
            self::ACTION_LOGIN => '🔑',
            self::ACTION_LOGOUT => '🚪',
            self::ACTION_PREFERENCES => '⚙️',
            self::ACTION_PASSWORD => '🔒',
            self::ACTION_STOCK_MOVEMENT => '📦',
        ];

        return $icons[$this->action] ?? '📝';
    }
}
