<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    // Champs de profil (optionnels)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // Reset password
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetRequestedAt = null;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: MouvementStock::class)]
    private Collection $mouvementStocks;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserActivity::class)]
    private Collection $activities;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    // 2FA
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(length: 20, options: ['default' => 'email'])]
    private string $twoFactorDefaultMethod = 'email'; // email|totp|sms

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $twoFactorBackupCodes = null; // store hashed codes

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $twoFactorEmailCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $twoFactorEmailExpiresAt = null;

    public function __construct()
    {
        $this->mouvementStocks = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->enabled = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // Profil: prénom
    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    // Profil: nom
    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    // Profil: téléphone
    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    // Profil: poste
    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(?string $poste): static
    {
        $this->poste = $poste;
        return $this;
    }

    // Profil: dernière connexion
    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    // Reset token
    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetRequestedAt(): ?\DateTimeImmutable
    {
        return $this->resetRequestedAt;
    }

    public function setResetRequestedAt(?\DateTimeImmutable $resetRequestedAt): static
    {
        $this->resetRequestedAt = $resetRequestedAt;
        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
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
            $mouvementStock->setCreatedBy($this);
        }

        return $this;
    }

    public function removeMouvementStock(MouvementStock $mouvementStock): static
    {
        if ($this->mouvementStocks->removeElement($mouvementStock)) {
            // set the owning side to null (unless already changed)
            if ($mouvementStock->getCreatedBy() === $this) {
                $mouvementStock->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserActivity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(UserActivity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setUser($this);
        }

        return $this;
    }

    public function removeActivity(UserActivity $activity): static
    {
        if ($this->activities->removeElement($activity)) {
            // set the owning side to null (unless already changed)
            if ($activity->getUser() === $this) {
                $activity->setUser(null);
            }
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    // 2FA accessors
    public function isTwoFactorEnabled(): bool { return $this->twoFactorEnabled; }
    public function setTwoFactorEnabled(bool $enabled): self { $this->twoFactorEnabled = $enabled; return $this; }

    public function getTwoFactorDefaultMethod(): string { return $this->twoFactorDefaultMethod; }
    public function setTwoFactorDefaultMethod(string $method): self { $this->twoFactorDefaultMethod = $method; return $this; }

    public function getTwoFactorBackupCodes(): ?array { return $this->twoFactorBackupCodes; }
    public function setTwoFactorBackupCodes(?array $codes): self { $this->twoFactorBackupCodes = $codes; return $this; }

    public function getTwoFactorEmailCode(): ?string { return $this->twoFactorEmailCode; }
    public function setTwoFactorEmailCode(?string $code): self { $this->twoFactorEmailCode = $code; return $this; }

    public function getTwoFactorEmailExpiresAt(): ?\DateTimeImmutable { return $this->twoFactorEmailExpiresAt; }
    public function setTwoFactorEmailExpiresAt(?\DateTimeImmutable $dt): self { $this->twoFactorEmailExpiresAt = $dt; return $this; }
}
