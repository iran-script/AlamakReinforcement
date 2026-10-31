<?php

namespace App\Services;

use App\Enums\FormFieldType;
use App\Enums\FormVersionStatus;
use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FormDefinitionService
{
    public function __construct(private AuditLogger $audit, private WorkflowConditionEvaluator $conditions) {}

    public function createDefinition(User $actor, array $data): FormDefinition
    {
        return DB::transaction(function () use ($actor, $data) {
            $org = $actor->hasRole('superadmin') ? ($data['organization_id'] ?? $actor->organization_id) : $actor->organization_id;
            if (! $actor->hasRole('superadmin') && $org === null) {
                throw new DomainException('عضویت سازمانی الزامی است.');
            } $m = FormDefinition::create(Arr::only($data, ['key', 'name', 'description', 'resource_key', 'subject_type', 'is_active']) + ['organization_id' => $org]);
            $this->audit->record('form.definition_created', $m, null, $this->audit->attributesFor($m));

            return $m;
        });
    }

    public function updateDefinition(FormDefinition $m, array $data): FormDefinition
    {
        return DB::transaction(function () use ($m, $data) {
            $before = $this->audit->attributesFor($m);
            $m->update(Arr::only($data, ['name', 'description', 'resource_key', 'subject_type', 'is_active']));
            $this->audit->record('form.definition_updated', $m, $before, $this->audit->attributesFor($m));

            return $m->refresh();
        });
    }

    public function createDraftVersion(FormDefinition $d, array $data = []): FormVersion
    {
        return DB::transaction(function () use ($d, $data) {
            $d = FormDefinition::lockForUpdate()->findOrFail($d->id);
            $v = $d->versions()->create(['version' => (int) $d->versions()->max('version') + 1, 'status' => FormVersionStatus::Draft->value] + Arr::only($data, ['schema', 'ui_schema', 'configuration']));
            $this->audit->record('form.version_created', $v, null, $this->audit->attributesFor($v), ['definition_id' => $d->id]);

            return $v;
        });
    }

    public function updateVersion(FormVersion $v, array $data): FormVersion
    {
        $this->assertDraft($v);

        return DB::transaction(function () use ($v, $data) {
            $b = $this->audit->attributesFor($v);
            $v->update(Arr::only($data, ['schema', 'ui_schema', 'configuration']));
            $this->audit->record('form.version_updated', $v, $b, $this->audit->attributesFor($v));

            return $v->refresh();
        });
    }

    public function addField(FormVersion $v, array $data): FormField
    {
        $this->assertDraft($v);
        $this->validateField($data);

        return DB::transaction(function () use ($v, $data) {
            $f = $v->fields()->create($data);
            $this->audit->record('form.field_created', $f, null, $this->audit->attributesFor($f), ['version_id' => $v->id]);

            return $f;
        });
    }

    public function updateField(FormField $f, array $data): FormField
    {
        $this->assertDraft($f->version);
        $candidate = array_replace($f->toArray(), $data);
        $this->validateField($candidate);

        return DB::transaction(function () use ($f, $data) {
            $b = $this->audit->attributesFor($f);
            $f->update(Arr::only($data, $f->getFillable()));
            $this->audit->record('form.field_updated', $f, $b, $this->audit->attributesFor($f));

            return $f->refresh();
        });
    }

    public function removeField(FormField $field): void
    {
        $this->assertDraft($field->version);
        DB::transaction(function () use ($field) {
            $before = $this->audit->attributesFor($field);
            $this->audit->record('form.field_removed', $field, $before, null, ['version_id' => $field->form_version_id]);
            $field->delete();
        });
    }

    public function reorderFields(FormVersion $v, array $keys): FormVersion
    {
        $this->assertDraft($v);
        if (count($keys) !== count(array_unique($keys)) || $v->fields()->whereIn('key', $keys)->count() !== count($keys)) {
            throw new DomainException('ترتیب فیلدها معتبر نیست.');
        }

        return DB::transaction(function () use ($v, $keys) {
            foreach ($keys as $i => $key) {
                $v->fields()->where('key', $key)->update(['sort_order' => $i]);
            }$this->audit->record('form.fields_reordered', $v, null, null, ['keys' => $keys]);

            return $v->load('fields');
        });
    }

    public function validateVersion(FormVersion $v): array
    {
        $errors = [];
        $fields = $v->fields()->where('is_active', true)->get();
        if ($fields->isEmpty()) {
            $errors[] = 'حداقل یک فیلد فعال الزامی است.';
        }foreach ($fields as $f) {
            try {
                $this->validateField($f->toArray());
            } catch (DomainException $e) {
                $errors[] = $f->key.': '.$e->getMessage();
            }
        }

        return $errors;
    }

    public function publishVersion(FormVersion $v, User $actor): FormVersion
    {
        return DB::transaction(function () use ($v, $actor) {
            $v = FormVersion::lockForUpdate()->findOrFail($v->id);
            $this->assertDraft($v);
            $e = $this->validateVersion($v);
            if ($e) {
                throw new DomainException(implode(' ', $e));
            }$v->update(['status' => FormVersionStatus::Published->value, 'published_at' => now(), 'published_by' => $actor->id]);
            $this->audit->record('form.version_published', $v, null, $this->audit->attributesFor($v));

            return $v->refresh();
        });
    }

    public function archiveVersion(FormVersion $v): FormVersion
    {
        if ($v->status !== FormVersionStatus::Published) {
            throw new DomainException('فقط نسخه منتشرشده قابل بایگانی است.');
        }$b = $this->audit->attributesFor($v);
        $v->update(['status' => FormVersionStatus::Archived->value]);
        $this->audit->record('form.version_archived', $v, $b, $this->audit->attributesFor($v));

        return $v->refresh();
    }

    public function clonePublishedVersionToDraft(FormVersion $source): FormVersion
    {
        if ($source->status !== FormVersionStatus::Published) {
            throw new DomainException('فقط نسخه منتشرشده قابل تکثیر است.');
        }

        return DB::transaction(function () use ($source) {
            $source->load('definition', 'fields');
            $v = $this->createDraftVersion($source->definition, $source->only(['schema', 'ui_schema', 'configuration']));
            foreach ($source->fields as $f) {
                $v->fields()->create($f->only($f->getFillable()));
            }$this->audit->record('form.version_cloned', $v, null, $this->audit->attributesFor($v), ['source_version_id' => $source->id]);

            return $v->load('fields');
        });
    }

    public function resolvePublishedForm(string $key, ?int $organizationId, ?string $resourceKey = null): ?FormVersion
    {
        return FormVersion::query()->where('status', FormVersionStatus::Published->value)->whereHas('definition', fn ($q) => $q->where('key', $key)->where('is_active', true)->when($resourceKey, fn ($q, $r) => $q->where(fn ($x) => $x->whereNull('resource_key')->orWhere('resource_key', $r)))->where(fn ($q) => $q->where('organization_id', $organizationId)->orWhereNull('organization_id')))->with('definition', 'fields')->orderByRaw('CASE WHEN EXISTS (SELECT 1 FROM form_definitions fd WHERE fd.id=form_versions.form_definition_id AND fd.organization_id = ?) THEN 0 ELSE 1 END', [$organizationId ?? 0])->orderByDesc('version')->first();
    }

    private function assertDraft(FormVersion $v): void
    {
        if (! $v->isMutable()) {
            throw new DomainException('نسخه منتشرشده یا بایگانی‌شده تغییرپذیر نیست؛ یک پیش‌نویس جدید بسازید.');
        }
    }

    private function validateField(array $f): void
    {
        if (! in_array($f['field_type'] instanceof FormFieldType ? $f['field_type']->value : ($f['field_type'] ?? ''), FormFieldType::values(), true)) {
            throw new DomainException('نوع فیلد پشتیبانی نمی‌شود.');
        } foreach (['visibility_condition', 'required_condition'] as $k) {
            $this->conditions->validate($f[$k] ?? null);
        }$type = $f['field_type'] instanceof FormFieldType ? $f['field_type']->value : $f['field_type'];
        if (in_array($type, ['select', 'multi_select', 'radio', 'checkbox_group'], true)) {
            if (! is_array($f['options'] ?? null) || count($f['options']) < 1 || count($f['options']) > 200) {
                throw new DomainException('گزینه‌های فیلد معتبر نیست.');
            }foreach ($f['options'] as $o) {
                if (! is_array($o) || ! isset($o['value'],$o['label']) || array_diff(array_keys($o), ['value', 'label', 'disabled'])) {
                    throw new DomainException('ساختار گزینه معتبر نیست.');
                }
            }
        } $allowed = ['min', 'max', 'min_length', 'max_length', 'pattern', 'date_min', 'date_max', 'max_items', 'unique', 'mime_types', 'max_size_kb'];
        if (array_diff(array_keys($f['validation'] ?? []), $allowed)) {
            throw new DomainException('قاعده اعتبارسنجی مجاز نیست.');
        }if (($f['access_definition'] ?? null) !== null) {
            $a = $f['access_definition'];
            if (! is_array($a) || array_diff(array_keys($a), ['default', 'roles', 'users']) || ! in_array($a['default'] ?? 'edit', ['edit', 'readonly', 'hidden'], true)) {
                throw new DomainException('تعریف دسترسی فیلد معتبر نیست.');
            }
            foreach (array_merge($a['roles'] ?? [], $a['users'] ?? []) as $state) {
                if (! in_array($state, ['edit', 'readonly', 'hidden'], true)) {
                    throw new DomainException('وضعیت دسترسی فیلد معتبر نیست.');
                }
            }
        }if ($type === 'repeatable_group') {
            $c = $f['configuration'] ?? [];
            if (! is_array($c) || ! is_array($c['fields'] ?? null) || count($c['fields']) > 50 || ($c['max_items'] ?? 20) > 100) {
                throw new DomainException('گروه تکرارشونده معتبر یا محدود نیست.');
            }foreach ($c['fields'] as $nested) {
                $this->validateField($nested);
            }
        }
    }
}
