<?php

namespace App\Controller;

use App\DTO\LoginRequestDTO;
use App\DTO\RegisterRequestDTO;
use App\Entity\User;
use App\Exception\ApiValidationException;
use App\Repository\UserRepository;
use App\Response\ApiResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends ApiController
{
    public function __construct(
        ApiResponseFactory $responseFactory,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly RateLimiterFactory $authLimiter,
        private readonly LoggerInterface $auditLogger,
    ) {
        parent::__construct($responseFactory);
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $this->assertRateLimit($this->authLimiter, 'register:' . ($request->getClientIp() ?? 'unknown'));

        $payload = $this->parseJson($request);
        $dto = new RegisterRequestDTO();
        $dto->email = $payload['email'] ?? null;
        $dto->password = $payload['password'] ?? null;

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $this->validationFailed($violations);
        }

        if ($this->userRepository->findOneBy(['email' => mb_strtolower((string) $dto->email)])) {
            throw new ApiValidationException(['email' => ['An account with this email already exists.']]);
        }

        $user = (new User())
            ->setEmail((string) $dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $dto->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->success([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->assertRateLimit($this->authLimiter, 'login:' . ($request->getClientIp() ?? 'unknown'));

        $payload = $this->parseJson($request);
        $dto = new LoginRequestDTO();
        $dto->email = $payload['email'] ?? null;
        $dto->password = $payload['password'] ?? null;

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $this->validationFailed($violations);
        }

        $user = $this->userRepository->findOneBy(['email' => mb_strtolower((string) $dto->email)]);
        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, (string) $dto->password)) {
            $this->auditLogger->warning('Authentication failed.', [
                'email' => $dto->email,
                'ip' => $request->getClientIp(),
            ]);

            return $this->json([
                'message' => 'Invalid credentials.',
                'code' => 'invalid_credentials',
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        return $this->success([
            'token' => $this->jwtTokenManager->create($user),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }
}
