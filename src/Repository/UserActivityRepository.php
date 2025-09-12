<?php

namespace App\Repository;

use App\Entity\UserActivity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserActivity>
 */
class UserActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserActivity::class);
    }

    /**
     * Trouve l'historique complet d'un utilisateur
     */
    public function findUserHistory(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total d'activités d'un utilisateur
     */
    public function countUserActivities(User $user): int
    {
        return $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les activités récentes d'un utilisateur (dernières 24h)
     */
    public function findRecentUserActivities(User $user, int $limit = 10): array
    {
        $yesterday = new \DateTimeImmutable('-24 hours');
        
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.createdAt >= :yesterday')
            ->setParameter('user', $user)
            ->setParameter('yesterday', $yesterday)
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les activités par type d'action
     */
    public function findUserActivitiesByAction(User $user, string $action, int $limit = 10): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.action = :action')
            ->setParameter('user', $user)
            ->setParameter('action', $action)
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtient les statistiques d'activité d'un utilisateur
     */
    public function getUserActivityStats(User $user): array
    {
        $qb = $this->createQueryBuilder('ua')
            ->select('ua.action, COUNT(ua.id) as count')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->groupBy('ua.action')
            ->orderBy('count', 'DESC');

        $results = $qb->getQuery()->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['action']] = (int) $result['count'];
        }
        
        return $stats;
    }

    /**
     * Trouve les dernières connexions d'un utilisateur
     */
    public function findUserLogins(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('ua')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.action = :action')
            ->setParameter('user', $user)
            ->setParameter('action', UserActivity::ACTION_LOGIN)
            ->orderBy('ua.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
