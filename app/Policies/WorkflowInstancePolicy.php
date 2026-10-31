<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\AccessPolicyService;

class WorkflowInstancePolicy
{
    public function __construct(private AccessPolicyService $access) {}

    public function create(User $user): bool
    {
        return $this->access->can($user, 'workflow_instances', 'start');
    }

    public function view(User $user, WorkflowInstance $instance): bool
    {
        return $this->owns($user, $instance) && $this->access->can($user, 'workflow_instances', 'view');
    }

    public function transition(User $user, WorkflowInstance $instance): bool
    {
        return $this->owns($user, $instance) && $this->access->can($user, 'workflow_instances', 'transition');
    }

    private function owns(User $user, WorkflowInstance $instance): bool
    {
        return $user->hasRole('superadmin') || ($user->organization_id !== null && $instance->organization_id !== null && (int) $user->organization_id === (int) $instance->organization_id);
    }
}
