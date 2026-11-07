<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface
{
    public const ROLE_ORGANIZER = 'ROLE_ORGANIZER';
    public const ROLE_PARTICIPANT = 'ROLE_PARTICIPANT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // E-mail jest opcjonalny: Facebook go nie zawsze zwraca.
    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $fullname = null;

    // Nazwa kolumny zostaje created_at, tak jak w starych migracjach.
    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'user')]
    private Collection $payments;

    /** @var Collection<int, UserIdentity> */
    #[ORM\OneToMany(targetEntity: UserIdentity::class, mappedBy: 'user')]
    private Collection $identities;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->identities = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFullname(): ?string
    {
        return $this->fullname;
    }

    public function setFullname(string $fullname): static
    {
        $this->fullname = $fullname;

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

    /** Identyfikator w security to id: e-mail bywa pusty (logowanie przez Facebooka). */
    public function getUserIdentifier(): string
    {
        return (string) $this->id;
    }

    /** @return Collection<int, UserIdentity> */
    public function getIdentities(): Collection
    {
        return $this->identities;
    }

    public function hasProvider(string $provider): bool
    {
        foreach ($this->identities as $identity) {
            if ($identity->getProvider() === $provider) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** Rola wybrana przez osobę przy pierwszym logowaniu: 'organizer', 'participant' albo null (jeszcze nie wybrała). */
    public function getAppRole(): ?string
    {
        return match (true) {
            \in_array(self::ROLE_ORGANIZER, $this->roles, true) => 'organizer',
            \in_array(self::ROLE_PARTICIPANT, $this->roles, true) => 'participant',
            default => null,
        };
    }

    public function isOrganizer(): bool
    {
        return 'organizer' === $this->getAppRole();
    }

    /** Ustawia wybraną rolę ('organizer' lub 'participant'); wszystkie inne role zostają zastąpione. */
    public function chooseRole(string $role): static
    {
        $this->roles = ['organizer' === $role ? self::ROLE_ORGANIZER : self::ROLE_PARTICIPANT];

        return $this;
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setUser($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment) && $payment->getUser() === $this) {
            $payment->setUser(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->fullname;
    }
}
