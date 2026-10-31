<?php

namespace App\Services;

use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Enums\FormVersionStatus;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionFile;
use App\Models\FormVersion;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTransition;
use App\Models\Zone;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormSubmissionService
{
    private const FILE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain'];

    public function __construct(private AuditLogger $audit, private AccessPolicyService $access, private WorkflowConditionEvaluator $conditions) {}

    public function createDraft(FormVersion $v, User $actor, array $context = []): FormSubmission
    {
        if ($v->status !== FormVersionStatus::Published) {
            throw new DomainException('نسخه فرم باید منتشرشده باشد.');
        }$org = $actor->hasRole('superadmin') ? ($context['organization_id'] ?? $actor->organization_id) : $actor->organization_id;
        if ($v->definition->organization_id !== null && (int) $v->definition->organization_id !== (int) $org) {
            throw new DomainException('فرم متعلق به سازمان دیگری است.');
        }$this->validateLinks($org, $context);

        return DB::transaction(function () use ($v, $context, $org) {
            $s = FormSubmission::create(['organization_id' => $org, 'form_version_id' => $v->id, 'subject_type' => $context['subject_type'] ?? null, 'subject_id' => $context['subject_id'] ?? null, 'workflow_instance_id' => $context['workflow_instance_id'] ?? null, 'workflow_transition_id' => $context['workflow_transition_id'] ?? null, 'status' => 'draft', 'data' => [], 'context' => $context['context'] ?? null]);
            $this->audit->record('form.submission_created', $s, null, $this->audit->attributesFor($s));

            return $s;
        });
    }

    public function saveDraft(FormSubmission $s, array $data, User $actor): FormSubmission
    {
        $this->assertDraft($s);
        $clean = $this->validateSubmission($s, $data, $actor, false);

        return DB::transaction(function () use ($s, $clean) {
            $b = $this->audit->attributesFor($s);
            $s->update(['data' => $clean]);
            $this->audit->record('form.submission_draft_saved', $s, $b, $this->audit->attributesFor($s));

            return $s->refresh();
        });
    }

    public function submit(FormSubmission $s, array $data, User $actor): FormSubmission
    {
        $this->assertDraft($s);
        $clean = $this->validateSubmission($s, $data, $actor, true);

        return DB::transaction(function () use ($s, $clean, $actor) {
            $s = FormSubmission::lockForUpdate()->findOrFail($s->id);
            $this->assertDraft($s);
            $s->update(['data' => $clean, 'status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now()]);
            $this->audit->record('form.submission_submitted', $s, null, $this->audit->attributesFor($s));

            return $s->refresh();
        });
    }

    public function validateSubmission(FormSubmission $s, array $data, User $actor, bool $final = true): array
    {
        if (strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 524288) {
            throw ValidationException::withMessages(['data' => 'حجم داده فرم بیش از حد مجاز است.']);
        }$rules = [];
        $accepted = [];
        foreach ($s->version->fields()->where('is_active', true)->get() as $f) {
            $visible = $this->conditions->evaluate($f->visibility_condition, $this->context($s, $data, $actor));
            $state = $this->accessState($f, $actor);
            if (! $visible || $state === 'hidden') {
                if (array_key_exists($f->key, $data)) {
                    throw ValidationException::withMessages([$f->key => 'ارسال فیلد مخفی مجاز نیست.']);
                }

                continue;
            }if ($state !== 'edit' && array_key_exists($f->key, $data) && ($s->data[$f->key] ?? null) !== $data[$f->key]) {
                throw ValidationException::withMessages([$f->key => 'ویرایش این فیلد مجاز نیست.']);
            }$hasRequiredCondition = $f->required_condition !== null && $f->required_condition !== [];
            $required = $final && ($f->is_required || ($hasRequiredCondition && $this->conditions->evaluate($f->required_condition, $this->context($s, $data, $actor))));
            if ($required && in_array($f->field_type, [FormFieldType::File, FormFieldType::Image, FormFieldType::Signature], true)) {
                if (! $s->files()->where('field_key', $f->key)->exists()) {
                    throw ValidationException::withMessages([$f->key => 'بارگذاری فایل این فیلد الزامی است.']);
                }
                $required = false;
            }
            $rules[$f->key] = $this->rules($f, $required, $s);
            if (array_key_exists($f->key, $data)) {
                $accepted[$f->key] = $data[$f->key];
            }
        }
        $validated = Validator::make($accepted, $rules, [], [])->validate();
        $this->validateReferences($s, $validated, $actor);

        return array_replace($s->data ?? [], $validated);
    }

    public function resolveVisibleFields(FormVersion $v, User $actor, array $data = []): array
    {
        $dummy = new FormSubmission(['data' => $data]);

        return $v->fields->filter(fn ($f) => $this->accessState($f, $actor) !== 'hidden' && $this->conditions->evaluate($f->visibility_condition, $this->context($dummy, $data, $actor)))->values()->all();
    }

    public function resolveEditableFields(FormVersion $v, User $actor, array $data = []): array
    {
        return array_values(array_filter($this->resolveVisibleFields($v, $actor, $data), fn ($f) => $this->accessState($f, $actor) === 'edit'));
    }

    public function uploadFile(FormSubmission $s, string $fieldKey, UploadedFile $file, User $actor): FormSubmissionFile
    {
        $this->assertDraft($s);
        $f = $s->version->fields()->where('key', $fieldKey)->firstOrFail();
        if (! in_array($f->field_type, [FormFieldType::File, FormFieldType::Image, FormFieldType::Signature], true) || $this->accessState($f, $actor) !== 'edit') {
            throw new DomainException('آپلود برای این فیلد مجاز نیست.');
        }$allowed = $f->validation['mime_types'] ?? self::FILE_TYPES;
        $max = min((int) ($f->validation['max_size_kb'] ?? 10240), 20480);
        Validator::make(['file' => $file], ['file' => ['required', 'file', 'max:'.$max, 'mimetypes:'.implode(',', $allowed)]])->validate();
        $disk = 'local';
        $path = $file->store('organizations/'.($s->organization_id ?? 'global').'/forms/'.$s->id, $disk);
        $m = $s->files()->create(['field_key' => $fieldKey, 'disk' => $disk, 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $actor->id]);
        $this->audit->record('form.file_uploaded', $m, null, ['id' => $m->id, 'field_key' => $fieldKey, 'size' => $m->size]);

        return $m;
    }

    public function removeDraftFile(FormSubmissionFile $file): void
    {
        $this->assertDraft($file->submission);
        Storage::disk($file->disk)->delete($file->path);
        $this->audit->record('form.file_removed', $file, $this->audit->attributesFor($file), null);
        $file->delete();
    }

    public function hasRequiredWorkflowSubmission(WorkflowInstance $i, WorkflowTransition $t, FormVersion $v): bool
    {
        return FormSubmission::where('organization_id', $i->organization_id)->where('form_version_id', $v->id)->where('workflow_instance_id', $i->id)->where('workflow_transition_id', $t->id)->where('subject_type', $i->subject_type)->where('subject_id', $i->subject_id)->where('status', 'submitted')->exists();
    }

    private function rules(FormField $f, bool $required, FormSubmission $s): array
    {
        $r = [$required ? 'required' : 'nullable'];
        $type = $f->field_type;
        if (in_array($type, [FormFieldType::Text, FormFieldType::Textarea, FormFieldType::Signature], true)) {
            $r[] = 'string';
        }if (in_array($type, [FormFieldType::Number, FormFieldType::Decimal], true)) {
            $r[] = 'numeric';
        }if ($type === FormFieldType::Boolean) {
            $r[] = 'boolean';
        }if ($type === FormFieldType::Date) {
            $r[] = 'date_format:Y-m-d';
        }if ($type === FormFieldType::Datetime) {
            $r[] = 'date';
        }if (in_array($type, [FormFieldType::MultiSelect, FormFieldType::CheckboxGroup, FormFieldType::RepeatableGroup], true)) {
            $r[] = 'array';
        }if (in_array($type, [FormFieldType::Select, FormFieldType::Radio], true)) {
            $r[] = 'in:'.implode(',', array_column($f->options ?? [], 'value'));
        }$v = $f->validation ?? [];
        foreach (['min', 'max'] as $k) {
            if (isset($v[$k])) {
                $r[] = $k.':'.$v[$k];
            }
        }if (isset($v['min_length'])) {
            $r[] = 'min:'.$v['min_length'];
        }if (isset($v['max_length'])) {
            $r[] = 'max:'.$v['max_length'];
        }if (isset($v['pattern']) && preg_match('/^\A[\w\s\-\.\[\]\(\)\?\+\*\^\$\\|]+\z$/u', $v['pattern'])) {
            $r[] = 'regex:/'.$v['pattern'].'/u';
        }if (isset($v['max_items'])) {
            $r[] = 'max:'.min(100, (int) $v['max_items']);
        }
        if (isset($v['date_min'])) {
            $r[] = 'after_or_equal:'.$v['date_min'];
        }
        if (isset($v['date_max'])) {
            $r[] = 'before_or_equal:'.$v['date_max'];
        }

        return $r;
    }

    private function validateReferences(FormSubmission $s, array $data, User $actor): void
    {
        foreach ($s->version->fields as $f) {
            if (! isset($data[$f->key])) {
                continue;
            }$id = $data[$f->key];
            if (in_array($f->field_type, [FormFieldType::MultiSelect, FormFieldType::CheckboxGroup], true)) {
                $allowed = array_column($f->options ?? [], 'value');
                if (! is_array($id) || array_diff($id, $allowed)) {
                    throw ValidationException::withMessages([$f->key => 'یک یا چند گزینه معتبر نیست.']);
                }
            }
            if ($f->field_type === FormFieldType::User && ! User::whereKey($id)->where('organization_id', $s->organization_id)->exists()) {
                throw ValidationException::withMessages([$f->key => 'کاربر خارج از سازمان است.']);
            }if ($f->field_type === FormFieldType::Asset && ! $this->access->visibleAssets($actor)->whereKey($id)->where('organization_id', $s->organization_id)->exists()) {
                throw ValidationException::withMessages([$f->key => 'دارایی معتبر یا قابل دسترس نیست.']);
            }if ($f->field_type === FormFieldType::Zone && ! Zone::whereKey($id)->exists()) {
                throw ValidationException::withMessages([$f->key => 'ناحیه معتبر نیست.']);
            }if (in_array($f->field_type, [FormFieldType::Geopoint, FormFieldType::Geometry], true)) {
                $this->validateGeoJson($data[$f->key], $f->key);
            }
        }
    }

    private function validateGeoJson(mixed $g, string $key): void
    {
        if (! is_array($g) || ! in_array($g['type'] ?? null, ['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon'], true) || ! is_array($g['coordinates'] ?? null) || isset($g['crs'])) {
            throw ValidationException::withMessages([$key => 'GeoJSON باید معتبر و با SRID 4326 باشد.']);
        }
    }

    private function accessState(FormField $f, User $u): string
    {
        $a = $f->access_definition ?? [];
        $state = $a['users'][(string) $u->id] ?? null;
        foreach ($u->roles()->pluck('roles.id') as $id) {
            $state = $state ?? ($a['roles'][(string) $id] ?? null);
        }$state = $state ?? ($a['default'] ?? 'edit');
        $policy = $this->access->fieldAccessState($u, 'form_submissions', $f->key, $state);
        $rank = ['edit' => 0, 'readonly' => 1, 'hidden' => 2];

        return ($rank[$policy] ?? 2) > ($rank[$state] ?? 2) ? $policy : $state;
    }

    private function context(FormSubmission $s, array $data, User $u): array
    {
        return ['input' => $data, 'submission' => ['data' => $s->data ?? []], 'actor' => ['id' => $u->id, 'role_ids' => $u->roles()->pluck('roles.id')->map(fn ($x) => (int) $x)->all()]];
    }

    private function validateLinks(?int $org, array $c): void
    {
        if (isset($c['workflow_instance_id']) && ! WorkflowInstance::whereKey($c['workflow_instance_id'])->where('organization_id', $org)->exists()) {
            throw new DomainException('نمونه گردش کار معتبر نیست.');
        }if (isset($c['workflow_transition_id'],$c['workflow_instance_id'])) {
            $i = WorkflowInstance::find($c['workflow_instance_id']);
            if (! WorkflowTransition::whereKey($c['workflow_transition_id'])->where('workflow_version_id', $i?->workflow_version_id)->exists()) {
                throw new DomainException('انتقال گردش کار معتبر نیست.');
            }
        }
    }

    private function assertDraft(FormSubmission $s): void
    {
        if ($s->status !== FormSubmissionStatus::Draft) {
            throw new DomainException('ارسال نهایی تغییرپذیر نیست.');
        }
    }
}
