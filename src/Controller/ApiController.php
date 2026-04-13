<?php

namespace App\Controller;

use App\Exception\ApiRateLimitException;
use App\Exception\ApiValidationException;
use App\Response\ApiResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class ApiController extends AbstractController
{
    public function __construct(
        private readonly ApiResponseFactory $responseFactory,
    ) {
    }

    protected function success(array $data, int $status = 200, ?array $meta = null): JsonResponse
    {
        return $this->responseFactory->success($data, $status, $meta);
    }

    protected function validationFailed(ConstraintViolationListInterface $violations): never
    {
        $errors = [];

        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath() ?: 'body';
            $errors[$field] ??= [];
            $errors[$field][] = $violation->getMessage();
        }

        throw new ApiValidationException($errors);
    }

    protected function assertRateLimit(RateLimiterFactory $factory, string $key): void
    {
        $limit = $factory->create($key)->consume(1);
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();
            throw new ApiRateLimitException(
                'Too many requests.',
                $retryAfter ? max(1, $retryAfter->getTimestamp() - time()) : null
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseJson(Request $request): array
    {
        if ('' === (string) $request->getContent()) {
            return [];
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiValidationException(['body' => ['Invalid JSON payload.']]);
        }

        return is_array($data) ? $data : [];
    }
}
