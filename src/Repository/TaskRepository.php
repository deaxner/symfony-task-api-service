<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * @param array{page?: int, limit?: int, status?: ?string, priority?: ?string, search?: ?string, sort?: ?string, direction?: ?string} $filters
     *
     * @return array{items: Task[], total: int, page: int, limit: int, pages: int}
     */
    public function findPaginatedByUserAndFilters(User $user, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $sortField = match ($filters['sort'] ?? 'createdAt') {
            'updatedAt' => 't.updatedAt',
            'dueDate' => 't.dueDate',
            default => 't.createdAt',
        };

        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy($sortField, $direction)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $filters['priority']);
        }

        if (!empty($filters['search'])) {
            $qb
                ->andWhere('LOWER(t.title) LIKE :search OR LOWER(COALESCE(t.description, \'\')) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower((string) $filters['search']) . '%');
        }

        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => max(1, (int) ceil($total / $limit)),
        ];
    }

    public function findOneOwnedByUser(int $id, User $user): ?Task
    {
        return $this->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);
    }
}
