# Symfony Task API Service

REST API for a task management system built with Symfony 6.4, PHP 8.2, and MySQL.

## Features

- JWT authentication
- User registration and login
- User-scoped task CRUD
- Pagination and filtering
- DTO-based request and response mapping
- Validation and access control
- Rate limiting for auth and task APIs
- Audit-style logging for task mutations and auth failures

## Stack

- PHP 8.2+
- Symfony 6.4
- MySQL 8+
- Doctrine ORM and Migrations
- LexikJWTAuthenticationBundle

## Folder Structure

```text
symfony-task-api-service/
├── config/
├── migrations/
├── public/
├── src/
│   ├── Controller/
│   │   ├── ApiController.php
│   │   ├── AuthController.php
│   │   └── TaskController.php
│   ├── DTO/
│   │   ├── LoginRequestDTO.php
│   │   ├── RegisterRequestDTO.php
│   │   ├── TaskDTO.php
│   │   └── TaskRequestDTO.php
│   ├── Entity/
│   │   ├── Task.php
│   │   └── User.php
│   ├── EventSubscriber/
│   │   └── ApiExceptionSubscriber.php
│   ├── Exception/
│   │   ├── ApiRateLimitException.php
│   │   └── ApiValidationException.php
│   ├── Repository/
│   │   ├── TaskRepository.php
│   │   └── UserRepository.php
│   ├── Response/
│   │   └── ApiResponseFactory.php
│   ├── Security/
│   │   └── JwtAuthenticator.php
│   └── Service/
│       └── TaskService.php
├── tests/
├── .env
└── README.md
```

## Setup

1. Install dependencies:

```bash
composer install
```

2. Configure environment values in `.env.local`:

```dotenv
APP_ENV=dev
APP_SECRET=change-me
DATABASE_URL="mysql://app:password@127.0.0.1:3306/task_api?serverVersion=8.0.32&charset=utf8mb4"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change-me
```

3. Generate JWT keys:

```bash
php bin/console lexik:jwt:generate-keypair
```

4. Run migrations:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

5. Start the API:

```bash
symfony server:start
```

## Docker

This repo includes a Dockerized local stack for the API and MySQL, connected through the named Docker network `task_api_network`.

### Start the stack

```bash
docker compose up --build -d
```

### Start the stack with automatic port fallback on Windows

```powershell
./docker-up.ps1
```

The script prefers port `8000`. If `8000` is already in use, it falls back to the next free port up to `8100` and starts the stack with that host port.

### Run migrations inside the app container

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### Stop the stack

```bash
docker compose down
```

### Reset the database volume

```bash
docker compose down -v
```

### Docker notes

- The API is exposed on `http://localhost:8000` by default
- If `8000` is already in use, run `./docker-up.ps1` to auto-select the next free host port
- MySQL is exposed on host port `3307`
- The app container connects to MySQL using the service hostname `db`
- JWT keys are generated automatically in the container if they are missing
- Composer dependencies are persisted in a named Docker volume

## Example Endpoints

### Auth

- `POST /api/register`
- `POST /api/login`

### Tasks

- `GET /api/tasks?page=1&limit=10&status=todo&priority=high&search=report&sort=createdAt&direction=desc`
- `POST /api/tasks`
- `GET /api/tasks/{id}`
- `PUT /api/tasks/{id}`
- `DELETE /api/tasks/{id}`

## Example Requests

### Register

```json
{
  "email": "alice@example.com",
  "password": "Password123"
}
```

### Create Task

```json
{
  "title": "Write monthly report",
  "description": "Prepare finance summary",
  "status": "todo",
  "priority": "high",
  "dueDate": "2030-01-01T10:00:00+00:00"
}
```

## JSON Response Examples

### Login Success

```json
{
  "data": {
    "token": "jwt-token",
    "user": {
      "id": 1,
      "email": "alice@example.com",
      "roles": ["ROLE_USER"]
    }
  }
}
```

### Task List Success

```json
{
  "data": [
    {
      "id": 10,
      "title": "Write monthly report",
      "description": "Prepare finance summary",
      "status": "todo",
      "priority": "high",
      "dueDate": "2030-01-01T10:00:00+00:00",
      "createdAt": "2026-04-13T10:00:00+00:00",
      "updatedAt": "2026-04-13T10:00:00+00:00"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 10,
    "total": 1,
    "pages": 1
  }
}
```

### Validation Error

```json
{
  "message": "Validation failed.",
  "errors": {
    "title": ["This value should not be blank."]
  },
  "code": "validation_error"
}
```

## Security Notes

- JWT bearer token required for all `/api/tasks*` endpoints
- Tasks are only visible and mutable by their owner
- Invalid credentials are logged to the `audit` channel
- Task create, update, and delete actions are logged to the `audit` channel
- The Docker setup uses an internal named network so the app and database communicate by service name instead of host-local addresses

## Running Tests

```bash
php bin/phpunit
```
