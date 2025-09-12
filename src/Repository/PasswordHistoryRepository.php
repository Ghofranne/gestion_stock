<?php

namespace App\Repository;

use App\Entity\PasswordHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordHistory>
 */
class PasswordHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordHistory::class);
    }

    /**
     * @return PasswordHistory[]
     */
    public function findRecentByUser(User $user, int $limit): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.user = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function pruneExceeding(User $user, int $keep): void
    {
        if ($keep <= 0) {
            $this->createQueryBuilder('h')
                ->delete()
                ->andWhere('h.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute();
            return;
        }

        $rows = $this->createQueryBuilder('h')
            ->select('h.id')
            ->andWhere('h.user = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($keep)
            ->getQuery()
            ->getScalarResult();
        $idsToKeep = array_map(static fn($r) => (int) $r['id'], $rows);

        if (!empty($idsToKeep)) {
            $this->createQueryBuilder('h')
                ->delete()
                ->andWhere('h.user = :user')
                ->andWhere('h.id NOT IN (:ids)')
                ->setParameter('user', $user)
                ->setParameter('ids', $idsToKeep)
                ->getQuery()
                ->execute();
        }
    }
}
