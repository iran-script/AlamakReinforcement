<?php

namespace App\Policies;

use App\Models\Operation;
use App\Models\Riser;
use App\Models\User;

class OperationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('operation.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('operation.create');
    }

    public function view(User $user, Operation $operation): bool
    {
        return $this->visible($user, $operation) && ($user->hasRole('superadmin') || $user->can('operation.view'));
    }

    public function update(User $user, Operation $operation): bool
    {
        if ($operation->supervisors()->wherePivot('status', 'approved')->exists()) {
            return false;
        }

        return $this->visible($user, $operation) && ($user->hasRole('superadmin') || $user->can('operation.edit'));
    }

    public function delete(User $user, Operation $operation): bool
    {
        return $this->visible($user, $operation) && ($user->hasRole('superadmin') || $user->can('operation.delete'));
    }

    public function approve(User $user, Operation $operation): bool
    {
        return $this->canReview($user, $operation);
    }

    public function returnForRevision(User $user, Operation $operation): bool
    {
        return $this->canReview($user, $operation);
    }

    private function canReview(User $user, Operation $operation): bool
    {
        if (! $this->visible($user, $operation)) {
            return false;
        }

        if (! ($user->hasRole(['superadmin', 'supervisor']) || $user->can('operation.approve'))) {
            return false;
        }

        return $operation->supervisors()
            ->whereKey($user->id)
            ->wherePivot('status', 'pending')
            ->exists();
    }

    private function visible(User $user, Operation $operation): bool
    {
        return Riser::query()->visibleTo($user)->whereKey($operation->riser_id)->exists();
    }
}
