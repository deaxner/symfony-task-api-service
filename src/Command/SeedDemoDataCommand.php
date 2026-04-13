<?php

namespace App\Command;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-demo-data', description: 'Seed 2 demo users and 100 demo tasks.')]
class SeedDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $connection->executeStatement($platform->getTruncateTableSQL('task', true));
        $connection->executeStatement($platform->getTruncateTableSQL('user', true));
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $users = [
            $this->createUser('alex@example.com', 'Password123'),
            $this->createUser('jamie@example.com', 'Password123'),
        ];

        $titles = [
            'Review sprint scope',
            'Draft client summary',
            'Prepare release notes',
            'Audit onboarding flow',
            'Update test checklist',
            'Refine analytics query',
            'Check deployment logs',
            'Triage support backlog',
            'Map API dependency',
            'Clean up dashboard copy',
        ];
        $descriptions = [
            'Focused work item generated for the demo dashboard dataset.',
            'Cross-team follow-up used to produce realistic reporting volume.',
            'Operational task seeded for charts, cards, and recent activity.',
            'Short-lived planning item to create a varied delivery timeline.',
        ];

        for ($index = 0; $index < 100; $index += 1) {
            $owner = $users[$index % 2];
            $createdAt = new \DateTimeImmutable(sprintf('-%d days', random_int(0, 89)));
            $dueOffset = random_int(-12, 20);
            $status = Task::STATUSES[$index % count(Task::STATUSES)];
            $priority = Task::PRIORITIES[array_rand(Task::PRIORITIES)];

            $task = (new Task())
                ->setUser($owner)
                ->setTitle(sprintf('%s #%d', $titles[$index % count($titles)], $index + 1))
                ->setDescription($descriptions[$index % count($descriptions)])
                ->setStatus($status)
                ->setPriority($priority)
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt->modify(sprintf('+%d hours', random_int(1, 72))))
                ->setDueDate('done' === $status ? null : $createdAt->modify(sprintf('%+d days', $dueOffset)));

            $this->entityManager->persist($task);
        }

        $this->entityManager->flush();

        $io->success([
            'Seeded 2 demo users and 100 tasks.',
            'alex@example.com / Password123',
            'jamie@example.com / Password123',
        ]);

        return Command::SUCCESS;
    }

    private function createUser(string $email, string $plainPassword): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);

        return $user;
    }
}
