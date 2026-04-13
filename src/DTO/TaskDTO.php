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
        public ?string $dueDate,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Task $task): self
    {
        return new self(
            $task->getId() ?? 0,
            $task->getTitle() ?? '',
            $task->getDescription(),
            $task->getStatus(),
            $task->getPriority(),
            $task->getDueDate()?->format(\DateTimeInterface::ATOM),
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
            'dueDate' => $this->dueDate,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
