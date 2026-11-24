<?php

namespace App\Policies;

use App\Models\ComparisonReport;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, ComparisonReport $report): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $job = $report->comparisonJob;

        if ($user->id === $job->user_id) {
            return true;
        }

        return $job->workspace->members()->where('user_id', $user->id)->exists()
            || $user->id === $job->workspace->owner_id;
    }

    public function export(User $user, ComparisonReport $report): bool
    {
        return $this->view($user, $report);
    }
}
