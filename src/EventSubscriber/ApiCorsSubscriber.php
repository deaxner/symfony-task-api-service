<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiCorsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $frontendOrigin,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isApiRequest($request)) {
            return;
        }

        if (Request::METHOD_OPTIONS !== $request->getMethod()) {
            return;
        }

        $response = new Response();
        $this->applyCorsHeaders($request, $response);
        $response->setStatusCode(Response::HTTP_NO_CONTENT);
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->isApiRequest($request)) {
            return;
        }

        $this->applyCorsHeaders($request, $event->getResponse());
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api');
    }

    private function applyCorsHeaders(Request $request, Response $response): void
    {
        $origin = $request->headers->get('Origin');
        if (!$origin) {
            return;
        }

        $allowedOrigins = array_filter(array_map('trim', explode(',', $this->frontendOrigin)));
        if ([] === $allowedOrigins) {
            $allowedOrigins = ['*'];
        }

        if (!in_array('*', $allowedOrigins, true) && !in_array($origin, $allowedOrigins, true)) {
            return;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Max-Age', '3600');
    }
}
