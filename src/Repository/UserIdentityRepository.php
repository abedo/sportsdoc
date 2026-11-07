<?php

namespace App\Repository;

use App\Entity\UserIdentity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserIdentity>
 */
class UserIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserIdentity::class);
    }

    public function findOneFor(string $provider, string $subject): ?UserIdentity
    {
        return $this->findOneBy(['provider' => $provider, 'subject' => $subject]);
    }
}
