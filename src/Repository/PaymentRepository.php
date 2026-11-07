<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Place;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /** Suma potwierdzonych wpłat osoby w miejscu. */
    public function totalPaid(User $user, Place $place): int
    {
        return $this->sumFor($user, $place, true);
    }

    /** Suma wpłat zgłoszonych przez osobę w miejscu, które czekają na potwierdzenie. */
    public function totalPending(User $user, Place $place): int
    {
        return $this->sumFor($user, $place, false);
    }

    /**
     * Liczba wpłat czekających na potwierdzenie w podanych miejscach.
     *
     * @param list<Place> $places
     */
    public function pendingCount(array $places): int
    {
        return [] === $places ? 0 : $this->count(['confirmed' => false, 'place' => $places]);
    }

    /**
     * @param list<Place> $places
     *
     * @return list<Payment> wpłaty w podanych miejscach: najpierw czekające na potwierdzenie
     */
    public function latestForPlaces(array $places): array
    {
        if ([] === $places) {
            return [];
        }

        return $this->findBy(['place' => $places], ['confirmed' => 'ASC', 'date' => 'DESC', 'id' => 'DESC']);
    }

    private function sumFor(User $user, Place $place, bool $confirmed): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->where('p.user = :user')
            ->andWhere('p.place = :place')
            ->andWhere('p.confirmed = :confirmed')
            ->setParameter('user', $user)
            ->setParameter('place', $place)
            ->setParameter('confirmed', $confirmed)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
