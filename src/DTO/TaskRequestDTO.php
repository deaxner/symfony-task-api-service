<?php

namespace App\DTO;

use App\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

class TaskRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\Length(max: 5000)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: Task::STATUSES)]
    public ?string $status = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: Task::PRIORITIES)]
    public ?string $priority = null;

    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public ?string $dueDate = null;
}
