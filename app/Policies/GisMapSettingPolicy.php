<?php

namespace App\Policies;

use App\Models\GisMapSetting;
use App\Models\User;
use App\Services\AccessPolicyService;

class GisMapSettingPolicy
{
    public function __construct(private AccessPolicyService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'gis_settings', 'view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'gis_settings', 'configure');
    }

    public function update(User $user, GisMapSetting $setting): bool
    {
        return ($user->hasRole('superadmin') || ($setting->organization_id !== null && (int) $setting->organization_id === (int) $user->organization_id))
            && $this->access->can($user, 'gis_settings', 'configure');
    }
}
