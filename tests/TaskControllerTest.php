<?php

namespace App\Tests;

use App\Entity\Task;
use App\Entity\User;
use App\Tests\Support\DatabaseResetTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TaskControllerTest extends WebTestCase
{
    use DatabaseResetTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->resetDatabase($this->entityManager);
    }

    public function testUnauthenticatedTaskAccessIsRejected(): void
    {
        $this->client->request('GET', '/api/tasks');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testTaskCrudAndFilteringAreUserScoped(): void
    {
        $token = $this->createUserAndLogin('owner@example.com');
        $otherUser = $this->createUser('other@example.com');

        $otherTask = (new Task())
            ->setUser($otherUser)
            ->setTitle('Hidden task')
            ->setDescription('Should not be visible')
            ->setStatus('todo')
            ->setPriority('low');
        $this->entityManager->persist($otherTask);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/tasks',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'title' => 'Write report',
                'description' => 'Monthly reporting',
                'status' => 'todo',
                'priority' => 'high',
                'dueDate' => '2030-01-01T10:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $taskId = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']['id'];

        $this->client->request('GET', '/api/tasks?status=todo&priority=high&search=report', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $payload['data']);
        self::assertSame(1, $payload['meta']['total']);

        $this->client->request(
            'PUT',
            '/api/tasks/' . $taskId,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'title' => 'Write annual report',
                'description' => 'Updated details',
                'status' => 'in_progress',
                'priority' => 'medium',
                'dueDate' => '2030-02-01T10:00:00+00:00',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        self::assertSame('in_progress', json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']['status']);

        $this->client->request('GET', '/api/tasks/' . $otherTask->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $this->client->request('DELETE', '/api/tasks/' . $taskId, server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testInvalidTaskPayloadIsRejected(): void
    {
        $token = $this->createUserAndLogin('owner@example.com');

        $this->client->request(
            'POST',
            '/api/tasks',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'title' => '',
                'description' => 'Bad payload',
                'status' => 'invalid',
                'priority' => 'urgent',
                'dueDate' => 'not-a-date',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
    }

    private function createUserAndLogin(string $email): string
    {
        $octet = (string) ((crc32($email) % 200) + 20);
        $server = ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.' . $octet];

        $this->client->request('POST', '/api/register', server: $server, content: json_encode([
            'email' => $email,
            'password' => 'Password123',
        ], JSON_THROW_ON_ERROR));

        $this->client->request('POST', '/api/login', server: $server, content: json_encode([
            'email' => $email,
            'password' => 'Password123',
        ], JSON_THROW_ON_ERROR));

        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']['token'];
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Password123'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
