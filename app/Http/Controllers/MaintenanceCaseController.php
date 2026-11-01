<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceCase;
use App\Models\Material;
use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\OperationMaterial;
use App\Models\OperationSupervisor;
use App\Models\Riser;
use App\Models\User;
use App\Models\WorkflowTransfer;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MaintenanceCaseController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request)
    {
        $this->authorizeFieldOrManager($request->user());
        $user = $request->user();
        $visibleGroupIds = $user->isSuperAdmin() ? null : ($user->hasRole('boss') ? $user->managedGroupIds() : array_filter([(int) $user->group_user_id]));
        abort_unless($user->isSuperAdmin() || ($visibleGroupIds !== [] && $user->zone_id), 403);
        $cases = MaintenanceCase::query()->visibleTo($user)
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereIn('assigned_group_id', $visibleGroupIds))
            ->with(['riser:id,r_giscode,giscode', 'reporter:id,name', 'assignedGroup:id,name', 'operationCategory:id,title', 'operation:id,status,operation_date'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($user->isSuperAdmin() && $request->filled('group'), fn ($query) => $query->where('assigned_group_id', $request->integer('group')))
            ->when($request->filled('supervisor'), fn ($query) => $query->where('reported_by', $request->integer('supervisor')))
            ->latest('identified_at')->paginate(25)->withQueryString();

        return $request->expectsJson()
            ? response()->json($cases)
            : view('maintenance-cases.index', compact('cases'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->requireMobileIdempotencySchema($request, 'maintenance_cases');
        $clientUuidRule = $request->is('api/v1/supervisor/*') ? 'required' : 'nullable';
        abort_if(! $actor->group_user_id || ! $actor->zone_id || ! $actor->organization_id, 422, 'ط¨ط±ط§غŒ ط«ط¨طھ ظ…غŒط¯ط§ظ†غŒطŒ ع¯ط±ظˆظ‡طŒ ط³ط§ط²ظ…ط§ظ† ظˆ Zone ظ†ط§ط¸ط± ط¨ط§غŒط¯ ظ…ط´ط®طµ ط¨ط§ط´ط¯.');
        abort_unless(\App\Models\GroupUser::query()->whereKey($actor->group_user_id)
            ->where('organization_id', $actor->organization_id)->where('zone_id', $actor->zone_id)->exists(), 403,
            'ع¯ط±ظˆظ‡ ع©ط§ط±غŒ ط¨ظ‡ ط³ط§ط²ظ…ط§ظ† غŒط§ Zone ظ†ط§ط¸ط± طھط¹ظ„ظ‚ ظ†ط¯ط§ط±ط¯.');

        $data = $request->validate([
            'riser_id' => ['required', 'integer'],
            'client_uuid' => [$clientUuidRule, 'uuid'],
            'operation_category_id' => ['nullable', 'integer', 'exists:operation_categories,id'],
            'priority' => ['required', 'string', Rule::in(["\u{06A9}\u{0645}", "\u{0645}\u{062A}\u{0648}\u{0633}\u{0637}", "\u{0632}\u{06CC}\u{0627}\u{062F}", "\u{0641}\u{0648}\u{0631}\u{06CC}"])],
            'description' => ['nullable', 'string', 'max:10000'],
            'identified_at' => ['required', 'date'],
            'photos_before' => ['nullable', 'array', 'max:20'],
            'photos_before.*' => ['image', 'max:10240'],
        ]);
        $riser = Riser::query()->visibleTo($actor)->findOrFail($data['riser_id']);
        $supportsCaseIdempotency = Schema::hasColumn('maintenance_cases', 'client_uuid');
        if ($supportsCaseIdempotency && ! empty($data['client_uuid'])) {
            $existing = MaintenanceCase::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                abort_unless((int) $existing->reported_by === (int) $actor->id && (int) $existing->riser_id === (int) $riser->id, 409);
                return response()->json(['case' => $existing->load(['riser:id,r_giscode,giscode', 'assignedGroup:id,name']), 'idempotent_replay' => true]);
            }
        }

        $caseAttributes = [
                'riser_id' => $riser->id,
                'reported_by' => $actor->id,
                'assigned_group_id' => $actor->group_user_id,
                'last_edited_by' => $actor->id,
                'operation_category_id' => $data['operation_category_id'] ?? null,
                'status' => MaintenanceCase::STATUS_OPEN,
                'priority' => $data['priority'],
                'description' => $data['description'] ?? null,
                'identified_at' => $data['identified_at'],
            ];
        if ($supportsCaseIdempotency) {
            $caseAttributes['client_uuid'] = $data['client_uuid'] ?? null;
        }
        $case = DB::transaction(function () use ($request, $data, $actor, $riser, $caseAttributes): MaintenanceCase {
            $case = MaintenanceCase::query()->create($caseAttributes);
            $this->audit->record('maintenance_case.created', $case, null, $this->audit->attributesFor($case));

            foreach ($request->file('photos_before', []) as $file) {
                $photo = $this->storePhoto($file, $actor, $riser, $case, null, 'before');
                $this->audit->record('maintenance_case.photo_added', $photo, null, [
                    'photo_id' => $photo->id, 'type' => 'before', 'maintenance_case_id' => $case->id,
                ]);
            }

            return $case;
        });

        return response()->json(['case' => $case->load(['riser:id,r_giscode,giscode', 'assignedGroup:id,name'])], 201);
    }

    public function show(Request $request, MaintenanceCase $maintenanceCase)
    {
        $this->authorizeCaseView($request->user(), $maintenanceCase);
        $case = $maintenanceCase->load([
            'riser:id,r_giscode,giscode', 'reporter:id,name', 'assignedGroup:id,name',
            'operationCategory:id,title', 'photos:id,maintenance_case_id,operation_id,riser_id,type,uploaded_by',
            'lastEditor:id,name', 'operation.operationCategory', 'operation.operationMaterial', 'operation.images',
        ]);
        $casePhotos = $case->photos->merge($case->operation?->images ?? collect())->unique('id')->values();
        $casePhotos->each(function (OperationImage $photo) use ($request): void {
            $photo->makeHidden(['path', 'original_name']);
            $photo->setAttribute('url', $request->is('api/v1/supervisor/*')
                ? url('/api/v1/supervisor/photos/'.$photo->id)
                : route('maintenance-cases.photos.show', $photo));
        });
        $case->setRelation('photos', $casePhotos);

        return response()->json(['case' => $case]);
    }

    public function photo(Request $request, OperationImage $operationPhoto)
    {
        abort_unless($operationPhoto->maintenance_case_id, 404);
        $case = MaintenanceCase::query()->findOrFail($operationPhoto->maintenance_case_id);
        $this->authorizeCaseView($request->user(), $case);
        abort_unless(Storage::disk('local')->exists($operationPhoto->path), 404);

        return response()->file(Storage::disk('local')->path($operationPhoto->path));
    }

    public function complete(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->requireMobileIdempotencySchema($request, 'operations');
        $clientUuidRule = $request->is('api/v1/supervisor/*') ? 'required' : 'nullable';
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);
        abort_unless(in_array($maintenanceCase->status, [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_RETURNED], true), 409);

        $data = $request->validate([
            'client_uuid' => [$clientUuidRule, 'uuid'],
            'operation_date' => ['required', 'date'],
            'operation_category_id' => ['nullable', 'integer', 'exists:operation_categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'materials' => ['nullable', 'array'],
            'materials.*' => ['numeric', 'gt:0', 'max:99999999.99'],
            'photos_after' => ['nullable', 'array', 'max:20'],
            'photos_after.*' => ['image', 'max:10240'],
        ]);
        $supportsOperationIdempotency = Schema::hasColumn('operations', 'client_uuid');
        if ($supportsOperationIdempotency && ! empty($data['client_uuid'])) {
            $existing = Operation::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                abort_unless((int) $existing->maintenance_case_id === (int) $maintenanceCase->id && (int) $existing->user_id === (int) $actor->id, 409);
                return response()->json(['operation' => $existing->load(['operationMaterial', 'operationCategory', 'images']), 'idempotent_replay' => true]);
            }
        }
        abort_if($maintenanceCase->operation()->exists(), 409, 'This maintenance case already has an operation.');
        $materialIds = array_map('intval', array_keys($data['materials'] ?? []));
        if ($materialIds !== [] && Material::query()->where('is_active', true)->whereIn('id', $materialIds)->count() !== count(array_unique($materialIds))) {
            abort(422, 'ظ…طµط§ظ„ط­ ط§ظ†طھط®ط§ط¨â€Œط´ط¯ظ‡ ظ…ط¹طھط¨ط± غŒط§ ظپط¹ط§ظ„ ظ†غŒط³طھظ†ط¯.');
        }

        $boss = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'boss')->where('guard_name', 'web'))
            ->whereHas('managedGroups', fn ($groups) => $groups->whereKey($maintenanceCase->assigned_group_id)
                ->where('group_users.organization_id', $actor->organization_id)
                ->where('group_users.zone_id', $actor->zone_id))
            ->where('zone_id', $actor->zone_id)->where('organization_id', $actor->organization_id)->orderBy('id')->first();
        abort_unless($boss, 422, 'ط¨ط±ط§غŒ ط§غŒظ† ع¯ط±ظˆظ‡ ظˆ Zone ط±ط¦غŒط³ ظ†ط§ط­غŒظ‡ ظ‚ط§ط¨ظ„ ط§ط±ط¬ط§ط¹ طھط¹ط±غŒظپ ظ†ط´ط¯ظ‡ ط§ط³طھ.');

        $operationAttributes = [
                'riser_id' => $maintenanceCase->riser_id,
                'maintenance_case_id' => $maintenanceCase->id,
                'group_user_id' => $maintenanceCase->assigned_group_id,
                'operation_category_id' => $data['operation_category_id'] ?? $maintenanceCase->operation_category_id,
                'operation_date' => $data['operation_date'],
                'status' => 'submitOperation',
                'priority' => $maintenanceCase->priority,
                'description' => $data['description'] ?? $maintenanceCase->description,
                'user_id' => $actor->id,
                'contract_id' => $actor->contract_id,
            ];
        if ($supportsOperationIdempotency) {
            $operationAttributes['client_uuid'] = $data['client_uuid'] ?? null;
        }
        $operation = DB::transaction(function () use ($request, $data, $actor, $boss, $maintenanceCase, $operationAttributes): Operation {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_if($case->operation()->exists(), 409);
            $beforeCase = $this->audit->attributesFor($case);

            $operation = Operation::query()->create($operationAttributes);

            foreach (($data['materials'] ?? []) as $materialId => $qty) {
                OperationMaterial::query()->create([
                    'operation_id' => $operation->id,
                    'material_id' => (int) $materialId,
                    'qty' => $qty,
                    'price' => 0,
                    'total' => 0,
                    'description' => 'Field maintenance record; quantities only.',
                ]);
            }
            $this->audit->record('operation.created', $operation, null, $this->audit->attributesFor($operation));
            $this->audit->record('operation.materials_updated', $operation, null, ['materials' => $this->materialSnapshot($operation)]);

            foreach ($request->file('photos_after', []) as $file) {
                $photo = $this->storePhoto($file, $actor, $case->riser, $case, $operation, 'after');
                $this->audit->record('operation.photo_added', $photo, null, [
                    'photo_id' => $photo->id, 'type' => 'after', 'maintenance_case_id' => $case->id,
                ]);
            }

            OperationSupervisor::query()->create([
                'operation_id' => $operation->id,
                'user_id' => $boss->id,
                'status' => 'pending',
                'comment' => 'Field report submitted to the area manager.',
                'order' => 1,
            ]);
            WorkflowTransfer::query()->create([
                'user_id_from' => $actor->id,
                'user_id_to' => $boss->id,
                'riser_id' => $case->riser_id,
                'operation_id' => $operation->id,
                'type' => WorkflowTransfer::TYPE_OPERATION_REVIEW_REQUEST,
                'title' => 'Field repair report submitted for area review',
                'comment' => $data['description'] ?? $case->description,
                'send_date' => now(),
            ]);
            $case->update([
                'status' => MaintenanceCase::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'last_edited_by' => $actor->id,
            ]);
            $this->audit->record('maintenance_case.submitted', $case, $beforeCase, $this->audit->attributesFor($case));

            return $operation->load(['operationMaterial', 'operationCategory', 'images']);
        });

        return response()->json(['operation' => $operation], 201);
    }

    public function review(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        abort_unless($actor->hasRole('boss') && $actor->can('operation.approve'), 403);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless($actor->managesGroup((int) $maintenanceCase->assigned_group_id), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'returned'])],
            'comment' => ['nullable', 'required_if:decision,returned', 'string', 'max:5000'],
        ]);

        $operation = $maintenanceCase->operation()->firstOrFail();
        DB::transaction(function () use ($actor, $data, $maintenanceCase, $operation): void {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            $pivot = OperationSupervisor::query()->where('operation_id', $operation->id)->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($pivot->status === 'pending', 409);
            $before = $this->audit->attributesFor($case);
            $approved = $data['decision'] === 'approved';
            $pivot->update([
                'status' => $approved ? 'approved' : 'need_revision',
                'comment' => $data['comment'] ?? null,
                'approved_at' => $approved ? now() : null,
            ]);
            $operation->update(['status' => $approved ? 'approved' : 'need_revision']);
            $case->update([
                'status' => $approved ? MaintenanceCase::STATUS_COMPLETED : MaintenanceCase::STATUS_RETURNED,
                'closed_at' => $approved ? now() : null,
                'last_edited_by' => $actor->id,
            ]);
            WorkflowTransfer::query()->where('operation_id', $operation->id)
                ->where('user_id_to', $actor->id)->where('type', WorkflowTransfer::TYPE_OPERATION_REVIEW_REQUEST)
                ->whereNull('receive_date')->update(['receive_date' => now()]);
            if (! $approved) {
                WorkflowTransfer::query()->create([
                    'user_id_from' => $actor->id,
                    'user_id_to' => $operation->user_id,
                    'riser_id' => $operation->riser_id,
                    'operation_id' => $operation->id,
                    'type' => WorkflowTransfer::TYPE_OPERATION_REVIEW_RETURNED,
                    'title' => 'Field repair report returned for correction',
                    'comment' => $data['comment'],
                    'send_date' => now(),
                ]);
            }
            $this->audit->record('maintenance_case.reviewed', $case, $before, $this->audit->attributesFor($case), [
                'decision' => $data['decision'], 'operation_id' => $operation->id,
            ]);
            $this->audit->record('operation.reviewed', $operation, null, [
                'status' => $operation->status, 'reviewer_id' => $actor->id,
            ]);
        });

        return response()->json(['success' => true, 'status' => $maintenanceCase->fresh()->status]);
    }

    public function update(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless($actor->isSuperAdmin() || ($actor->hasRole('boss') && $actor->can('operation.edit')), 403);
        $data = $request->validate([
            'operation_date' => ['sometimes', 'required', 'date'],
            'operation_category_id' => ['sometimes', 'nullable', 'integer', 'exists:operation_categories,id'],
            'assigned_group_id' => ['sometimes', 'required', 'integer', 'exists:group_users,id'],
            'priority' => ['sometimes', 'required', 'string', Rule::in(["\u{06A9}\u{0645}", "\u{0645}\u{062A}\u{0648}\u{0633}\u{0637}", "\u{0632}\u{06CC}\u{0627}\u{062F}", "\u{0641}\u{0648}\u{0631}\u{06CC}"])],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'materials' => ['sometimes', 'array'],
            'materials.*' => ['numeric', 'gt:0', 'max:99999999.99'],
        ]);
        if (array_key_exists('materials', $data)) {
            $ids = array_map('intval', array_keys($data['materials']));
            if ($ids !== [] && Material::query()->where('is_active', true)->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                abort(422, 'ظ…طµط§ظ„ط­ ط§ظ†طھط®ط§ط¨â€Œط´ط¯ظ‡ ظ…ط¹طھط¨ط± غŒط§ ظپط¹ط§ظ„ ظ†غŒط³طھظ†ط¯.');
            }
        }
        if (array_key_exists('assigned_group_id', $data)) {
            $targetGroupId = (int) $data['assigned_group_id'];
            abort_unless($actor->isSuperAdmin() || $actor->managesGroup($targetGroupId), 403);
            $targetGroup = \App\Models\GroupUser::query()->findOrFail($targetGroupId);
            if (! $actor->isSuperAdmin()) {
                abort_unless((int) $targetGroup->organization_id === (int) $actor->organization_id
                    && (int) $targetGroup->zone_id === (int) $actor->zone_id, 403);
            }
            abort_if($targetGroupId !== (int) $maintenanceCase->assigned_group_id && $maintenanceCase->operation()->exists(), 409,
                'ظ¾ط³ ط§ط² ط«ط¨طھ ط¹ظ…ظ„غŒط§طھطŒ ط§ظ†طھظ‚ط§ظ„ ظ¾ط±ظˆظ†ط¯ظ‡ ط¨ظ‡ ع¯ط±ظˆظ‡ ط¯غŒع¯ط± ظ†غŒط§ط²ظ…ظ†ط¯ ع¯ط±ط¯ط´â€Œع©ط§ط± ط§ط®طھطµط§طµغŒ ط§ط³طھ.');
        }

        DB::transaction(function () use ($actor, $data, $maintenanceCase): void {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            $operation = $case->operation()->lockForUpdate()->first();
            $caseBefore = $this->audit->attributesFor($case);
            $operationBefore = $operation ? $this->audit->attributesFor($operation) : null;
            $materialsBefore = $operation ? $this->materialSnapshot($operation) : null;

            $case->fill(array_intersect_key($data, array_flip(['operation_category_id', 'assigned_group_id', 'priority', 'description'])));
            $case->last_edited_by = $actor->id;
            $case->save();
            if ($operation) {
                $operation->fill(array_intersect_key($data, array_flip(['operation_date', 'operation_category_id', 'priority', 'description'])));
                $operation->save();
                if (array_key_exists('materials', $data)) {
                    $operation->operationMaterial()->detach();
                    foreach ($data['materials'] as $materialId => $quantity) {
                        OperationMaterial::query()->create([
                            'operation_id' => $operation->id,
                            'material_id' => (int) $materialId,
                            'qty' => $quantity,
                            'price' => 0,
                            'total' => 0,
                            'description' => 'Area manager edit; quantities only.',
                        ]);
                    }
                }
                $this->audit->record('operation.area_manager_edited', $operation, $operationBefore, $this->audit->attributesFor($operation));
                if (array_key_exists('materials', $data)) {
                    $this->audit->record('operation.materials_updated', $operation, ['materials' => $materialsBefore], ['materials' => $this->materialSnapshot($operation)]);
                }
            }
            $this->audit->record('maintenance_case.area_manager_edited', $case, $caseBefore, $this->audit->attributesFor($case));
        });

        return response()->json(['success' => true, 'case' => $maintenanceCase->fresh()->load('operation.operationMaterial')]);
    }

    private function authorizeFieldEntry(User $user): void
    {
        abort_unless(! $user->isSuperAdmin() && $user->hasRole('supervisor'), 403);
    }

    /**
     * Versioned mobile writes must never silently fall back to non-idempotent
     * inserts. Legacy web/API paths remain available while the additive
     * migration is pending.
     */
    private function requireMobileIdempotencySchema(Request $request, string $table): void
    {
        if ($request->is('api/v1/supervisor/*') && ! Schema::hasColumn($table, 'client_uuid')) {
            abort(503, 'ثبت موبایلی موقتاً در دسترس نیست؛ به‌روزرسانی پایگاه داده لازم است.');
        }
    }

    private function authorizeFieldOrManager(User $user): void
    {
        abort_unless($user->isSuperAdmin() || $user->hasRole('supervisor') || $user->hasRole('boss')
            || $user->can('maintenance.case.view'), 403);
    }

    private function authorizeCaseView(User $user, MaintenanceCase $case): void
    {
        abort_unless(Riser::query()->visibleTo($user)->whereKey($case->riser_id)->exists(), 404);
        if ($user->isSuperAdmin()) return;
        if ($user->hasRole('boss')) {
            abort_unless($user->can('operation.view') && $user->managesGroup((int) $case->assigned_group_id), 403);
            return;
        }
        abort_unless($user->hasRole('supervisor') || $user->can('maintenance.case.view'), 403);
        abort_unless((int) $user->group_user_id === (int) $case->assigned_group_id, 403);
    }

    private function storePhoto($file, User $actor, Riser $riser, MaintenanceCase $case, ?Operation $operation, string $type): OperationImage
    {
        return OperationImage::query()->create([
            'operation_id' => $operation?->id,
            'maintenance_case_id' => $case->id,
            'riser_id' => $riser->id,
            'type' => $type,
            'path' => $file->store('maintenance-case-photos', 'local'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $actor->id,
        ]);
    }

    private function materialSnapshot(Operation $operation): array
    {
        return $operation->load('operationMaterial')->operationMaterial->map(fn ($material) => [
            'material_id' => $material->id,
            'title' => $material->title,
            'unit' => $material->unit,
            'qty' => (string) $material->pivot->qty,
        ])->values()->all();
    }
}
