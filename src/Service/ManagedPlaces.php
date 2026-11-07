<?php

namespace App\Service;

use App\Entity\Place;
use App\Entity\User;
use App\Repository\PlaceMemberRepository;

/** Miejsca, którymi osoba zarządza: te, w których jest organizatorem. */
final readonly class ManagedPlaces
{
    public function __construct(private PlaceMemberRepository $members)
    {
    }

    /** @return list<Place> */
    public function forUser(User $user): array
    {
        return $this->members->managedPlacesOf($user);
    }
}
