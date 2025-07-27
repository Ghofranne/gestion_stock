<?php

namespace App\Entity;

use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
class MouvementStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementStocks')]
    private ?equipement $equipement = null;

    #[ORM\Column(length: 255)]
    private ?string $type_mouvement = null;

    #[ORM\Column]
    private ?\DateTime $date = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementStocks')]
    private ?depot $depotSource = null;

    #[ORM\ManyToOne(inversedBy: 'mouvementStocks')]
    private ?depot $depotDestination = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipement(): ?equipement
    {
        return $this->equipement;
    }

    public function setEquipement(?equipement $equipement): static
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

    public function setDepotSource(?depot $depotSource): static
    {
        $this->depotSource = $depotSource;

        return $this;
    }

    public function getDepotDestination(): ?depot
    {
        return $this->depotDestination;
    }

    public function setDepotDestination(?depot $depotDestination): static
    {
        $this->depotDestination = $depotDestination;

        return $this;
    }
}
