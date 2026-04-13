<?php

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponseFactory
{
    public function success(array $data, int $status = JsonResponse::HTTP_OK, ?array $meta = null): JsonResponse
    {
        $payload = ['data' => $data];

        if (null !== $meta) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status);
    }

    public function error(string $message, int $status, array $errors = [], ?string $code = null, array $headers = []): JsonResponse
    {
        $payload = ['message' => $message];

        if ([] !== $errors) {
            $payload['errors'] = $errors;
        }

        if (null !== $code) {
            $payload['code'] = $code;
        }

        return new JsonResponse($payload, $status, $headers);
    }
}
