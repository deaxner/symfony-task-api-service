<?php

namespace App\Tests;

use App\Tests\Support\DatabaseResetTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class AuthControllerTest extends WebTestCase
{
    use DatabaseResetTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabase($entityManager);
    }

    public function testRegisterSucceeds(): void
    {
        $this->client->request('POST', '/api/register', server: [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.11',
        ], content: json_encode([
            'email' => 'alice@example.com',
            'password' => 'Password123',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame('alice@example.com', json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']['email']);
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $payload = json_encode([
            'email' => 'alice@example.com',
            'password' => 'Password123',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.12'], content: $payload);
        $this->client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.12'], content: $payload);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $this->client->request('POST', '/api/register', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.13'], content: json_encode([
            'email' => 'alice@example.com',
            'password' => 'Password123',
        ], JSON_THROW_ON_ERROR));

        $this->client->request('POST', '/api/login', server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '127.0.0.13'], content: json_encode([
            'email' => 'alice@example.com',
            'password' => 'Password123',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['data']);
    }
}
