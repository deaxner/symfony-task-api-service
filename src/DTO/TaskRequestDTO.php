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

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $projectId = null;

    public ?string $dueDate = null;
    public ?string $startedAt = null;
    public ?string $completedAt = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $parsedDates = [];

        foreach (['dueDate', 'startedAt', 'completedAt'] as $field) {
            $value = $this->{$field};
            if (null === $value || '' === $value) {
                continue;
            }

            try {
                $parsedDates[$field] = new \DateTimeImmutable($value);
            } catch (\Exception) {
                $context
                    ->buildViolation('This value is not a valid datetime.')
                    ->atPath($field)
                    ->addViolation();
            }
        }

        if (
            isset($parsedDates['startedAt'], $parsedDates['completedAt']) &&
            $parsedDates['completedAt'] < $parsedDates['startedAt']
        ) {
            $context
                ->buildViolation('Completed date must be after the start date.')
                ->atPath('completedAt')
                ->addViolation();
        }
    }
}
