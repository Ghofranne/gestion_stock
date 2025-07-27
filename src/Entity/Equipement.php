<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $typeEquipement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imei = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numAppel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $operateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referenceCarte = null;

    #[ORM\Column(length: 255)]
    private ?string $fournisseur = null;

    #[ORM\Column(length: 255)]
    private ?string $etat = null;

    /**
     * @var Collection<int, MouvementStock>
     */
    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'equipement')]
    private Collection $mouvementStocks;

    public function __construct()
    {
        $this->mouvementStocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeEquipement(): ?string
    {
        return $this->typeEquipement;
    }

    public function setTypeEquipement(string $typeEquipement): static
    {
        $this->typeEquipement = $typeEquipement;

        return $this;
    }

    public function getSn(): ?string
    {
        return $this->sn;
    }

    public function setSn(?string $sn): static
    {
        $this->sn = $sn;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getImei(): ?string
    {
        return $this->imei;
    }

    public function setImei(?string $imei): static
    {
        $this->imei = $imei;

        return $this;
    }

    public function getNumAppel(): ?string
    {
        return $this->numAppel;
    }

    public function setNumAppel(?string $numAppel): static
    {
        $this->numAppel = $numAppel;

        return $this;
    }

    public function getOperateur(): ?string
    {
        return $this->operateur;
    }

    public function setOperateur(?string $operateur): static
    {
        $this->operateur = $operateur;

        return $this;
    }

    public function getReferenceCarte(): ?string
    {
        return $this->referenceCarte;
    }

    public function setReferenceCarte(?string $referenceCarte): static
    {
        $this->referenceCarte = $referenceCarte;

        return $this;
    }

    public function getFournisseur(): ?string
    {
        return $this->fournisseur;
    }

    public function setFournisseur(string $fournisseur): static
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    /**
     * @return Collection<int, MouvementStock>
     */
    public function getMouvementStocks(): Collection
    {
        return $this->mouvementStocks;
    }

    public function addMouvementStock(MouvementStock $mouvementStock): static
    {
        if (!$this->mouvementStocks->contains($mouvementStock)) {
            $this->mouvementStocks->add($mouvementStock);
            $mouvementStock->setEquipement($this);
        }

        return $this;
    }

    public function removeMouvementStock(MouvementStock $mouvementStock): static
    {
        if ($this->mouvementStocks->removeElement($mouvementStock)) {
            // set the owning side to null (unless already changed)
            if ($mouvementStock->getEquipement() === $this) {
                $mouvementStock->setEquipement(null);
            }
        }

        return $this;
    }
}
