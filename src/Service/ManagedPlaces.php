<?php

namespace App\Service;

use App\Entity\Place;
use App\Entity\User;
use App\Repository\PlaceMemberRepository;
use App\Repository\PlaceRepository;

/** Miejsca, którymi osoba zarządza: organizator swoimi, administrator serwisu wszystkimi. */
final readonly class ManagedPlaces
{
    public function __construct(
        private PlaceMemberRepository $members,
        private PlaceRepository $places,
    ) {
    }

    /** @return list<Place> */
    public function forUser(User $user): array
    {
        return \in_array('ROLE_ADMIN', $user->getRoles(), true)
            ? $this->places->findBy([], ['name' => 'ASC'])
            : $this->members->managedPlacesOf($user);
    }
}
