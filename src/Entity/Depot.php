<?php

namespace App\Entity;

use App\Repository\DepotRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepotRepository::class)]
class Depot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $adresse = null;

    #[ORM\ManyToOne(inversedBy: 'depots')]
    private ?Societe $societe = null;

    /**
     * @var Collection<int, MouvementStock>
     */
    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'depotSource')]
    private Collection $mouvementStocksSource;

    /**
     * @var Collection<int, MouvementStock>
     */
    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'depotDestination')]
    private Collection $mouvementStocksDestination;

    public function __construct()
    {
        $this->mouvementStocksSource = new ArrayCollection();
        $this->mouvementStocksDestination = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getSociete(): ?Societe
    {
        return $this->societe;
    }

    public function setSociete(?Societe $societe): static
    {
        $this->societe = $societe;

        return $this;
    }

    /**
     * @return Collection<int, MouvementStock>
     */
    public function getMouvementStocksSource(): Collection
    {
        return $this->mouvementStocksSource;
    }

    public function addMouvementStocksSource(MouvementStock $mouvementStock): static
    {
        if (!$this->mouvementStocksSource->contains($mouvementStock)) {
            $this->mouvementStocksSource->add($mouvementStock);
            $mouvementStock->setDepotSource($this);
        }

        return $this;
    }

    public function removeMouvementStocksSource(MouvementStock $mouvementStock): static
    {
        if ($this->mouvementStocksSource->removeElement($mouvementStock)) {
            // set the owning side to null (unless already changed)
            if ($mouvementStock->getDepotSource() === $this) {
                $mouvementStock->setDepotSource(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MouvementStock>
     */
    public function getMouvementStocksDestination(): Collection
    {
        return $this->mouvementStocksDestination;
    }

    public function addMouvementStocksDestination(MouvementStock $mouvementStock): static
    {
        if (!$this->mouvementStocksDestination->contains($mouvementStock)) {
            $this->mouvementStocksDestination->add($mouvementStock);
            $mouvementStock->setDepotDestination($this);
        }

        return $this;
    }

    public function removeMouvementStocksDestination(MouvementStock $mouvementStock): static
    {
        if ($this->mouvementStocksDestination->removeElement($mouvementStock)) {
            // set the owning side to null (unless already changed)
            if ($mouvementStock->getDepotDestination() === $this) {
                $mouvementStock->setDepotDestination(null);
            }
        }

        return $this;
    }
}
