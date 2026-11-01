<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class UserPolicy
{
    public const BOSS_ASSIGNABLE_ROLES = ['supervisor'];

    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('superadmin')
            || ($actor->hasRole('boss') && $actor->managedGroupIds() !== [] && $actor->can('user.view'));
    }

    public function create(User $actor): bool
    {
        if ($actor->hasRole('superadmin')) {
            return true;
        }

        $zones = Zone::query()->whereKey($actor->zone_id)->where('organization_id', $actor->organization_id);

        return $actor->hasRole('boss')
            && $actor->managedGroupIds() !== []
            && $actor->can('user.create')
            && $zones->exists();
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->hasRole('superadmin')) {
            return true;
        }

        $targetRoles = $target->roles()->where('guard_name', 'web')->pluck('name')->all();

        if (! $actor->hasRole('boss')
            || ! $actor->can('user.edit')
            || (int) $actor->id === (int) $target->id
            || ! in_array((int) $target->group_user_id, $actor->managedGroupIds(), true)
            || (int) $actor->organization_id !== (int) $target->organization_id
            || $target->hasAnyRole(['boss', 'superadmin'])
            || count($targetRoles) !== 1
            || ! in_array($targetRoles[0], self::BOSS_ASSIGNABLE_ROLES, true)) {
            return false;
        }

        return $target->zone_id !== null
            && (int) $target->zone_id === (int) $actor->zone_id
            && Zone::query()->where('organization_id', $actor->organization_id)->whereKey($target->zone_id)->exists();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->hasRole('superadmin') && (int) $actor->id !== (int) $target->id;
    }
}
