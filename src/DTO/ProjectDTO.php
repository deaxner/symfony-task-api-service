<?php

namespace App\DTO;

use App\Entity\Project;

class ProjectDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $color,
        public ?string $description,
        public ?string $externalProjectKey,
    ) {
    }

    public static function fromEntity(Project $project): self
    {
        return new self(
            $project->getId() ?? 0,
            $project->getName(),
            $project->getColor(),
            $project->getDescription(),
            $project->getExternalProjectKey(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'description' => $this->description,
            'externalProjectKey' => $this->externalProjectKey,
        ];
    }
}
