<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zone;

class ZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('superadmin')
            || ($user->can('zone.view') && ($user->hasRole('boss') || $user->organization_id !== null));
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin')
            || (! $user->hasRole('boss') && $user->can('zone.create') && $user->organization_id !== null);
    }

    public function update(User $user, Zone $zone): bool
    {
        return ! $user->hasRole('boss')
            && $this->owns($user, $zone)
            && ($user->hasRole('superadmin') || $user->can('zone.edit'));
    }

    public function delete(User $user, Zone $zone): bool
    {
        return ! $user->hasRole('boss')
            && $this->owns($user, $zone)
            && ($user->hasRole('superadmin') || $user->can('zone.delete'));
    }

    private function owns(User $user, Zone $zone): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($user->hasRole('boss')) {
            return (int) $zone->creator_id === (int) $user->id;
        }

        return $user->organization_id !== null
            && $zone->organization_id !== null
            && (int) $user->organization_id === (int) $zone->organization_id;
    }
}
