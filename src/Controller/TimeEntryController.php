<?php

namespace App\Controller;

use App\DTO\TimeEntryRequestDTO;
use App\Entity\User;
use App\Response\ApiResponseFactory;
use App\Service\TimeEntryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/time-entries')]
class TimeEntryController extends ApiController
{
    public function __construct(
        ApiResponseFactory $responseFactory,
        private readonly TimeEntryService $timeEntryService,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $apiLimiter,
    ) {
        parent::__construct($responseFactory);
    }

    #[Route('', name: 'api_time_entries_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'time-entries:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        return $this->success($this->timeEntryService->listForUser($user, [
            'taskId' => $request->query->getInt('taskId') ?: null,
            'projectId' => $request->query->getInt('projectId') ?: null,
            'billable' => $request->query->has('billable') ? filter_var($request->query->get('billable'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null,
        ]));
    }

    #[Route('', name: 'api_time_entries_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'time-entries:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        return $this->success(
            $this->timeEntryService->create($user, $this->mapRequest($request))->toArray(),
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_time_entries_update', methods: ['PUT'])]
    public function update(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'time-entries:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        return $this->success(
            $this->timeEntryService->update($id, $user, $this->mapRequest($request))->toArray()
        );
    }

    #[Route('/{id}', name: 'api_time_entries_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'time-entries:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));
        $this->timeEntryService->delete($id, $user);

        return $this->success(['deleted' => true]);
    }

    private function mapRequest(Request $request): TimeEntryRequestDTO
    {
        $payload = $this->parseJson($request);
        $dto = new TimeEntryRequestDTO();
        $dto->taskId = isset($payload['taskId']) ? (int) $payload['taskId'] : null;
        $dto->startedAt = $payload['startedAt'] ?? null;
        $dto->endedAt = $payload['endedAt'] ?? null;
        $dto->billable = isset($payload['billable']) ? (bool) $payload['billable'] : true;
        $dto->notes = $payload['notes'] ?? null;
        $dto->costRateSnapshot = $payload['costRateSnapshot'] ?? null;
        $dto->billRateSnapshot = $payload['billRateSnapshot'] ?? null;

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $this->validationFailed($violations);
        }

        return $dto;
    }

    private function assertAuthenticated(?User $user): void
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }
    }
}
