<?php

namespace App\Policies;

use App\Models\ComparisonJob;
use App\Models\User;

class ComparisonJobPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ComparisonJob $job): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Job owner
        if ($user->id === $job->user_id) {
            return true;
        }

        // Must be active member of the workspace
        return $job->workspace->members()->where('user_id', $user->id)->exists()
            || $user->id === $job->workspace->owner_id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, ComparisonJob $job): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $job->user_id) {
            return true;
        }

        return $user->isWorkspaceAdmin($job->workspace);
    }

    public function delete(User $user, ComparisonJob $job): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $job->user_id) {
            return true;
        }

        return $user->isWorkspaceAdmin($job->workspace);
    }
}
