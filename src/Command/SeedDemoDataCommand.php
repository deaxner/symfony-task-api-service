<?php

namespace App\Command;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TimeEntry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-demo-data', description: 'Seed 2 demo users, projects, and four years of task/worklog history.')]
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
        $connection->executeStatement($platform->getTruncateTableSQL('time_entry', true));
        $connection->executeStatement($platform->getTruncateTableSQL('task', true));
        $connection->executeStatement($platform->getTruncateTableSQL('project', true));
        $connection->executeStatement($platform->getTruncateTableSQL('user', true));
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $users = [
            $this->createUser('alex@example.com', 'Password123'),
            $this->createUser('jamie@example.com', 'Password123'),
        ];

        $timelineStart = (new \DateTimeImmutable('first day of this month midnight'))->modify('-48 months');
        $today = new \DateTimeImmutable('today');

        $projectConfigs = [
            ['Signal cockpit', '#57b6ff', 'Platform delivery and release planning.', 'PRJ-01'],
            ['Revenue stream', '#45d483', 'Billing operations, revenue reporting, and customer workflows.', 'PRJ-02'],
            ['Support grid', '#f4b65f', 'Operational support, QA, and triage work.', 'PRJ-03'],
            ['Mobile ops', '#ff6b6b', 'Mobile and product experience updates across clients.', 'PRJ-04'],
            ['Client lane', '#b072ff', 'Client-facing coordination and SLA delivery.', 'PRJ-05'],
            ['Ledger pulse', '#21c8a7', 'Financial ops and invoice-adjacent workflow updates.', 'PRJ-06'],
            ['Growth board', '#4cb1ff', 'Growth experiments, landing pages, and activation work.', 'PRJ-07'],
            ['Care desk', '#5dd39e', 'Service quality, healthcare integrations, and support continuity.', 'PRJ-08'],
            ['Commerce engine', '#ffb347', 'Checkout, order processing, and merchandising updates.', 'PRJ-09'],
            ['Transit hub', '#ff7f96', 'Planning, capacity tooling, and rider-facing release work.', 'PRJ-10'],
            ['Studio pipeline', '#8c7bff', 'Creative workflow automation and collaboration features.', 'PRJ-11'],
            ['Ops console', '#2fd3c4', 'Internal platform tooling, automation, and release readiness.', 'PRJ-12'],
        ];
        $projects = [];

        foreach ($users as $userIndex => $user) {
            foreach (array_slice($projectConfigs, $userIndex * 6, 6) as [$name, $color, $description, $externalProjectKey]) {
                $project = (new Project())
                    ->setUser($user)
                    ->setName($name)
                    ->setColor($color)
                    ->setDescription($description)
                    ->setExternalProjectKey($externalProjectKey);

                $this->entityManager->persist($project);
                $projects[] = $project;
            }
        }

        $taskTitlePatterns = [
            'Plan %s sprint review',
            'Ship %s workflow update',
            'Investigate %s production issue',
            'Coordinate %s stakeholder follow-up',
            'Refine %s analytics view',
            'Document %s rollout checklist',
            'Handle %s support escalation',
            'Prepare %s release notes',
            'Stabilize %s integration edge case',
            'Review %s backlog priorities',
        ];
        $taskDescriptionPatterns = [
            'Seeded work item representing a realistic delivery ticket from the live operating history.',
            'Long-running demo task used to create believable trend lines for analytics and board views.',
            'Operational delivery record generated for project, lead-time, and profitability reporting.',
        ];

        $taskCount = 0;
        $timeEntryCount = 0;

        foreach ($projects as $projectIndex => $project) {
            $projectStart = $timelineStart->modify(sprintf('+%d months', $projectIndex));
            $monthCursor = $projectStart;
            $owner = $project->getUser();
            $projectLabel = strtolower($project->getName());

            while ($monthCursor <= $today) {
                $monthAge = $today->diff($monthCursor)->m + ($today->diff($monthCursor)->y * 12);
                $tasksThisMonth = 1 + (($projectIndex + (int) $monthCursor->format('n')) % 3);
                if ($monthAge < 6) {
                    ++$tasksThisMonth;
                }

                for ($slot = 0; $slot < $tasksThisMonth; $slot += 1) {
                    $createdAt = $monthCursor
                        ->modify(sprintf('+%d days', 2 + (($slot * 6 + $projectIndex) % 20)))
                        ->setTime(9 + (($slot + $projectIndex) % 6), 15 * (($slot + $projectIndex) % 4));

                    if ($createdAt > $today) {
                        continue;
                    }

                    $priority = Task::PRIORITIES[($taskCount + $projectIndex + $slot) % count(Task::PRIORITIES)];
                    $status = $this->resolveTaskStatus($createdAt, $today, $projectIndex, $slot);
                    $startedAt = in_array($status, ['in_progress', 'done'], true)
                        ? $createdAt->modify(sprintf('+%d days +%d hours', 1 + (($taskCount + $slot) % 4), 2 + (($projectIndex + $slot) % 5)))
                        : null;
                    $completedAt = 'done' === $status && $startedAt instanceof \DateTimeImmutable
                        ? $startedAt->modify(sprintf('+%d days +%d hours', 1 + (($taskCount + $projectIndex) % 8), 3 + (($slot + $projectIndex) % 6)))
                        : null;

                    $task = (new Task())
                        ->setUser($owner)
                        ->setProject($project)
                        ->setTitle(sprintf($taskTitlePatterns[($taskCount + $slot) % count($taskTitlePatterns)], $projectLabel))
                        ->setDescription($taskDescriptionPatterns[($taskCount + $projectIndex) % count($taskDescriptionPatterns)])
                        ->setStatus($status)
                        ->setPriority($priority)
                        ->setStartedAt($startedAt)
                        ->setCompletedAt($completedAt)
                        ->setCreatedAt($createdAt)
                        ->setUpdatedAt(($completedAt ?? $startedAt ?? $createdAt)->modify(sprintf('+%d hours', 1 + (($taskCount + $slot) % 12))))
                        ->setDueDate(
                            'done' === $status
                                ? null
                                : $createdAt->modify(sprintf('+%d days', 5 + (($projectIndex + $slot) % 18)))
                        );

                    $this->entityManager->persist($task);
                    ++$taskCount;

                    if (null !== $startedAt) {
                        $logsForTask = 'done' === $status
                            ? 1 + (($taskCount + $projectIndex) % 3)
                            : 1 + (($slot + $projectIndex) % 2);

                        for ($logIndex = 0; $logIndex < $logsForTask; $logIndex += 1) {
                            $entryStart = $startedAt->modify(sprintf('+%d days +%d hours', $logIndex, ($logIndex * 2 + $slot) % 6));
                            $durationMinutes = $this->resolveEntryDurationMinutes($priority, $status, $projectIndex, $slot, $logIndex);
                            $entryEnd = $entryStart->modify(sprintf('+%d minutes', $durationMinutes));

                            if ($completedAt instanceof \DateTimeImmutable && $entryEnd > $completedAt) {
                                $entryEnd = $completedAt;
                            }

                            if ($entryEnd <= $entryStart) {
                                $entryEnd = $entryStart->modify('+45 minutes');
                            }

                            $timeEntry = (new TimeEntry())
                                ->setUser($owner)
                                ->setTask($task)
                                ->setProject($project)
                                ->setStartedAt($entryStart)
                                ->setEndedAt($entryEnd)
                                ->setBillable((($taskCount + $logIndex + $projectIndex) % 6) !== 0)
                                ->setNotes($this->buildWorklogNote($status, $project->getName()))
                                ->setCostRateSnapshot(number_format(36 + (($projectIndex * 3 + $logIndex) % 18), 2, '.', ''))
                                ->setBillRateSnapshot(number_format(88 + (($projectIndex * 7 + $logIndex * 4) % 52), 2, '.', ''));

                            $this->entityManager->persist($timeEntry);
                            ++$timeEntryCount;
                        }
                    }
                }

                $monthCursor = $monthCursor->modify('+1 month');
            }
        }

        $this->entityManager->flush();

        $io->success([
            sprintf('Seeded 2 demo users, %d projects, %d tasks, and %d time entries spanning the last four years.', count($projects), $taskCount, $timeEntryCount),
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

    private function resolveTaskStatus(\DateTimeImmutable $createdAt, \DateTimeImmutable $today, int $projectIndex, int $slot): string
    {
        $ageInDays = (int) floor(($today->getTimestamp() - $createdAt->getTimestamp()) / 86400);

        if ($ageInDays > 30) {
            return (($projectIndex + $slot + $ageInDays) % 6 === 0) ? 'in_progress' : 'done';
        }

        if ($ageInDays > 7) {
            return (($projectIndex + $slot + $ageInDays) % 3 === 0) ? 'todo' : 'in_progress';
        }

        return (($projectIndex + $slot + $ageInDays) % 2 === 0) ? 'todo' : 'in_progress';
    }

    private function resolveEntryDurationMinutes(string $priority, string $status, int $projectIndex, int $slot, int $logIndex): int
    {
        $base = match ($priority) {
            'high' => 180,
            'medium' => 120,
            default => 75,
        };

        if ('done' === $status) {
            $base += 30;
        }

        return $base + (($projectIndex + $slot + $logIndex) % 5) * 30;
    }

    private function buildWorklogNote(string $status, string $projectName): string
    {
        return match ($status) {
            'done' => sprintf('Completed seeded delivery slice for %s and captured the final billable worklog.', $projectName),
            'in_progress' => sprintf('Active seeded worklog for %s to simulate ongoing delivery and support effort.', $projectName),
            default => sprintf('Seeded planning log for %s.', $projectName),
        };
    }
}
