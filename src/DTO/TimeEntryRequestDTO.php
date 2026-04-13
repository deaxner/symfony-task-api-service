<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TimeEntryRequestDTO
{
    #[Assert\Positive]
    public ?int $taskId = null;

    #[Assert\NotBlank]
    public ?string $startedAt = null;

    public ?string $endedAt = null;

    public bool $billable = true;

    #[Assert\Length(max: 5000)]
    public ?string $notes = null;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Cost rate snapshot must be a valid decimal value.')]
    public ?string $costRateSnapshot = null;

    #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Bill rate snapshot must be a valid decimal value.')]
    public ?string $billRateSnapshot = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $startedAt = null;
        $endedAt = null;

        try {
            $startedAt = null === $this->startedAt ? null : new \DateTimeImmutable($this->startedAt);
        } catch (\Exception) {
            $context->buildViolation('This value is not a valid datetime.')
                ->atPath('startedAt')
                ->addViolation();
        }

        if (null !== $this->endedAt && '' !== $this->endedAt) {
            try {
                $endedAt = new \DateTimeImmutable($this->endedAt);
            } catch (\Exception) {
                $context->buildViolation('This value is not a valid datetime.')
                    ->atPath('endedAt')
                    ->addViolation();
            }
        }

        if ($startedAt instanceof \DateTimeImmutable && $endedAt instanceof \DateTimeImmutable && $endedAt < $startedAt) {
            $context->buildViolation('Ended time must be after the started time.')
                ->atPath('endedAt')
                ->addViolation();
        }
    }
}
