<?php

namespace App\Exception;

class ApiValidationException extends \RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed.',
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
