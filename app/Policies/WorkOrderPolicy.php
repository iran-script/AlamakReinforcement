<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AccessPolicyService;

class WorkOrderPolicy
{
    public function __construct(private AccessPolicyService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->can($user, 'work_orders', 'view');
    }

    public function create(User $user): bool
    {
        return $this->access->can($user, 'work_orders', 'create');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $this->owns($user, $workOrder) && $this->access->can($user, 'work_orders', 'view');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $this->owns($user, $workOrder) && $this->access->can($user, 'work_orders', 'update');
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $this->owns($user, $workOrder) && $this->access->can($user, 'work_orders', 'assign');
    }

    public function changeStatus(User $user, WorkOrder $workOrder): bool
    {
        return $this->owns($user, $workOrder) && $this->access->can($user, 'work_orders', 'change_status');
    }

    public function attachAsset(User $user, WorkOrder $workOrder): bool
    {
        return $this->owns($user, $workOrder) && $this->access->can($user, 'work_orders', 'attach_asset');
    }

    private function owns(User $user, WorkOrder $workOrder): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->organization_id !== null
            && $workOrder->organization_id !== null
            && (int) $user->organization_id === (int) $workOrder->organization_id;
    }
}
