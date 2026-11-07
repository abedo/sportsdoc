<?php

namespace App\Repository;

use App\Entity\Place;
use App\Entity\PlaceMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaceMember>
 */
class PlaceMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaceMember::class);
    }

    public function findOneFor(Place $place, User $user): ?PlaceMember
    {
        return $this->findOneBy(['place' => $place, 'user' => $user]);
    }

    public function isMember(Place $place, User $user): bool
    {
        return null !== $this->findOneFor($place, $user);
    }

    public function isOrganizer(Place $place, User $user): bool
    {
        return $this->findOneFor($place, $user)?->isOrganizer() ?? false;
    }

    /** @return list<Place> miejsca osoby, alfabetycznie */
    public function placesOf(User $user): array
    {
        return array_values(array_map(
            static fn (PlaceMember $m): Place => $m->getPlace(),
            $this->createQueryBuilder('m')
                ->addSelect('p')
                ->join('m.place', 'p')
                ->where('m.user = :user')
                ->setParameter('user', $user)
                ->orderBy('p.name', 'ASC')
                ->getQuery()
                ->getResult(),
        ));
    }

    /** @return list<Place> miejsca, w których osoba jest organizatorem */
    public function managedPlacesOf(User $user): array
    {
        return array_values(array_map(
            static fn (PlaceMember $m): Place => $m->getPlace(),
            $this->createQueryBuilder('m')
                ->addSelect('p')
                ->join('m.place', 'p')
                ->where('m.user = :user')
                ->andWhere('m.organizer = true')
                ->setParameter('user', $user)
                ->orderBy('p.name', 'ASC')
                ->getQuery()
                ->getResult(),
        ));
    }

    /** @return list<PlaceMember> członkowie miejsca: organizatorzy pierwsi, potem alfabetycznie */
    public function membersOf(Place $place): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('u')
            ->join('m.user', 'u')
            ->where('m.place = :place')
            ->setParameter('place', $place)
            ->orderBy('m.organizer', 'DESC')
            ->addOrderBy('u.fullname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<User> */
    public function usersOf(Place $place): array
    {
        return array_values(array_map(static fn (PlaceMember $m): User => $m->getUser(), $this->membersOf($place)));
    }

    public function organizerCount(Place $place): int
    {
        return $this->count(['place' => $place, 'organizer' => true]);
    }

    /**
     * Różne osoby ze wszystkich podanych miejsc, alfabetycznie.
     *
     * @param list<Place> $places
     *
     * @return list<User>
     */
    public function usersOfPlaces(array $places): array
    {
        if ([] === $places) {
            return [];
        }

        return $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN (SELECT IDENTITY(m.user) FROM '.PlaceMember::class.' m WHERE m.place IN (:places))')
            ->setParameter('places', $places)
            ->orderBy('u.fullname', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
