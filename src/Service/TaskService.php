<?php

namespace App\Service;

use App\DTO\TaskDTO;
use App\DTO\TaskRequestDTO;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $auditLogger,
    ) {
    }

    public function createTask(User $user, TaskRequestDTO $dto): TaskDTO
    {
        $task = (new Task())
            ->setUser($user)
            ->setTitle($dto->title ?? '')
            ->setDescription($dto->description)
            ->setStatus($dto->status ?? 'todo')
            ->setPriority($dto->priority ?? 'medium')
            ->setDueDate($this->parseDueDate($dto->dueDate));

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->auditLogger->info('Task created.', [
            'userId' => $user->getId(),
            'taskId' => $task->getId(),
        ]);

        return TaskDTO::fromEntity($task);
    }

    public function getTaskForUser(int $id, User $user): TaskDTO
    {
        return TaskDTO::fromEntity($this->requireTask($id, $user));
    }

    /**
     * @param array{page?: int, limit?: int, status?: ?string, priority?: ?string, search?: ?string, sort?: ?string, direction?: ?string} $filters
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listTasks(User $user, array $filters): array
    {
        $result = $this->taskRepository->findPaginatedByUserAndFilters($user, $filters);

        return [
            'data' => array_map(
                static fn (Task $task): array => TaskDTO::fromEntity($task)->toArray(),
                $result['items']
            ),
            'meta' => [
                'page' => $result['page'],
                'limit' => $result['limit'],
                'total' => $result['total'],
                'pages' => $result['pages'],
            ],
        ];
    }

    public function updateTask(int $id, User $user, TaskRequestDTO $dto): TaskDTO
    {
        $task = $this->requireTask($id, $user)
            ->setTitle($dto->title ?? '')
            ->setDescription($dto->description)
            ->setStatus($dto->status ?? 'todo')
            ->setPriority($dto->priority ?? 'medium')
            ->setDueDate($this->parseDueDate($dto->dueDate));

        $this->entityManager->flush();

        $this->auditLogger->info('Task updated.', [
            'userId' => $user->getId(),
            'taskId' => $task->getId(),
        ]);

        return TaskDTO::fromEntity($task);
    }

    public function deleteTask(int $id, User $user): void
    {
        $task = $this->requireTask($id, $user);

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        $this->auditLogger->info('Task deleted.', [
            'userId' => $user->getId(),
            'taskId' => $id,
        ]);
    }

    private function requireTask(int $id, User $user): Task
    {
        $task = $this->taskRepository->findOneOwnedByUser($id, $user);
        if (!$task instanceof Task) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $task;
    }

    private function parseDueDate(?string $dueDate): ?\DateTimeImmutable
    {
        if (null === $dueDate || '' === $dueDate) {
            return null;
        }

        return new \DateTimeImmutable($dueDate);
    }
}
