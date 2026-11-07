<?php

namespace App\Entity;

use App\Repository\PlaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 64)]
    private ?string $city = null;

    #[ORM\Column(length: 25)]
    private ?string $zip = null;

    /** @var Collection<int, Game> */
    #[ORM\OneToMany(targetEntity: Game::class, mappedBy: 'place')]
    private Collection $games;

    /** @var Collection<int, PlaceMember> */
    #[ORM\OneToMany(targetEntity: PlaceMember::class, mappedBy: 'place')]
    private Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->games = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function setZip(string $zip): static
    {
        $this->zip = $zip;

        return $this;
    }

    /** @return Collection<int, PlaceMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    /** @return Collection<int, Game> */
    public function getGames(): Collection
    {
        return $this->games;
    }

    public function addGame(Game $game): static
    {
        if (!$this->games->contains($game)) {
            $this->games->add($game);
            $game->setPlace($this);
        }

        return $this;
    }

    public function removeGame(Game $game): static
    {
        if ($this->games->removeElement($game) && $game->getPlace() === $this) {
            $game->setPlace(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
