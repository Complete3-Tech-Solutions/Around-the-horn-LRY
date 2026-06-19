<?php

namespace App\Repository;

use App\Entity\Founder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Founder>
 */
class FounderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Founder::class);
    }

    /**
     * Founders in ballot order (position 1..N).
     *
     * @return Founder[]
     */
    public function findOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function maxPosition(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COALESCE(MAX(f.position), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
