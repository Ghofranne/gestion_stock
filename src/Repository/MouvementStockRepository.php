<?php

namespace App\Repository;

use App\Entity\MouvementStock;
use App\Entity\Depot;
use App\Entity\Equipement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MouvementStock>
 */
class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }

    //    /**
    //     * @return MouvementStock[] Returns an array of MouvementStock objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    /**
     * Récupère l'historique des activités d'un utilisateur
     * @param User $user
     * @param int $limit
     * @return MouvementStock[]
     */
    public function findUserHistory($user, int $limit = 20): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.equipement', 'e')
            ->leftJoin('m.depotSource', 'ds')
            ->leftJoin('m.depotDestination', 'dd')
            ->addSelect('e', 'ds', 'dd')
            ->andWhere('m.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('m.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total d'activités d'un utilisateur
     * @param User $user
     * @return int
     */
    public function countUserActivities($user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.createdBy = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne les équipements actuellement présents dans un dépôt donné.
     * La présence est calculée en fonction des mouvements: entrées (destination)
     * moins sorties (source). Si le solde > 0 alors l'équipement est dans le dépôt.
     * Optionnellement filtrer par type d'équipement.
     * @return Equipement[]
     */
    public function getEquipementsInDepot(Depot $depot, ?string $typeFilter = null): array
    {
        // Récupérer tous les mouvements liés au dépôt (destination et source)
        $em = $this->getEntityManager();

        $mouvementsEntree = $this->findBy(['depotDestination' => $depot]);
        $mouvementsSortie = $this->findBy(['depotSource' => $depot]);

        $stockParEquipement = [];
        /** @var Equipement[] $equipementsDetails */
        $equipementsDetails = [];

        foreach ($mouvementsEntree as $mouvement) {
            $eq = $mouvement->getEquipement();
            if (!$eq) { continue; }
            if ($typeFilter && $eq->getTypeEquipement() !== $typeFilter) { continue; }
            $id = $eq->getId();
            $stockParEquipement[$id] = ($stockParEquipement[$id] ?? 0) + 1;
            $equipementsDetails[$id] = $eq;
        }

        foreach ($mouvementsSortie as $mouvement) {
            $eq = $mouvement->getEquipement();
            if (!$eq) { continue; }
            if ($typeFilter && $eq->getTypeEquipement() !== $typeFilter) { continue; }
            $id = $eq->getId();
            $stockParEquipement[$id] = ($stockParEquipement[$id] ?? 0) - 1;
            $equipementsDetails[$id] = $eq;
        }

        $result = [];
        foreach ($stockParEquipement as $id => $qte) {
            if ($qte > 0 && isset($equipementsDetails[$id])) {
                $result[] = $equipementsDetails[$id];
            }
        }

        return $result;
    }

    /**
     * Calcule le solde (entrées - sorties) par équipement sur l'ensemble des dépôts.
     * Si $typeFilter est fourni, ne prend en compte que les mouvements dont l'équipement
     * est de ce type.
     * @return array<int,int> tableau [equipementId => net]
     */
    public function getNetStockByEquipement(?string $typeFilter = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.equipement', 'e')
            ->addSelect('e')
            ->andWhere('m.equipement IS NOT NULL');

        if ($typeFilter) {
            $qb->andWhere('e.typeEquipement = :tf')->setParameter('tf', $typeFilter);
        }

        $mouvements = $qb->getQuery()->getResult();
        $net = [];
        /** @var MouvementStock $m */
        foreach ($mouvements as $m) {
            $eq = $m->getEquipement();
            if (!$eq) { continue; }
            $id = $eq->getId();
            if (!array_key_exists($id, $net)) { $net[$id] = 0; }
            if ($m->getDepotDestination()) { $net[$id] += 1; }
            if ($m->getDepotSource()) { $net[$id] -= 1; }
        }
        return $net;
    }

    /**
     * Retourne les IDs d'équipements actuellement affectés à un dépôt.
     * Critère: le dernier mouvement (par date puis id) possède un depotDestination non null.
     * Optionnellement filtrer par type d'équipement.
     * @return int[]
     */
    public function getCurrentlyAssignedEquipementIds(?string $typeFilter = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.equipement', 'e')
            ->addSelect('e')
            ->andWhere('m.equipement IS NOT NULL')
            ->orderBy('m.date', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        if ($typeFilter) {
            $qb->andWhere('e.typeEquipement = :tf')->setParameter('tf', $typeFilter);
        }

        $mouvements = $qb->getQuery()->getResult();
        $lastByEquipement = [];
        /** @var MouvementStock $m */
        foreach ($mouvements as $m) {
            $eq = $m->getEquipement();
            if (!$eq) { continue; }
            $lastByEquipement[$eq->getId()] = $m; // Ordre ASC -> on écrase jusqu'au dernier
        }

        $assignedIds = [];
        foreach ($lastByEquipement as $eqId => $m) {
            if ($m->getDepotDestination() !== null) {
                $assignedIds[] = (int)$eqId;
            }
        }
        return $assignedIds;
    }
}
