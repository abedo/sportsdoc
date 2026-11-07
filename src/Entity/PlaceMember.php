<?php

namespace App\Entity;

use App\Repository\PlaceMemberRepository;
use Doctrine\ORM\Mapping as ORM;

/** Członkostwo osoby w miejscu. Rola jest per miejsce: organizator albo zwykły uczestnik. */
#[ORM\Entity(repositoryClass: PlaceMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_place_member', columns: ['place_id', 'user_id'])]
class PlaceMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Place $place = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $organizer = false;

    #[ORM\Column(name: 'joined_at')]
    private \DateTimeImmutable $joinedAt;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): static
    {
        $this->place = $place;

        return $this;
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

    public function isOrganizer(): bool
    {
        return $this->organizer;
    }

    public function setOrganizer(bool $organizer): static
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
}
