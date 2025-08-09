<?php

namespace App\Entity;

use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
class MouvementStock
{
    // Types de mouvements disponibles
    public const TYPE_ENTREE = 'entree';
    public const TYPE_SORTIE = 'sortie';
    public const TYPE_TRANSFERT = 'transfert';
    public const TYPE_AJUSTEMENT = 'ajustement';
    public const TYPE_RETOUR = 'retour';
    
    public static function getTypesChoices(): array
    {
        return [
            'Entrée en stock' => self::TYPE_ENTREE,
            'Sortie de stock' => self::TYPE_SORTIE,
            'Transfert entre dépôts' => self::TYPE_TRANSFERT,
            'Ajustement d\'inventaire' => self::TYPE_AJUSTEMENT,
            'Retour client' => self::TYPE_RETOUR,
        ];
    }
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementStocks')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 255)]
    private ?string $type_mouvement = null;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementStocks')]
    private ?Depot $depotSource = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementStocks')]
    private ?Depot $depotDestination = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $quantite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipement(): ?equipement
    {
        return $this->equipement;
    }

    public function setEquipement(?Equipement $equipement): static
    {
        $this->equipement = $equipement;

        return $this;
    }

    public function getTypeMouvement(): ?string
    {
        return $this->type_mouvement;
    }

    public function setTypeMouvement(string $type_mouvement): static
    {
        $this->type_mouvement = $type_mouvement;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getDepotSource(): ?depot
    {
        return $this->depotSource;
    }

    public function setDepotSource(?Depot $depotSource): static
    {
        $this->depotSource = $depotSource;

        return $this;
    }

    public function getDepotDestination(): ?depot
    {
        return $this->depotDestination;
    }

    public function setDepotDestination(?Depot $depotDestination): static
    {
        $this->depotDestination = $depotDestination;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    /**
     * Vérifie si le mouvement nécessite un dépôt source
     */
    public function requiresDepotSource(): bool
    {
        return in_array($this->type_mouvement, [self::TYPE_SORTIE, self::TYPE_TRANSFERT]);
    }

    /**
     * Vérifie si le mouvement nécessite un dépôt destination
     */
    public function requiresDepotDestination(): bool
    {
        return in_array($this->type_mouvement, [self::TYPE_ENTREE, self::TYPE_TRANSFERT, self::TYPE_RETOUR]);
    }
}
