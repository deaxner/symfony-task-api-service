<?php

namespace App\DTO;

use App\Entity\Task;

class TaskDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public string $status,
        public string $priority,
        public array $project,
        public ?string $dueDate,
        public ?string $startedAt,
        public ?string $completedAt,
        public int $totalLoggedMinutes,
        public int $billableMinutes,
        public int $timeEntriesCount,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Task $task, array $timeSummary = []): self
    {
        return new self(
            $task->getId() ?? 0,
            $task->getTitle() ?? '',
            $task->getDescription(),
            $task->getStatus(),
            $task->getPriority(),
            ProjectDTO::fromEntity($task->getProject())->toArray(),
            $task->getDueDate()?->format(\DateTimeInterface::ATOM),
            $task->getStartedAt()?->format(\DateTimeInterface::ATOM),
            $task->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            (int) ($timeSummary['totalLoggedMinutes'] ?? 0),
            (int) ($timeSummary['billableMinutes'] ?? 0),
            (int) ($timeSummary['timeEntries'] ?? 0),
            $task->getCreatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            $task->getUpdatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'project' => $this->project,
            'dueDate' => $this->dueDate,
            'startedAt' => $this->startedAt,
            'completedAt' => $this->completedAt,
            'totalLoggedMinutes' => $this->totalLoggedMinutes,
            'billableMinutes' => $this->billableMinutes,
            'timeEntriesCount' => $this->timeEntriesCount,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
