<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
#[UniqueEntity(fields: ['imei'], errorPath: 'imei', message: 'Cet IMEI existe déjà.')]
#[UniqueEntity(fields: ['sn'], errorPath: 'sn', message: 'Ce numéro de série existe déjà.')]
#[UniqueEntity(fields: ['referenceCarte'], errorPath: 'referenceCarte', message: 'Cette référence de carte existe déjà.')]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Choice(choices: ['SIM','TPE','Carte','Autres'], message: 'Type invalide.')]
    private ?string $typeEquipement = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le SN ne doit pas dépasser {{ limit }} caractères.', groups: ['tpe'])]
    private ?string $sn = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 120, maxMessage: 'Le modèle ne doit pas dépasser {{ limit }} caractères.', groups: ['tpe'])]
    private ?string $model = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(pattern: '/^\\d{15}$/', message: 'IMEI invalide, 15 chiffres requis.', groups: ['sim'])]
    private ?string $imei = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Regex(pattern: '/^\+?\d{8,15}$/', message: 'Numéro d\'appel invalide.', groups: ['sim'])]
    private ?string $numAppel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'L\'opérateur ne doit pas dépasser {{ limit }} caractères.', groups: ['sim'])]
    #[Assert\Choice(choices: ['Tunisie Telecom','Ooredoo','Orange Tunisie'], message: 'Opérateur non valide.', groups: ['sim'])]
    private ?string $operateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'La référence de carte ne doit pas dépasser {{ limit }} caractères.', groups: ['carte'])]
    private ?string $referenceCarte = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 120, maxMessage: 'Le fournisseur ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $fournisseur = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 50)]
    private ?string $etat = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    /**
     * @var Collection<int, MouvementStock>
     */
    #[ORM\OneToMany(targetEntity: MouvementStock::class, mappedBy: 'equipement')]
    private Collection $mouvementStocks;

    public function __construct()
    {
        $this->mouvementStocks = new ArrayCollection();
        // Valeur par défaut pour l'état d'un nouvel équipement
        $this->etat = 'Disponible';
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

    public function setFournisseur(?string $fournisseur): static
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // Règles spécifiques par type
        if (($this->typeEquipement === 'Autres') && (null === $this->description || trim($this->description) === '')) {
            $context->buildViolation('La description est obligatoire pour le type "Autres".')
                ->atPath('description')
                ->addViolation();
        }
        if ($this->typeEquipement === 'SIM') {
            if (null === $this->imei || trim($this->imei) === '') {
                $context->buildViolation('L\'IMEI est obligatoire pour le type SIM.')
                    ->atPath('imei')
                    ->addViolation();
            }
        }
        if ($this->typeEquipement === 'TPE') {
            if (null === $this->sn || trim($this->sn) === '') {
                $context->buildViolation('Le numéro de série (SN) est obligatoire pour le type TPE.')
                    ->atPath('sn')
                    ->addViolation();
            }
            if (null === $this->model || trim($this->model) === '') {
                $context->buildViolation('Le modèle est obligatoire pour le type TPE.')
                    ->atPath('model')
                    ->addViolation();
            }
        }
        if ($this->typeEquipement === 'Carte') {
            if (null === $this->referenceCarte || trim($this->referenceCarte) === '') {
                $context->buildViolation('La référence de la carte est obligatoire pour le type Carte.')
                    ->atPath('referenceCarte')
                    ->addViolation();
            }
        }
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
