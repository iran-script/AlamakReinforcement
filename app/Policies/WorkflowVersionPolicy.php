<?php

namespace App\Policies;

use App\Enums\WorkflowVersionStatus;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Services\AccessPolicyService;

class WorkflowVersionPolicy
{
    public function __construct(private AccessPolicyService $access) {}

    public function view(User $user, WorkflowVersion $version): bool
    {
        return ($version->definition->organization_id === null || $this->owns($user, $version)) && $this->access->can($user, 'workflow_definitions', 'view');
    }

    public function update(User $user, WorkflowVersion $version): bool
    {
        return $version->status === WorkflowVersionStatus::Draft && $this->owns($user, $version) && $this->access->can($user, 'workflow_definitions', 'configure');
    }

    public function publish(User $user, WorkflowVersion $version): bool
    {
        return $this->owns($user, $version) && $this->access->can($user, 'workflow_definitions', 'publish');
    }

    private function owns(User $user, WorkflowVersion $version): bool
    {
        $organizationId = $version->definition->organization_id;

        return $user->hasRole('superadmin') || ($user->organization_id !== null && $organizationId !== null && (int) $user->organization_id === (int) $organizationId);
    }
}
