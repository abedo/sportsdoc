<?php

namespace App\Repository;

use App\Entity\Attendance;
use App\Entity\Game;
use App\Entity\Place;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attendance>
 */
class AttendanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attendance::class);
    }

    /**
     * Obecność w grze jako mapa id osoby => był (true) / nie był (false).
     * Osoby bez wpisu nie występują w mapie (jeszcze nie zaznaczone).
     *
     * @return array<int, bool>
     */
    public function presenceMapForGame(Game $game): array
    {
        $map = [];
        foreach ($this->findBy(['game' => $game]) as $attendance) {
            $map[(int) $attendance->getUser()?->getId()] = $attendance->isPresent();
        }

        return $map;
    }

    /** Liczba gier w miejscu, w których osoba była, i suma ich kosztów. @return array{games: int, cost: int} */
    public function attendedTotals(User $user, Place $place): array
    {
        $row = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) AS games', 'COALESCE(SUM(g.cost), 0) AS cost')
            ->join('a.game', 'g')
            ->where('a.user = :user')
            ->andWhere('a.present = true')
            ->andWhere('g.place = :place')
            ->setParameter('user', $user)
            ->setParameter('place', $place)
            ->getQuery()
            ->getSingleResult();

        return ['games' => (int) $row['games'], 'cost' => (int) $row['cost']];
    }

    /**
     * Liczba obecnych osób w każdej grze: id gry => liczba.
     *
     * @return array<int, int>
     */
    public function presentCountsByGame(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.game) AS gameId', 'COUNT(a.id) AS cnt')
            ->where('a.present = true')
            ->groupBy('a.game')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['gameId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /** Ile różnych osób było kiedykolwiek na grach w danym miejscu. */
    public function distinctAttendeesForPlace(Place $place): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.user)')
            ->join('a.game', 'g')
            ->where('g.place = :place')
            ->andWhere('a.present = true')
            ->setParameter('place', $place)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneFor(Game $game, User $user): ?Attendance
    {
        return $this->findOneBy(['game' => $game, 'user' => $user]);
    }
}
