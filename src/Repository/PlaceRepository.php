<?php

namespace App\Repository;

use App\Entity\Place;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Place>
 */
class PlaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Place::class);
    }

    /**
     * Wyszukiwanie miejsc po nazwie, mieście lub adresie. Puste zapytanie zwraca pierwsze miejsca alfabetycznie.
     *
     * @return list<Place>
     */
    public function search(string $query, int $limit = 30): array
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.name', 'ASC')->setMaxResults($limit);
        foreach (array_filter(preg_split('/\s+/u', trim($query)) ?: []) as $i => $word) {
            $qb->andWhere("p.name LIKE :w$i OR p.city LIKE :w$i OR p.address LIKE :w$i")
                ->setParameter("w$i", '%'.addcslashes($word, '%_\\').'%');
        }

        return $qb->getQuery()->getResult();
    }
}
