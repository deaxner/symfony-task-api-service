<?php

namespace App\Repository;

use App\Entity\TimeEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeEntry>
 */
class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    /** @return list<TimeEntry> */
    public function findByUserAndFilters(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('timeEntry')
            ->addSelect('task', 'project')
            ->join('timeEntry.user', 'user')
            ->join('timeEntry.task', 'task')
            ->join('timeEntry.project', 'project')
            ->andWhere('timeEntry.user = :user')
            ->setParameter('user', $user)
            ->orderBy('timeEntry.startedAt', 'DESC');

        if (!empty($filters['taskId'])) {
            $qb->andWhere('task.id = :taskId')->setParameter('taskId', (int) $filters['taskId']);
        }

        if (!empty($filters['projectId'])) {
            $qb->andWhere('project.id = :projectId')->setParameter('projectId', (int) $filters['projectId']);
        }

        if (array_key_exists('billable', $filters) && null !== $filters['billable']) {
            $qb->andWhere('timeEntry.billable = :billable')->setParameter('billable', (bool) $filters['billable']);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneOwnedByUser(int $id, User $user): ?TimeEntry
    {
        return $this->createQueryBuilder('timeEntry')
            ->addSelect('task', 'project')
            ->join('timeEntry.task', 'task')
            ->join('timeEntry.project', 'project')
            ->andWhere('timeEntry.id = :id')
            ->andWhere('timeEntry.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, array{taskId: int, totalMinutes: string, billableMinutes: string, timeEntries: string}> */
    public function summarizeByTask(User $user): array
    {
        return $this->createQueryBuilder('timeEntry')
            ->select('IDENTITY(timeEntry.task) AS taskId, COALESCE(SUM(timeEntry.minutes), 0) AS totalMinutes, COALESCE(SUM(CASE WHEN timeEntry.billable = true THEN timeEntry.minutes ELSE 0 END), 0) AS billableMinutes, COUNT(timeEntry.id) AS timeEntries')
            ->andWhere('timeEntry.user = :user')
            ->setParameter('user', $user)
            ->groupBy('timeEntry.task')
            ->getQuery()
            ->getArrayResult();
    }

    /** @return array<int, array{projectId: int, totalMinutes: string, billableMinutes: string}> */
    public function summarizeByProject(User $user): array
    {
        return $this->createQueryBuilder('timeEntry')
            ->select('IDENTITY(timeEntry.project) AS projectId, COALESCE(SUM(timeEntry.minutes), 0) AS totalMinutes, COALESCE(SUM(CASE WHEN timeEntry.billable = true THEN timeEntry.minutes ELSE 0 END), 0) AS billableMinutes')
            ->andWhere('timeEntry.user = :user')
            ->setParameter('user', $user)
            ->groupBy('timeEntry.project')
            ->getQuery()
            ->getArrayResult();
    }
}
