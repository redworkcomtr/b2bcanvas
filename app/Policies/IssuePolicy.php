<?php

namespace App\Policies;

use App\Models\User;

class IssuePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_issues');
    }
}
