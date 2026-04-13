<?php

namespace App\Service;

use App\DTO\TaskDTO;
use App\DTO\TaskRequestDTO;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $auditLogger,
    ) {
    }

    public function createTask(User $user, TaskRequestDTO $dto): TaskDTO
    {
        $task = (new Task())
            ->setUser($user)
            ->setProject($this->requireProject($dto->projectId, $user))
            ->setTitle($dto->title ?? '')
            ->setDescription($dto->description)
            ->setStatus($dto->status ?? 'todo')
            ->setPriority($dto->priority ?? 'medium')
            ->setDueDate($this->parseDate($dto->dueDate));

        $this->applyTimeline($task, $dto);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->auditLogger->info('Task created.', [
            'userId' => $user->getId(),
            'taskId' => $task->getId(),
        ]);

        return TaskDTO::fromEntity($task, $this->taskTimeSummary($user)[$task->getId() ?? 0] ?? []);
    }

    public function getTaskForUser(int $id, User $user): TaskDTO
    {
        return TaskDTO::fromEntity($this->requireTask($id, $user));
    }

    /**
     * @param array{page?: int, limit?: int, status?: ?string, priority?: ?string, projectId?: ?int, search?: ?string, sort?: ?string, direction?: ?string} $filters
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listTasks(User $user, array $filters): array
    {
        $result = $this->taskRepository->findPaginatedByUserAndFilters($user, $filters);
        $summary = $this->taskTimeSummary($user);

        return [
            'data' => array_map(
                static fn (Task $task): array => TaskDTO::fromEntity($task, $summary[$task->getId() ?? 0] ?? [])->toArray(),
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
            ->setProject($this->requireProject($dto->projectId, $user))
            ->setTitle($dto->title ?? '')
            ->setDescription($dto->description)
            ->setStatus($dto->status ?? 'todo')
            ->setPriority($dto->priority ?? 'medium')
            ->setDueDate($this->parseDate($dto->dueDate));

        $this->applyTimeline($task, $dto);

        $this->entityManager->flush();

        $this->auditLogger->info('Task updated.', [
            'userId' => $user->getId(),
            'taskId' => $task->getId(),
        ]);

        return TaskDTO::fromEntity($task, $this->taskTimeSummary($user)[$task->getId() ?? 0] ?? []);
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

    private function requireProject(?int $projectId, User $user): Project
    {
        $project = null === $projectId ? null : $this->projectRepository->findOneOwnedByUser($projectId, $user);
        if (!$project instanceof Project) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $project;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return new \DateTimeImmutable($value);
    }

    private function applyTimeline(Task $task, TaskRequestDTO $dto): void
    {
        $startedAt = $this->parseDate($dto->startedAt);
        $completedAt = $this->parseDate($dto->completedAt);

        if ('in_progress' === $dto->status && null === $startedAt) {
            $startedAt = $task->getStartedAt() ?? new \DateTimeImmutable();
        }

        if ('done' === $dto->status && null === $completedAt) {
            $completedAt = new \DateTimeImmutable();
        }

        if ('todo' === $dto->status) {
            $completedAt = null;
        }

        $task
            ->setStartedAt($startedAt)
            ->setCompletedAt($completedAt);
    }

    /** @return array<int, array<string, int>> */
    private function taskTimeSummary(User $user): array
    {
        $summary = [];
        foreach ($this->timeEntryRepository->summarizeByTask($user) as $row) {
            $summary[(int) $row['taskId']] = [
                'totalLoggedMinutes' => (int) $row['totalMinutes'],
                'billableMinutes' => (int) $row['billableMinutes'],
                'timeEntries' => (int) $row['timeEntries'],
            ];
        }

        return $summary;
    }
}
