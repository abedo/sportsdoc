<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /** @return list<Game> najnowsze gry w miejscu (data malejąco) */
    public function latestForPlace(Place $place, int $limit = 5): array
    {
        return $this->findBy(['place' => $place], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /** @return list<Game> wszystkie gry w miejscu, najnowsze pierwsze (slider w widoku gry) */
    public function allForPlace(Place $place): array
    {
        return $this->findBy(['place' => $place], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * @param list<Place> $places
     *
     * @return list<Game> gry w podanych miejscach, najnowsze pierwsze
     */
    public function latestForPlaces(array $places): array
    {
        if ([] === $places) {
            return [];
        }

        return $this->findBy(['place' => $places], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /** Koszt ostatniej gry w miejscu (podpowiedź dla nowej gry) albo 0. */
    public function lastCostForPlace(Place $place): int
    {
        $last = $this->findOneBy(['place' => $place], ['createdAt' => 'DESC', 'id' => 'DESC']);

        return $last?->getCost() ?? 0;
    }
}
