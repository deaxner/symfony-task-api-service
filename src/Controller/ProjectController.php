<?php

namespace App\Controller;

use App\Entity\User;
use App\Response\ApiResponseFactory;
use App\Service\ProjectService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/projects')]
class ProjectController extends ApiController
{
    public function __construct(
        ApiResponseFactory $responseFactory,
        private readonly ProjectService $projectService,
    ) {
        parent::__construct($responseFactory);
    }

    #[Route('', name: 'api_projects_index', methods: ['GET'])]
    public function index(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $this->success($this->projectService->listProjects($user));
    }
}
