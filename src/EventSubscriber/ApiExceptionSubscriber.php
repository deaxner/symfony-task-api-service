<?php

namespace App\EventSubscriber;

use App\Exception\ApiRateLimitException;
use App\Exception\ApiValidationException;
use App\Response\ApiResponseFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ApiResponseFactory $responseFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof ApiValidationException) {
            $event->setResponse(
                $this->responseFactory->error($exception->getMessage(), $exception->getStatusCode(), $exception->getErrors(), 'validation_error')
            );

            return;
        }

        if ($exception instanceof ApiRateLimitException) {
            $headers = [];
            if (null !== $exception->getRetryAfter()) {
                $headers['Retry-After'] = (string) $exception->getRetryAfter();
            }

            $event->setResponse(
                $this->responseFactory->error($exception->getMessage(), JsonResponse::HTTP_TOO_MANY_REQUESTS, code: 'rate_limited', headers: $headers)
            );

            return;
        }

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse(
                $this->responseFactory->error(
                    $exception->getMessage() ?: JsonResponse::$statusTexts[$exception->getStatusCode()],
                    $exception->getStatusCode(),
                    code: 'http_error'
                )
            );

            return;
        }

        $this->logger->error('Unhandled API exception.', [
            'path' => $request->getPathInfo(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        $event->setResponse(
            $this->responseFactory->error('Internal server error.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR, code: 'server_error')
        );
    }
}
