<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function save(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->persist($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->remove($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Notification[]
     */
    public function findByState(string $state): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.state = :state')
            ->setParameter('state', $state)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findByChannel(string $channel): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByState(string $state): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.state = :state')
            ->setParameter('state', $state)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByChannel(string $channel): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete notifications older than the given date.
     */
    public function deleteOlderThan(\DateTimeImmutable $date): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Get statistics grouped by state.
     *
     * @return array<string, int>
     */
    public function getStatsByState(): array
    {
        $results = $this->createQueryBuilder('n')
            ->select('n.state, COUNT(n.id) as count')
            ->groupBy('n.state')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['state']] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Get statistics grouped by channel.
     *
     * @return array<string, int>
     */
    public function getStatsByChannel(): array
    {
        $results = $this->createQueryBuilder('n')
            ->select('n.channel, COUNT(n.id) as count')
            ->groupBy('n.channel')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $stats[$result['channel']] = (int) $result['count'];
        }

        return $stats;
    }
}
