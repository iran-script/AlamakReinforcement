<?php

namespace App\Services;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAsset;
use App\Models\WorkOrderType;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(User $actor, array $attributes): WorkOrder
    {
        return DB::transaction(function () use ($actor, $attributes): WorkOrder {
            $organizationId = $actor->hasRole('superadmin')
                ? ($attributes['organization_id'] ?? $actor->organization_id)
                : $actor->organization_id;

            if (! $actor->hasRole('superadmin') && $organizationId === null) {
                throw new DomainException('برای ایجاد دستور کار، عضویت سازمانی الزامی است.');
            }

            $type = WorkOrderType::query()->findOrFail($attributes['work_order_type_id']);
            if ($type->organization_id !== null && (int) $type->organization_id !== (int) $organizationId) {
                throw new DomainException('نوع دستور کار متعلق به سازمان انتخاب‌شده نیست.');
            }
            $priority = $attributes['priority'] ?? $type->default_priority?->value ?? WorkOrderPriority::Normal->value;
            $values = Arr::only($attributes, [
                'work_order_type_id', 'code', 'title', 'description', 'requester_id', 'contract_id',
                'scheduled_at', 'due_at', 'properties',
            ]);
            $values += [
                'organization_id' => $organizationId,
                'created_by' => $actor->id,
                'status' => WorkOrderStatus::Draft->value,
                'priority' => $priority,
            ];

            if (empty($values['due_at']) && $type->default_sla_hours !== null) {
                $values['due_at'] = now()->addHours($type->default_sla_hours);
            }

            $workOrder = WorkOrder::query()->create($values);
            $this->writeGeometry($workOrder, $attributes);
            $workOrder->refresh();
            $this->audit->record('work_order.created', $workOrder, null, $this->audit->attributesFor($workOrder));

            return $workOrder;
        });
    }

    public function update(WorkOrder $workOrder, array $attributes): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $attributes): WorkOrder {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            $before = $this->audit->attributesFor($workOrder);
            if (isset($attributes['work_order_type_id'])) {
                $type = WorkOrderType::query()->findOrFail($attributes['work_order_type_id']);
                if ($type->organization_id !== null && (int) $type->organization_id !== (int) $workOrder->organization_id) {
                    throw new DomainException('نوع دستور کار متعلق به سازمان دستور کار نیست.');
                }
            }
            $workOrder->fill(Arr::only($attributes, [
                'work_order_type_id', 'code', 'title', 'description', 'priority', 'requester_id',
                'contract_id', 'scheduled_at', 'due_at', 'properties',
            ]))->save();
            $this->writeGeometry($workOrder, $attributes);
            $workOrder->refresh();
            $this->audit->record('work_order.updated', $workOrder, $before, $this->audit->attributesFor($workOrder));

            return $workOrder;
        });
    }

    public function assign(WorkOrder $workOrder, User $assignee): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $assignee): WorkOrder {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ((int) $assignee->organization_id !== (int) $workOrder->organization_id || $assignee->organization_id === null) {
                throw new DomainException('مسئول انتخاب‌شده متعلق به سازمان دستور کار نیست.');
            }
            $before = $this->audit->attributesFor($workOrder);
            $status = $workOrder->status;

            if (in_array($status, [WorkOrderStatus::Open, WorkOrderStatus::OnHold], true)) {
                $status = WorkOrderStatus::Assigned;
            } elseif (! in_array($status, [WorkOrderStatus::Assigned, WorkOrderStatus::InProgress], true)) {
                throw new DomainException('در وضعیت فعلی امکان تخصیص دستور کار وجود ندارد.');
            }

            $workOrder->update(['assigned_to' => $assignee->id, 'status' => $status->value]);
            $this->audit->record('work_order.assigned', $workOrder, $before, $this->audit->attributesFor($workOrder), ['assignee_id' => $assignee->id]);

            return $workOrder->refresh();
        });
    }

    public function changeStatus(WorkOrder $workOrder, WorkOrderStatus $next): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $next): WorkOrder {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if (! $workOrder->status->canTransitionTo($next)) {
                throw new DomainException("انتقال از {$workOrder->status->value} به {$next->value} مجاز نیست.");
            }
            if ($next === WorkOrderStatus::Assigned && $workOrder->assigned_to === null) {
                throw new DomainException('برای وضعیت assigned ابتدا مسئول دستور کار را تعیین کنید.');
            }

            $before = $this->audit->attributesFor($workOrder);
            $changes = ['status' => $next->value];
            if ($next === WorkOrderStatus::InProgress && $workOrder->started_at === null) {
                $changes['started_at'] = now();
            }
            $workOrder->update($changes);
            $this->audit->record('work_order.status_changed', $workOrder, $before, $this->audit->attributesFor($workOrder));

            return $workOrder->refresh();
        });
    }

    public function attachAsset(WorkOrder $workOrder, Asset $asset, string $relationshipType): WorkOrderAsset
    {
        return DB::transaction(function () use ($workOrder, $asset, $relationshipType): WorkOrderAsset {
            if ($asset->organization_id !== null && (int) $asset->organization_id !== (int) $workOrder->organization_id) {
                throw new DomainException('دارایی متعلق به سازمان دستور کار نیست.');
            }
            if (WorkOrderAsset::query()->where('work_order_id', $workOrder->id)->where('asset_id', $asset->id)->exists()) {
                throw new DomainException('این دارایی قبلاً به دستور کار متصل شده است.');
            }

            $link = WorkOrderAsset::query()->create([
                'work_order_id' => $workOrder->id,
                'asset_id' => $asset->id,
                'relationship_type' => $relationshipType,
            ]);
            $this->audit->record('work_order.asset_attached', $workOrder, null, null, [
                'asset_id' => $asset->id, 'relationship_type' => $relationshipType,
            ]);

            return $link;
        });
    }

    public function detachAsset(WorkOrder $workOrder, Asset $asset): void
    {
        DB::transaction(function () use ($workOrder, $asset): void {
            $deleted = WorkOrderAsset::query()->where('work_order_id', $workOrder->id)->where('asset_id', $asset->id)->delete();
            if (! $deleted) {
                throw new DomainException('این دارایی به دستور کار متصل نیست.');
            }
            $this->audit->record('work_order.asset_detached', $workOrder, null, null, ['asset_id' => $asset->id]);
        });
    }

    public function complete(WorkOrder $workOrder): WorkOrder
    {
        return DB::transaction(function () use ($workOrder): WorkOrder {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if (! $workOrder->status->canTransitionTo(WorkOrderStatus::Completed)) {
                throw new DomainException('تکمیل دستور کار از وضعیت فعلی مجاز نیست.');
            }
            $before = $this->audit->attributesFor($workOrder);
            $workOrder->update(['status' => WorkOrderStatus::Completed->value, 'completed_at' => now()]);
            $this->audit->record('work_order.completed', $workOrder, $before, $this->audit->attributesFor($workOrder));

            return $workOrder->refresh();
        });
    }

    private function writeGeometry(WorkOrder $workOrder, array $attributes): void
    {
        if (! array_key_exists('geometry', $attributes)) {
            return;
        }
        if ($attributes['geometry'] === null) {
            DB::table('work_orders')->where('id', $workOrder->id)->update(['geom' => null]);

            return;
        }

        DB::update(
            'UPDATE work_orders SET geom = ST_SetSRID(ST_Force2D(ST_GeomFromGeoJSON(?)), 4326) WHERE id = ?',
            [json_encode($attributes['geometry'], JSON_THROW_ON_ERROR), $workOrder->id]
        );
    }
}
