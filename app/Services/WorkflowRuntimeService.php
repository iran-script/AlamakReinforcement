<?php

namespace App\Services;

use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowStateCategory;
use App\Enums\WorkflowTaskStatus;
use App\Enums\WorkflowVersionStatus;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceTransition;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Models\WorkOrder;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowRuntimeService
{
    public function __construct(
        private AuditLogger $audit,
        private AccessPolicyService $access,
        private WorkflowConditionEvaluator $conditions,
        private FormDefinitionService $forms,
        private FormSubmissionService $formSubmissions,
    ) {}

    public function startInstance(WorkflowVersion $version, WorkOrder $workOrder, User $actor, array $context = []): WorkflowInstance
    {
        if ($version->status !== WorkflowVersionStatus::Published || $version->definition->subject_type !== 'work_orders') {
            throw new DomainException('نسخه منتشرشده و سازگار با دستور کار الزامی است.');
        }
        $this->assertWorkOrderAccess($actor, $workOrder);
        if ($version->definition->organization_id !== null && (int) $version->definition->organization_id !== (int) $workOrder->organization_id) {
            throw new DomainException('گردش کار متعلق به سازمان دستور کار نیست.');
        }

        return DB::transaction(function () use ($version, $workOrder, $actor, $context) {
            $workOrder = WorkOrder::query()->lockForUpdate()->findOrFail($workOrder->id);
            if ($workOrder->workflow_instance_id !== null) {
                throw new DomainException('برای این دستور کار قبلاً گردش کار آغاز شده است.');
            }
            $initial = $version->states()->where('category', WorkflowStateCategory::Initial->value)->sole();
            $instance = WorkflowInstance::query()->create([
                'organization_id' => $workOrder->organization_id,
                'workflow_version_id' => $version->id,
                'subject_type' => 'work_orders',
                'subject_id' => $workOrder->id,
                'current_state_id' => $initial->id,
                'status' => WorkflowInstanceStatus::Active->value,
                'context' => $context,
                'started_by' => $actor->id,
                'started_at' => now(),
            ]);
            $workOrder->update([
                'workflow_definition_id' => $version->workflow_definition_id,
                'workflow_version_id' => $version->id,
                'workflow_instance_id' => $instance->id,
            ]);
            $this->audit->record('workflow.instance_started', $instance, null, $this->audit->attributesFor($instance), ['work_order_id' => $workOrder->id]);

            return $instance->load(['version.definition', 'currentState']);
        });
    }

    public function availableTransitions(WorkflowInstance $instance, User $actor, array $input = []): Collection
    {
        if ($instance->status !== WorkflowInstanceStatus::Active) {
            return collect();
        }
        $this->assertSubjectAccess($actor, $instance);
        $context = $this->evaluationContext($instance, $actor, $input);

        return $instance->version->transitions()->where('from_state_id', $instance->current_state_id)->get()
            ->filter(fn (WorkflowTransition $transition) => $this->hasRequiredForm($instance, $transition)
                && $this->hasRequiredPermission($actor, $transition)
                && $this->conditions->evaluate($transition->condition_definition, $context));
    }

    public function transition(WorkflowInstance $instance, WorkflowTransition $transition, User $actor, array $input = [], ?string $comment = null): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $transition, $actor, $input, $comment) {
            $instance = WorkflowInstance::query()->lockForUpdate()->with(['version.definition', 'currentState'])->findOrFail($instance->id);
            if ($instance->status !== WorkflowInstanceStatus::Active || $transition->workflow_version_id !== $instance->workflow_version_id || $transition->from_state_id !== $instance->current_state_id) {
                throw new DomainException('انتقال با وضعیت فعلی نمونه گردش کار سازگار نیست.');
            }
            if (! $this->access->can($actor, 'workflow_instances', 'transition') || ! $this->hasRequiredPermission($actor, $transition)) {
                throw new DomainException('مجوز اجرای این انتقال را ندارید.');
            }
            if (! $this->hasRequiredForm($instance, $transition)) {
                throw new DomainException('فرم الزامی معتبر و ارسال‌شده برای این انتقال وجود ندارد.');
            }
            if (! $this->conditions->evaluate($transition->condition_definition, $this->evaluationContext($instance, $actor, $input))) {
                throw new DomainException('شرایط اجرای انتقال برقرار نیست.');
            }

            $this->assertSubjectAccess($actor, $instance);
            $history = WorkflowInstanceTransition::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_transition_id' => $transition->id,
                'from_state_id' => $instance->current_state_id,
                'to_state_id' => $transition->to_state_id,
                'performed_by' => $actor->id,
                'comment' => $comment,
                'input' => $input ?: null,
                'performed_at' => now(),
            ]);
            $instance->update(['current_state_id' => $transition->to_state_id]);
            $instance->tasks()->where('status', WorkflowTaskStatus::Open->value)->update(['status' => WorkflowTaskStatus::Cancelled->value]);
            if ($transition->assignment_definition) {
                $this->createTask($instance, $history, $transition->assignment_definition);
            }
            $this->completeInstanceIfTerminal($instance);
            $this->audit->record('workflow.transition_performed', $instance, null, $this->audit->attributesFor($instance), ['transition_id' => $transition->id, 'history_id' => $history->id]);

            return $instance->refresh()->load(['currentState', 'tasks']);
        });
    }

    public function createTask(WorkflowInstance $instance, ?WorkflowInstanceTransition $history, array $assignment): WorkflowTask
    {
        $allowed = ['assigned_to', 'assigned_role_id', 'title', 'due_hours', 'metadata'];
        if (array_diff(array_keys($assignment), $allowed) || empty($assignment['title'])) {
            throw new DomainException('ساختار تخصیص وظیفه معتبر نیست.');
        }
        if (isset($assignment['assigned_to']) && ! User::query()->whereKey($assignment['assigned_to'])->where('organization_id', $instance->organization_id)->exists()) {
            throw new DomainException('کاربر تخصیص‌یافته متعلق به سازمان نمونه نیست.');
        }

        return DB::transaction(function () use ($instance, $history, $assignment) {
            $task = WorkflowTask::query()->create([
                'workflow_instance_id' => $instance->id,
                'workflow_instance_transition_id' => $history?->id,
                'organization_id' => $instance->organization_id,
                'assigned_to' => $assignment['assigned_to'] ?? null,
                'assigned_role_id' => $assignment['assigned_role_id'] ?? null,
                'status' => WorkflowTaskStatus::Open->value,
                'title' => $assignment['title'],
                'due_at' => isset($assignment['due_hours']) ? now()->addHours((int) $assignment['due_hours']) : null,
                'metadata' => $assignment['metadata'] ?? null,
            ]);
            $this->audit->record('workflow.task_created', $task, null, $this->audit->attributesFor($task));

            return $task;
        });
    }

    public function completeTask(WorkflowTask $task, User $actor): WorkflowTask
    {
        return DB::transaction(function () use ($task, $actor) {
            $task = WorkflowTask::query()->lockForUpdate()->findOrFail($task->id);
            if ($task->status !== WorkflowTaskStatus::Open || ($task->assigned_to && $task->assigned_to !== $actor->id) || ($task->assigned_role_id && ! $actor->roles()->whereKey($task->assigned_role_id)->exists())) {
                throw new DomainException('این وظیفه برای کاربر قابل تکمیل نیست.');
            }
            $before = $this->audit->attributesFor($task);
            $task->update(['status' => WorkflowTaskStatus::Completed->value, 'completed_at' => now()]);
            $this->audit->record('workflow.task_completed', $task, $before, $this->audit->attributesFor($task));

            return $task->refresh();
        });
    }

    public function cancelInstance(WorkflowInstance $instance): WorkflowInstance
    {
        return DB::transaction(function () use ($instance) {
            $instance = WorkflowInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if ($instance->status !== WorkflowInstanceStatus::Active) {
                throw new DomainException('فقط نمونه فعال قابل لغو است.');
            }
            $instance->update(['status' => WorkflowInstanceStatus::Cancelled->value, 'completed_at' => now()]);
            $instance->tasks()->where('status', WorkflowTaskStatus::Open->value)->update(['status' => WorkflowTaskStatus::Cancelled->value]);
            $this->audit->record('workflow.instance_cancelled', $instance, null, $this->audit->attributesFor($instance));

            return $instance->refresh();
        });
    }

    public function completeInstanceIfTerminal(WorkflowInstance $instance): void
    {
        if ($instance->currentState()->where('category', WorkflowStateCategory::Terminal->value)->exists()) {
            $instance->update(['status' => WorkflowInstanceStatus::Completed->value, 'completed_at' => now()]);
            $this->audit->record('workflow.instance_completed', $instance, null, $this->audit->attributesFor($instance));
        }
    }

    private function hasRequiredPermission(User $actor, WorkflowTransition $transition): bool
    {
        if (! $transition->required_permission) {
            return true;
        }
        if (! preg_match('/^([a-z_]+):([a-z_]+)$/', $transition->required_permission, $matches)) {
            return false;
        }

        return $this->access->can($actor, $matches[1], $matches[2]);
    }

    private function hasRequiredForm(WorkflowInstance $instance, WorkflowTransition $transition): bool
    {
        if ($transition->required_form_key === null) {
            return true;
        }
        $version = $this->forms->resolvePublishedForm($transition->required_form_key, $instance->organization_id, $instance->subject_type);

        return $version !== null && $this->formSubmissions->hasRequiredWorkflowSubmission($instance, $transition, $version);
    }

    private function evaluationContext(WorkflowInstance $instance, User $actor, array $input): array
    {
        $workOrder = $instance->subject_type === 'work_orders' ? WorkOrder::query()->find($instance->subject_id) : null;

        return [
            'subject' => ['properties' => $workOrder?->properties ?? []],
            'work_order' => $workOrder ? ['priority' => $workOrder->priority->value, 'status' => $workOrder->status->value, 'properties' => $workOrder->properties ?? []] : [],
            'actor' => ['role_ids' => $actor->roles()->pluck('roles.id')->map(fn ($id) => (int) $id)->all()],
            'input' => $input,
        ];
    }

    private function assertSubjectAccess(User $actor, WorkflowInstance $instance): void
    {
        if ($instance->subject_type !== 'work_orders') {
            throw new DomainException('نوع موضوع گردش کار پشتیبانی نمی‌شود.');
        }
        $this->assertWorkOrderAccess($actor, WorkOrder::query()->findOrFail($instance->subject_id));
    }

    private function assertWorkOrderAccess(User $actor, WorkOrder $workOrder): void
    {
        if (! $actor->hasRole('superadmin') && ((int) $actor->organization_id !== (int) $workOrder->organization_id || ! $this->access->can($actor, 'work_orders', 'view'))) {
            throw new DomainException('دسترسی به دستور کار موضوع گردش کار مجاز نیست.');
        }
        if (! $actor->hasRole('superadmin')) {
            $assetIds = $workOrder->assets()->pluck('assets.id');
            if ($assetIds->isNotEmpty() && $this->access->visibleAssets($actor)->whereIn('assets.id', $assetIds)->count() !== $assetIds->count()) {
                throw new DomainException('دسترسی مکانی به همه دارایی‌های دستور کار وجود ندارد.');
            }
        }
    }
}
