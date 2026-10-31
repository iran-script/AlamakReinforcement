<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowTask;
use App\Services\AccessPolicyService;

class WorkflowTaskPolicy
{
    public function __construct(private AccessPolicyService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'workflow_tasks', 'view');
    }

    public function view(User $user, WorkflowTask $task): bool
    {
        return $this->owns($user, $task) && $this->access->can($user, 'workflow_tasks', 'view');
    }

    public function complete(User $user, WorkflowTask $task): bool
    {
        return $this->owns($user, $task) && $this->access->can($user, 'workflow_tasks', 'transition');
    }

    private function owns(User $user, WorkflowTask $task): bool
    {
        return $user->hasRole('superadmin') || ($user->organization_id !== null && $task->organization_id !== null && (int) $user->organization_id === (int) $task->organization_id);
    }
}
