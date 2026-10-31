<?php

namespace App\Services;

use App\Enums\WorkflowStateCategory;
use App\Enums\WorkflowVersionStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkflowDefinitionService
{
    public function __construct(private AuditLogger $audit, private WorkflowConditionEvaluator $conditions) {}

    public function createDefinition(User $actor, array $data): WorkflowDefinition
    {
        return DB::transaction(function () use ($actor, $data) {
            $organizationId = $actor->hasRole('superadmin') ? ($data['organization_id'] ?? $actor->organization_id) : $actor->organization_id;
            if (! $actor->hasRole('superadmin') && $organizationId === null) {
                throw new DomainException('عضویت سازمانی برای ایجاد گردش کار الزامی است.');
            }
            $definition = WorkflowDefinition::query()->create(Arr::only($data, ['key', 'name', 'description', 'subject_type', 'is_active']) + ['organization_id' => $organizationId]);
            $this->audit->record('workflow.definition_created', $definition, null, $this->audit->attributesFor($definition));

            return $definition;
        });
    }

    public function updateDefinition(WorkflowDefinition $definition, array $data): WorkflowDefinition
    {
        return DB::transaction(function () use ($definition, $data) {
            $before = $this->audit->attributesFor($definition);
            $definition->update(Arr::only($data, ['name', 'description', 'is_active']));
            $this->audit->record('workflow.definition_updated', $definition, $before, $this->audit->attributesFor($definition));

            return $definition->refresh();
        });
    }

    public function createDraftVersion(WorkflowDefinition $definition, array $data = []): WorkflowVersion
    {
        return DB::transaction(function () use ($definition, $data) {
            $definition = WorkflowDefinition::query()->lockForUpdate()->findOrFail($definition->id);
            $next = ((int) $definition->versions()->max('version')) + 1;
            $version = $definition->versions()->create(['version' => $next, 'status' => WorkflowVersionStatus::Draft->value, 'configuration' => $data['configuration'] ?? null]);
            $this->audit->record('workflow.version_created', $version, null, $this->audit->attributesFor($version), ['definition_id' => $definition->id]);

            return $version;
        });
    }

    public function updateVersion(WorkflowVersion $version, array $data): WorkflowVersion
    {
        $this->assertDraft($version);

        return DB::transaction(function () use ($version, $data) {
            $before = $this->audit->attributesFor($version);
            $version->fill(Arr::only($data, ['configuration']))->save();
            $this->audit->record('workflow.version_updated', $version, $before, $this->audit->attributesFor($version));

            return $version->refresh();
        });
    }

    public function addState(WorkflowVersion $version, array $data): WorkflowState
    {
        $this->assertDraft($version);

        return DB::transaction(function () use ($version, $data) {
            $state = $version->states()->create(Arr::only($data, ['key', 'name', 'description', 'category', 'sort_order', 'configuration']));
            $this->audit->record('workflow.state_created', $state, null, $this->audit->attributesFor($state), ['version_id' => $version->id]);

            return $state;
        });
    }

    public function updateState(WorkflowState $state, array $data): WorkflowState
    {
        $this->assertDraft($state->version);

        return DB::transaction(function () use ($state, $data) {
            $before = $this->audit->attributesFor($state);
            $state->update(Arr::only($data, ['key', 'name', 'description', 'category', 'sort_order', 'configuration']));
            $this->audit->record('workflow.state_updated', $state, $before, $this->audit->attributesFor($state));

            return $state->refresh();
        });
    }

    public function addTransition(WorkflowVersion $version, array $data): WorkflowTransition
    {
        $this->assertDraft($version);
        $this->conditions->validate($data['condition_definition'] ?? null);
        $this->validateAssignment($data['assignment_definition'] ?? null, $version);

        return DB::transaction(function () use ($version, $data) {
            $transition = $version->transitions()->create(Arr::only($data, ['key', 'name', 'from_state_id', 'to_state_id', 'sort_order', 'condition_definition', 'assignment_definition', 'required_permission', 'required_form_key', 'configuration']));
            $this->audit->record('workflow.transition_created', $transition, null, $this->audit->attributesFor($transition), ['version_id' => $version->id]);

            return $transition;
        });
    }

    public function updateTransition(WorkflowTransition $transition, array $data): WorkflowTransition
    {
        $this->assertDraft($transition->version);
        $this->conditions->validate($data['condition_definition'] ?? $transition->condition_definition);
        $this->validateAssignment($data['assignment_definition'] ?? $transition->assignment_definition, $transition->version);

        return DB::transaction(function () use ($transition, $data) {
            $before = $this->audit->attributesFor($transition);
            $transition->update(Arr::only($data, ['key', 'name', 'from_state_id', 'to_state_id', 'sort_order', 'condition_definition', 'assignment_definition', 'required_permission', 'required_form_key', 'configuration']));
            $this->audit->record('workflow.transition_updated', $transition, $before, $this->audit->attributesFor($transition));

            return $transition->refresh();
        });
    }

    public function removeTransition(WorkflowTransition $transition): void
    {
        $this->assertDraft($transition->version);
        DB::transaction(function () use ($transition) {
            $before = $this->audit->attributesFor($transition);
            $this->audit->record('workflow.transition_removed', $transition, $before, null, ['version_id' => $transition->workflow_version_id]);
            $transition->delete();
        });
    }

    public function removeState(WorkflowState $state): void
    {
        $this->assertDraft($state->version);
        if ($state->incomingTransitions()->exists() || $state->outgoingTransitions()->exists()) {
            throw new DomainException('پیش از حذف وضعیت، انتقال‌های متصل به آن را حذف کنید.');
        }
        DB::transaction(function () use ($state) {
            $before = $this->audit->attributesFor($state);
            $this->audit->record('workflow.state_removed', $state, $before, null, ['version_id' => $state->workflow_version_id]);
            $state->delete();
        });
    }

    public function validateVersion(WorkflowVersion $version): array
    {
        $version->load(['states', 'transitions']);
        $errors = [];
        $initials = $version->states->where('category', WorkflowStateCategory::Initial);
        $terminals = $version->states->where('category', WorkflowStateCategory::Terminal);
        if ($initials->count() !== 1) {
            $errors[] = 'گردش کار باید دقیقاً یک وضعیت آغازین داشته باشد.';
        }
        if ($terminals->isEmpty()) {
            $errors[] = 'گردش کار باید حداقل یک وضعیت پایانی داشته باشد.';
        }
        foreach ($version->transitions as $transition) {
            if (($transition->from_state_id && ! $version->states->contains('id', $transition->from_state_id)) || ! $version->states->contains('id', $transition->to_state_id)) {
                $errors[] = "انتقال {$transition->key} به وضعیت خارج از نسخه اشاره می‌کند.";
            }
            try {
                $this->conditions->validate($transition->condition_definition);
            } catch (DomainException $e) {
                $errors[] = "شرط انتقال {$transition->key}: {$e->getMessage()}";
            }
            try {
                $this->validateAssignment($transition->assignment_definition, $version);
            } catch (DomainException $e) {
                $errors[] = "تخصیص انتقال {$transition->key}: {$e->getMessage()}";
            }
        }
        if ($initials->count() === 1 && $terminals->isNotEmpty() && ! $this->hasTerminalPath($initials->first()->id, $terminals->pluck('id')->all(), $version->transitions->all())) {
            $errors[] = 'از وضعیت آغازین هیچ مسیر قابل وصولی به وضعیت پایانی وجود ندارد.';
        }

        return $errors;
    }

    public function publishVersion(WorkflowVersion $version, User $actor): WorkflowVersion
    {
        return DB::transaction(function () use ($version, $actor) {
            $version = WorkflowVersion::query()->lockForUpdate()->findOrFail($version->id);
            $this->assertDraft($version);
            $errors = $this->validateVersion($version);
            if ($errors) {
                throw new DomainException(implode(' ', $errors));
            }
            $version->update(['status' => WorkflowVersionStatus::Published->value, 'published_at' => now(), 'published_by' => $actor->id]);
            $this->audit->record('workflow.version_published', $version, null, $this->audit->attributesFor($version));

            return $version->refresh();
        });
    }

    public function archiveVersion(WorkflowVersion $version): WorkflowVersion
    {
        if ($version->status !== WorkflowVersionStatus::Published) {
            throw new DomainException('فقط نسخه منتشرشده قابل بایگانی است.');
        }

        return DB::transaction(function () use ($version) {
            $before = $this->audit->attributesFor($version);
            $version->update(['status' => WorkflowVersionStatus::Archived->value]);
            $this->audit->record('workflow.version_archived', $version, $before, $this->audit->attributesFor($version));

            return $version->refresh();
        });
    }

    public function clonePublishedVersionToDraft(WorkflowVersion $source): WorkflowVersion
    {
        if ($source->status !== WorkflowVersionStatus::Published) {
            throw new DomainException('فقط نسخه منتشرشده قابل تکثیر است.');
        }

        return DB::transaction(function () use ($source) {
            $source->load(['definition', 'states', 'transitions']);
            $clone = $this->createDraftVersion($source->definition, ['configuration' => $source->configuration]);
            $map = [];
            foreach ($source->states as $state) {
                $copy = $clone->states()->create($state->only(['key', 'name', 'description', 'category', 'sort_order', 'configuration']));
                $map[$state->id] = $copy->id;
            }
            foreach ($source->transitions as $transition) {
                $attributes = $transition->only(['key', 'name', 'sort_order', 'condition_definition', 'assignment_definition', 'required_permission', 'required_form_key', 'configuration']);
                $attributes['from_state_id'] = $transition->from_state_id ? $map[$transition->from_state_id] : null;
                $attributes['to_state_id'] = $map[$transition->to_state_id];
                $clone->transitions()->create($attributes);
            }
            $this->audit->record('workflow.version_cloned', $clone, null, $this->audit->attributesFor($clone), ['source_version_id' => $source->id]);

            return $clone->load(['states', 'transitions']);
        });
    }

    private function assertDraft(WorkflowVersion $version): void
    {
        if (! $version->isMutable()) {
            throw new DomainException('نسخه منتشرشده یا بایگانی‌شده تغییرپذیر نیست؛ ابتدا یک نسخه پیش‌نویس جدید بسازید.');
        }
    }

    private function validateAssignment(?array $assignment, WorkflowVersion $version): void
    {
        if ($assignment === null || $assignment === []) {
            return;
        }
        if (array_diff(array_keys($assignment), ['assigned_to', 'assigned_role_id', 'title', 'due_hours', 'metadata']) || ! is_string($assignment['title'] ?? null) || trim($assignment['title']) === '') {
            throw new DomainException('ساختار تخصیص وظیفه معتبر نیست.');
        }
        if (isset($assignment['assigned_to']) && (! is_int($assignment['assigned_to']) || $assignment['assigned_to'] < 1)) {
            throw new DomainException('شناسه کاربر تخصیص معتبر نیست.');
        }
        if (isset($assignment['assigned_role_id']) && (! is_int($assignment['assigned_role_id']) || $assignment['assigned_role_id'] < 1)) {
            throw new DomainException('شناسه نقش تخصیص معتبر نیست.');
        }
        if (isset($assignment['assigned_to'], $assignment['assigned_role_id'])) {
            throw new DomainException('هر وظیفه باید به یک کاربر یا یک نقش تخصیص یابد، نه هر دو.');
        }
        if (! isset($assignment['assigned_to']) && ! isset($assignment['assigned_role_id'])) {
            throw new DomainException('کاربر یا نقش دریافت‌کننده وظیفه را انتخاب کنید.');
        }
        if (isset($assignment['assigned_to'])) {
            $organizationId = $version->definition()->value('organization_id');
            if ($organizationId === null) {
                throw new DomainException('در گردش‌کار سراسری فقط تخصیص بر اساس نقش مجاز است.');
            }
            if (! User::query()->whereKey($assignment['assigned_to'])->where('organization_id', $organizationId)->exists()) {
                throw new DomainException('کاربر انتخاب‌شده به سازمان گردش‌کار تعلق ندارد.');
            }
        }
        if (isset($assignment['assigned_role_id']) && ! Role::query()->whereKey($assignment['assigned_role_id'])->exists()) {
            throw new DomainException('نقش انتخاب‌شده وجود ندارد.');
        }
        if (isset($assignment['due_hours']) && (! is_int($assignment['due_hours']) || $assignment['due_hours'] < 0)) {
            throw new DomainException('مهلت وظیفه معتبر نیست.');
        }
        if (isset($assignment['metadata']) && ! is_array($assignment['metadata'])) {
            throw new DomainException('فراداده وظیفه معتبر نیست.');
        }
    }

    private function hasTerminalPath(int $start, array $terminals, array $transitions): bool
    {
        $queue = [$start];
        $visited = [];
        while ($queue) {
            $state = array_shift($queue);
            if (in_array($state, $terminals, true)) {
                return true;
            } if (isset($visited[$state])) {
                continue;
            } $visited[$state] = true;
            foreach ($transitions as $transition) {
                if ($transition->from_state_id === $state) {
                    $queue[] = $transition->to_state_id;
                }
            }
        }

        return false;
    }
}
