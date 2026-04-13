<?php

namespace App\DTO;

use App\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    public ?string $dueDate = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null === $this->dueDate || '' === $this->dueDate) {
            return;
        }

        try {
            new \DateTimeImmutable($this->dueDate);
        } catch (\Exception) {
            $context
                ->buildViolation('This value is not a valid datetime.')
                ->atPath('dueDate')
                ->addViolation();
        }
    }
}
