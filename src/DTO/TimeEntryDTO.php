<?php

namespace App\DTO;

use App\Entity\TimeEntry;

class TimeEntryDTO
{
    public function __construct(
        public int $id,
        public int $taskId,
        public int $projectId,
        public string $projectName,
        public string $startedAt,
        public ?string $endedAt,
        public int $minutes,
        public bool $billable,
        public ?string $notes,
        public ?string $costRateSnapshot,
        public ?string $billRateSnapshot,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(TimeEntry $timeEntry): self
    {
        return new self(
            $timeEntry->getId() ?? 0,
            $timeEntry->getTask()?->getId() ?? 0,
            $timeEntry->getProject()?->getId() ?? 0,
            $timeEntry->getProject()?->getName() ?? '',
            $timeEntry->getStartedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            $timeEntry->getEndedAt()?->format(\DateTimeInterface::ATOM),
            $timeEntry->getMinutes(),
            $timeEntry->isBillable(),
            $timeEntry->getNotes(),
            $timeEntry->getCostRateSnapshot(),
            $timeEntry->getBillRateSnapshot(),
            $timeEntry->getCreatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
            $timeEntry->getUpdatedAt()?->format(\DateTimeInterface::ATOM) ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'taskId' => $this->taskId,
            'projectId' => $this->projectId,
            'projectName' => $this->projectName,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'minutes' => $this->minutes,
            'billable' => $this->billable,
            'notes' => $this->notes,
            'costRateSnapshot' => $this->costRateSnapshot,
            'billRateSnapshot' => $this->billRateSnapshot,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
