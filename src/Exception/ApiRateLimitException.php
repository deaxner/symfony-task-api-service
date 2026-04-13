<?php

namespace App\Exception;

class ApiRateLimitException extends \RuntimeException
{
    public function __construct(
        string $message = 'Rate limit exceeded.',
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, 429);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
