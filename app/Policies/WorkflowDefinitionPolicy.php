<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AccessPolicyService;

class WorkflowDefinitionPolicy
{
    public function __construct(private AccessPolicyService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'workflow_definitions', 'view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'workflow_definitions', 'configure');
    }

    public function view(User $user, WorkflowDefinition $definition): bool
    {
        return ($definition->organization_id === null || $this->owns($user, $definition)) && $this->access->can($user, 'workflow_definitions', 'view');
    }

    public function update(User $user, WorkflowDefinition $definition): bool
    {
        return $this->owns($user, $definition) && $this->access->can($user, 'workflow_definitions', 'configure');
    }

    public function publish(User $user, WorkflowDefinition $definition): bool
    {
        return $this->owns($user, $definition) && $this->access->can($user, 'workflow_definitions', 'publish');
    }

    private function owns(User $user, WorkflowDefinition $definition): bool
    {
        return $user->hasRole('superadmin') || ($user->organization_id !== null && $definition->organization_id !== null && (int) $user->organization_id === (int) $definition->organization_id);
    }
}
