<?php

namespace App\Policies;

use App\Models\Operation;
use App\Models\Riser;
use App\Models\User;

class OperationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('operation.view')
            || ($user->hasRole('boss') && $user->zone_id !== null && $user->managedGroupIds() !== []);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin') || $user->can('operation.create');
    }

    public function view(User $user, Operation $operation): bool
    {
        return $this->visible($user, $operation) && ($user->hasRole('superadmin') || $user->can('operation.view') || $user->hasRole('boss'));
    }

    public function update(User $user, Operation $operation): bool
    {
        if ($operation->supervisors()->wherePivot('status', 'approved')->exists()) {
            return false;
        }

        return $this->visible($user, $operation) && ($user->hasRole('superadmin') || $user->can('operation.edit') || $user->hasRole('boss'));
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

        if (! ($user->hasRole(['superadmin', 'supervisor', 'boss']) || $user->can('operation.approve'))) {
            return false;
        }

        return $operation->supervisors()
            ->whereKey($user->id)
            ->wherePivot('status', 'pending')
            ->exists();
    }

    private function visible(User $user, Operation $operation): bool
    {
        if (! Riser::query()->visibleTo($user)->whereKey($operation->riser_id)->exists()) {
            return false;
        }

        if ($user->hasRole('boss')) {
            $managedGroupIds = $user->managedGroupIds();
            if ($managedGroupIds === []) return false;
            $operationGroupId = $operation->group_user_id
                ?? $operation->user()->value('group_user_id');
            return in_array((int) $operationGroupId, $managedGroupIds, true);
        }

        return true;
    }
}
