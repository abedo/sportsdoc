<?php

namespace App\Repository;

use App\Entity\Payment;
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

    /** Suma potwierdzonych wpłat osoby. */
    public function totalPaid(User $user): int
    {
        return $this->sumFor($user, true);
    }

    /** Suma wpłat zgłoszonych przez osobę, które czekają na potwierdzenie. */
    public function totalPending(User $user): int
    {
        return $this->sumFor($user, false);
    }

    public function pendingCount(): int
    {
        return $this->count(['confirmed' => false]);
    }

    /** @return list<Payment> */
    public function latest(): array
    {
        return $this->findBy([], ['confirmed' => 'ASC', 'date' => 'DESC', 'id' => 'DESC']);
    }

    private function sumFor(User $user, bool $confirmed): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->where('p.user = :user')
            ->andWhere('p.confirmed = :confirmed')
            ->setParameter('user', $user)
            ->setParameter('confirmed', $confirmed)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
