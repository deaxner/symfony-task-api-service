<?php

namespace App\Controller;

use App\DTO\TaskRequestDTO;
use App\Entity\User;
use App\Response\ApiResponseFactory;
use App\Service\TaskService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tasks')]
class TaskController extends ApiController
{
    public function __construct(
        ApiResponseFactory $responseFactory,
        private readonly TaskService $taskService,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $apiLimiter,
    ) {
        parent::__construct($responseFactory);
    }

    #[Route('', name: 'api_tasks_index', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'tasks:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        $result = $this->taskService->listTasks($user, [
            'page' => (int) $request->query->get('page', 1),
            'limit' => (int) $request->query->get('limit', 10),
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'projectId' => $request->query->getInt('projectId') ?: null,
            'search' => $request->query->get('search'),
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'desc'),
        ]);

        return $this->success($result['data'], meta: $result['meta']);
    }

    #[Route('', name: 'api_tasks_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'tasks:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        return $this->success(
            $this->taskService->createTask($user, $this->mapTaskRequest($request))->toArray(),
            JsonResponse::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_tasks_show', methods: ['GET'])]
    public function show(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'tasks:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        return $this->success($this->taskService->getTaskForUser($id, $user)->toArray());
    }

    #[Route('/{id}', name: 'api_tasks_update', methods: ['PUT'])]
    public function update(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'tasks:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));

        return $this->success($this->taskService->updateTask($id, $user, $this->mapTaskRequest($request))->toArray());
    }

    #[Route('/{id}', name: 'api_tasks_delete', methods: ['DELETE'])]
    public function delete(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        $this->assertAuthenticated($user);
        $this->assertRateLimit($this->apiLimiter, 'tasks:' . $user->getId() . ':' . ($request->getClientIp() ?? 'unknown'));
        $this->taskService->deleteTask($id, $user);

        return $this->success(['deleted' => true]);
    }

    private function mapTaskRequest(Request $request): TaskRequestDTO
    {
        $payload = $this->parseJson($request);
        $dto = new TaskRequestDTO();
        $dto->title = $payload['title'] ?? null;
        $dto->description = $payload['description'] ?? null;
        $dto->status = $payload['status'] ?? null;
        $dto->priority = $payload['priority'] ?? null;
        $dto->projectId = isset($payload['projectId']) ? (int) $payload['projectId'] : null;
        $dto->dueDate = $payload['dueDate'] ?? null;
        $dto->startedAt = $payload['startedAt'] ?? null;
        $dto->completedAt = $payload['completedAt'] ?? null;

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
