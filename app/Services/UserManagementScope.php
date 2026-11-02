<?php

namespace App\Services;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;

class UserManagementScope
{
    public function canManageUsers(User $actor): bool
    {
        if (! $actor->hasRole('boss') || $actor->organization_id === null || $actor->zone_id === null) {
            return false;
        }

        return $this->managedGroupIds($actor) !== []
            && Zone::query()->whereKey($actor->zone_id)
                ->where('organization_id', $actor->organization_id)->exists();
    }

    public function managedGroupIds(User $actor): array
    {
        return $actor->hasRole('boss') ? $actor->managedGroupIds() : [];
    }

    public function usersQuery(User $actor): Builder
    {
        $query = User::query();
        if (! $actor->hasRole('boss')) {
            return $query;
        }

        return $query->where('id', '!=', $actor->id)
            ->where('organization_id', $actor->organization_id)
            ->where('zone_id', $actor->zone_id)
            ->whereIn('group_user_id', $this->managedGroupIds($actor))
            ->whereDoesntHave('roles', fn ($roles) => $roles
                ->where('guard_name', 'web')
                ->whereIn('name', ['boss', 'superadmin']));
    }

    public function contains(User $actor, User $target): bool
    {
        return $actor->hasRole('boss')
            && (int) $actor->id !== (int) $target->id
            && $this->usersQuery($actor)->whereKey($target->id)->exists();
    }
}
