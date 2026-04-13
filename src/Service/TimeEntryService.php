<?php

namespace App\Service;

use App\DTO\TimeEntryDTO;
use App\DTO\TimeEntryRequestDTO;
use App\Entity\Task;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TimeEntryService
{
    public function __construct(
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(User $user, array $filters = []): array
    {
        return array_map(
            static fn (TimeEntry $timeEntry): array => TimeEntryDTO::fromEntity($timeEntry)->toArray(),
            $this->timeEntryRepository->findByUserAndFilters($user, $filters)
        );
    }

    public function create(User $user, TimeEntryRequestDTO $dto): TimeEntryDTO
    {
        $task = $this->requireTask($dto->taskId, $user);
        $timeEntry = (new TimeEntry())
            ->setUser($user)
            ->setTask($task)
            ->setProject($task->getProject())
            ->setStartedAt(new \DateTimeImmutable((string) $dto->startedAt))
            ->setBillable($dto->billable)
            ->setNotes($dto->notes)
            ->setCostRateSnapshot($this->normalizeDecimal($dto->costRateSnapshot))
            ->setBillRateSnapshot($this->normalizeDecimal($dto->billRateSnapshot));

        if (null !== $dto->endedAt && '' !== $dto->endedAt) {
            $timeEntry->setEndedAt(new \DateTimeImmutable($dto->endedAt));
        }

        $this->entityManager->persist($timeEntry);
        $this->entityManager->flush();

        return TimeEntryDTO::fromEntity($timeEntry);
    }

    public function update(int $id, User $user, TimeEntryRequestDTO $dto): TimeEntryDTO
    {
        $timeEntry = $this->requireTimeEntry($id, $user);
        $task = null === $dto->taskId
            ? $timeEntry->getTask()
            : $this->requireTask($dto->taskId, $user);

        if (!$task instanceof Task) {
            throw new NotFoundHttpException('Task not found.');
        }

        $timeEntry
            ->setTask($task)
            ->setProject($task->getProject())
            ->setStartedAt(new \DateTimeImmutable((string) $dto->startedAt))
            ->setEndedAt((null !== $dto->endedAt && '' !== $dto->endedAt) ? new \DateTimeImmutable($dto->endedAt) : null)
            ->setBillable($dto->billable)
            ->setNotes($dto->notes)
            ->setCostRateSnapshot($this->normalizeDecimal($dto->costRateSnapshot))
            ->setBillRateSnapshot($this->normalizeDecimal($dto->billRateSnapshot));

        $this->entityManager->flush();

        return TimeEntryDTO::fromEntity($timeEntry);
    }

    public function delete(int $id, User $user): void
    {
        $timeEntry = $this->requireTimeEntry($id, $user);
        $this->entityManager->remove($timeEntry);
        $this->entityManager->flush();
    }

    private function requireTask(?int $taskId, User $user): Task
    {
        $task = null === $taskId ? null : $this->taskRepository->findOneOwnedByUser($taskId, $user);
        if (!$task instanceof Task) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $task;
    }

    private function requireTimeEntry(int $id, User $user): TimeEntry
    {
        $timeEntry = $this->timeEntryRepository->findOneOwnedByUser($id, $user);
        if (!$timeEntry instanceof TimeEntry) {
            throw new NotFoundHttpException('Time entry not found.');
        }

        return $timeEntry;
    }

    private function normalizeDecimal(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
