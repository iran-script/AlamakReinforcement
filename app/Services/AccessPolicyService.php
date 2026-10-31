<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\DataAccessPolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessPolicyService
{
    /** @return Collection<int, DataAccessPolicy> */
    public function policiesFor(User $user, string $resourceKey): Collection
    {
        $roleIds = $user->roles()->pluck('roles.id');

        return DataAccessPolicy::query()
            ->where('resource_key', $resourceKey)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->orWhere('organization_id', $user->organization_id))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('principal_type', 'user')->where('principal_id', $user->id))
                ->orWhere(fn (Builder $query) => $query->where('principal_type', 'role')->whereIn('principal_id', $roleIds)))
            ->orderBy('priority')
            ->get();
    }

    public function can(User $user, string $resourceKey, string $action): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        $policies = $this->policiesFor($user, $resourceKey)
            ->filter(fn (DataAccessPolicy $policy) => in_array($action, $policy->actions ?? [], true));

        return $policies->contains(fn (DataAccessPolicy $policy) => $policy->effect === 'allow')
            && ! $policies->contains(fn (DataAccessPolicy $policy) => $policy->effect === 'deny');
    }

    public function fieldAccessState(User $user, string $resourceKey, string $fieldKey, string $default = 'edit'): string
    {
        if ($user->hasRole('superadmin')) {
            return $default;
        }
        $state = $default;
        foreach ($this->policiesFor($user, $resourceKey) as $policy) {
            $rules = $policy->field_rules ?? [];
            $candidate = $rules[$fieldKey] ?? $rules['*'] ?? null;
            if (in_array($candidate, ['edit', 'readonly', 'hidden'], true)) {
                $state = $candidate;
            }
        }

        return $state;
    }

    public function visibleAssets(User $user): Builder
    {
        $query = Asset::query()->where(fn (Builder $query) => $query
            ->whereNull('organization_id')
            ->orWhere('organization_id', $user->organization_id));

        if ($user->hasRole('superadmin')) {
            return $query;
        }

        $scopeIds = $user->spatialScopes()->pluck('spatial_scopes.id');
        if ($scopeIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereExists(function ($scopeQuery) use ($scopeIds) {
            $scopeQuery->select(DB::raw(1))
                ->from('spatial_scopes')
                ->whereIn('spatial_scopes.id', $scopeIds)
                ->whereRaw('assets.geom IS NOT NULL')
                ->whereRaw('ST_Intersects(assets.geom, spatial_scopes.geom)');
        });
    }
}
