<?php

namespace App\Entity;

use App\Repository\UserIdentityRepository;
use Doctrine\ORM\Mapping as ORM;

/** Konto u dostawcy logowania (google, facebook) powiązane z użytkownikiem. Jedna osoba może mieć kilka. */
#[ORM\Entity(repositoryClass: UserIdentityRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_identity', columns: ['provider', 'subject'])]
class UserIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $provider;

    /** Stały identyfikator osoby u dostawcy (nie e-mail, bo Facebook nie zawsze go zwraca). */
    #[ORM\Column(length: 191)]
    private string $subject;

    #[ORM\ManyToOne(inversedBy: 'identities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function __construct(string $provider, string $subject, User $user)
    {
        $this->provider = $provider;
        $this->subject = $subject;
        $this->user = $user;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
