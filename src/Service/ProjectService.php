<?php

namespace App\Service;

use App\DTO\ProjectDTO;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TimeEntryRepository $timeEntryRepository,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProjects(User $user): array
    {
        $projectMinutes = [];
        foreach ($this->timeEntryRepository->summarizeByProject($user) as $row) {
            $projectMinutes[(int) $row['projectId']] = [
                'totalLoggedMinutes' => (int) $row['totalMinutes'],
                'billableMinutes' => (int) $row['billableMinutes'],
            ];
        }

        return array_map(
            function ($project) use ($projectMinutes): array {
                $taskCount = count($project->getTasks());
                $doneTasks = count(array_filter(
                    $project->getTasks()->toArray(),
                    static fn (Task $task): bool => 'done' === $task->getStatus()
                ));
                $leadHours = [];
                $cycleHours = [];
                foreach ($project->getTasks() as $task) {
                    if (null !== $task->getCompletedAt()) {
                        $leadHours[] = ($task->getCompletedAt()->getTimestamp() - ($task->getCreatedAt()?->getTimestamp() ?? 0)) / 3600;
                    }

                    if (null !== $task->getStartedAt() && null !== $task->getCompletedAt()) {
                        $cycleHours[] = ($task->getCompletedAt()->getTimestamp() - $task->getStartedAt()->getTimestamp()) / 3600;
                    }
                }

                return array_merge(
                    ProjectDTO::fromEntity($project)->toArray(),
                    [
                        'taskCount' => $taskCount,
                        'doneTasks' => $doneTasks,
                        'totalLoggedMinutes' => $projectMinutes[$project->getId() ?? 0]['totalLoggedMinutes'] ?? 0,
                        'billableMinutes' => $projectMinutes[$project->getId() ?? 0]['billableMinutes'] ?? 0,
                        'averageLeadHours' => $this->average($leadHours),
                        'averageCycleHours' => $this->average($cycleHours),
                    ],
                );
            },
            $this->projectRepository->findByUser($user)
        );
    }

    /** @param list<float|int> $values */
    private function average(array $values): float
    {
        if ([] === $values) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }
}
