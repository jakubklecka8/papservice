<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;

trait ProjectAccess
{
    /** Projekty, které uživatel smí číst (null = všechny) @return list<int>|null */
    protected function readableProjects(User $user): ?array
    {
        if ($user->canReadAllProjects()) {
            return null;
        }

        return $user->dispatcherProjectIds();
    }

    protected function canReadProject(User $user, int $projectId): bool
    {
        $ids = $this->readableProjects($user);

        return $ids === null || in_array($projectId, $ids, true);
    }

    protected function canManageProject(User $user, int $projectId): bool
    {
        return $user->isAdmin() || in_array($projectId, $user->dispatcherProjectIds(), true);
    }

    protected function scopeProjects($query, User $user, string $column = 'project_id')
    {
        $ids = $this->readableProjects($user);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }
}
