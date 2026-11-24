<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->id === $workspace->owner_id) {
            return true;
        }

        return $workspace->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isWorkspaceAdmin($workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->id === $workspace->owner_id;
    }
}
