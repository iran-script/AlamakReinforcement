<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceCase;
use App\Models\AuditLog;
use App\Models\Material;
use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\OperationMaterial;
use App\Models\OperationSupervisor;
use App\Models\OperationCategory;
use App\Models\Riser;
use App\Models\User;
use App\Models\GroupUser;
use App\Models\WorkflowTransfer;
use App\Services\AuditLogger;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Throwable;

class MaintenanceCaseController extends Controller
{
    public function __construct(private AuditLogger $audit, private WorkflowService $workflow) {}

    public function index(Request $request)
    {
        $this->authorizeFieldOrManager($request->user());
        $user = $request->user();
        $visibleGroupIds = $user->isSuperAdmin() || $user->isGlobalMaintenanceViewer()
            ? null : ($user->hasRole('boss') ? $user->managedGroupIds() : array_filter([(int) $user->group_user_id]));
        abort_unless($user->isSuperAdmin() || $user->isGlobalMaintenanceViewer()
            || ($visibleGroupIds !== [] && $user->zone_id), 403);

        $allowedStatuses = [
            MaintenanceCase::STATUS_OPEN,
            MaintenanceCase::STATUS_IN_PROGRESS,
            MaintenanceCase::STATUS_SUBMITTED,
            MaintenanceCase::STATUS_RETURNED,
            MaintenanceCase::STATUS_COMPLETED,
        ];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in($allowedStatuses)],
            'group' => ['nullable', 'integer', 'min:1'],
            'organization' => ['nullable', 'integer', 'exists:organizations,id'],
            'zone' => ['nullable', 'integer', 'exists:zone,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $baseQuery = fn () => MaintenanceCase::query()
            ->visibleTo($user)
            ->when(! $user->isSuperAdmin() && ! $user->isGlobalMaintenanceViewer(), fn ($query) => $query->whereIn('assigned_group_id', $visibleGroupIds));

        $groupFilterAllowed = ! isset($filters['group'])
            || $user->isSuperAdmin() || $user->isGlobalMaintenanceViewer()
            || in_array((int) $filters['group'], $visibleGroupIds ?? [], true);

        $casesQuery = $baseQuery()
            ->when(! $groupFilterAllowed, fn ($query) => $query->whereRaw('1 = 0'))
            ->when(isset($filters['group']), fn ($query) => $query->where('assigned_group_id', (int) $filters['group']))
            ->when(isset($filters['organization']), fn ($query) => $query->whereHas('riser', fn ($riser) => $riser->whereExists(function ($zone) use ($filters): void {
                $zone->selectRaw('1')->from('zone')->where('zone.organization_id', (int) $filters['organization'])
                    ->whereRaw('zone.geom IS NOT NULL AND riser.geom IS NOT NULL')
                    ->whereRaw('ST_Intersects(zone.geom, riser.geom)');
            })))
            ->when(isset($filters['zone']), fn ($query) => $query->whereHas('riser', fn ($riser) => $riser->whereExists(function ($zone) use ($filters): void {
                $zone->selectRaw('1')->from('zone')->whereKey((int) $filters['zone'])
                    ->whereRaw('zone.geom IS NOT NULL AND riser.geom IS NOT NULL')
                    ->whereRaw('ST_Intersects(zone.geom, riser.geom)');
            })))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('identified_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('identified_at', '<=', $filters['to']))
            ->when(isset($filters['q']) && trim($filters['q']) !== '', function ($query) use ($filters) {
                $term = trim($filters['q']);
                $query->where(function ($nested) use ($term) {
                    $nested->where('maintenance_cases.id', is_numeric($term) ? (int) $term : -1)
                        ->orWhereHas('riser', fn ($riser) => $riser
                            ->where('r_giscode', 'ilike', "%{$term}%")
                            ->orWhere('giscode', 'ilike', "%{$term}%"));
                });
            })
            ->with([
                'riser:id,r_giscode,giscode',
                'reporter:id,name',
                'assignedGroup:id,name',
            ])
            ->withCount('operations')
            ->latest('identified_at');

        $cases = $casesQuery->paginate(25)->withQueryString();
        $statusCounts = collect($allowedStatuses)->mapWithKeys(fn (string $status) => [
            $status => (clone $baseQuery())->where('status', $status)->count(),
        ]);
        $groups = GroupUser::query()
            ->when(! $user->isSuperAdmin() && ! $user->isGlobalMaintenanceViewer(), fn ($query) => $query->whereIn('id', $visibleGroupIds))
            ->orderBy('name')->get(['id', 'name']);
        $organizations = \App\Models\Organization::query()->orderBy('name')->get(['id', 'name']);
        $zones = \App\Models\Zone::query()->with('organization:id,name')->orderBy('name')->get(['id', 'name', 'organization_id']);

        return $request->expectsJson()
            ? response()->json($cases)
            : view('maintenance-cases.index', compact('cases', 'statusCounts', 'groups', 'organizations', 'zones', 'filters'));
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->requireMobileIdempotencySchema($request, 'maintenance_cases');
        $isMobile = $request->is('api/v1/supervisor/*');
        $clientUuidRule = $isMobile ? 'required' : 'nullable';
        abort_if(! $actor->group_user_id || ! $actor->zone_id || ! $actor->organization_id, 422, 'ط¨ط±ط§غŒ ط«ط¨طھ ظ…غŒط¯ط§ظ†غŒطŒ ع¯ط±ظˆظ‡طŒ ط³ط§ط²ظ…ط§ظ† ظˆ Zone ظ†ط§ط¸ط± ط¨ط§غŒط¯ ظ…ط´ط®طµ ط¨ط§ط´ط¯.');
        abort_unless(\App\Models\GroupUser::query()->whereKey($actor->group_user_id)
            ->where('organization_id', $actor->organization_id)->where('zone_id', $actor->zone_id)->exists(), 403,
            'ع¯ط±ظˆظ‡ ع©ط§ط±غŒ ط¨ظ‡ ط³ط§ط²ظ…ط§ظ† غŒط§ Zone ظ†ط§ط¸ط± طھط¹ظ„ظ‚ ظ†ط¯ط§ط±ط¯.');

        $data = $request->validate([
            'riser_id' => ['required', 'integer'],
            'client_uuid' => [$clientUuidRule, 'uuid'],
            'operation_category_id' => ['nullable', 'integer', 'exists:operation_categories,id'],
            'priority' => [$isMobile ? 'required' : 'nullable', 'string', Rule::in(['کم', 'متوسط', 'زیاد', 'فوری'])],
            'description' => ['nullable', 'string', 'max:10000'],
            'identified_at' => [$isMobile ? 'required' : 'nullable', 'date'],
            'photos_before' => ['nullable', 'array', 'max:20'],
            'photos_before.*' => ['image', 'max:10240'],
        ]);
        $markingPhotos = $request->file('photos_before', []);
        if (! $isMobile && (is_array($markingPhotos) ? count($markingPhotos) : ($markingPhotos ? 1 : 0)) > 1) {
            abort(422, 'برای نشانه‌گذاری فقط یک تصویر قبل از تعمیرات مجاز است.');
        }
        $riser = Riser::query()->visibleTo($actor)->findOrFail($data['riser_id']);
        $openStatuses = [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_SUBMITTED, MaintenanceCase::STATUS_RETURNED];
        $existingOpenCase = MaintenanceCase::query()->where('riser_id', $riser->id)
            ->where('assigned_group_id', $actor->group_user_id)
            ->whereIn('status', $openStatuses)->latest('identified_at')->first();
        if ($existingOpenCase && ! $isMobile) {
            return response()->json([
                'case' => $existingOpenCase->load(['riser:id,r_giscode,giscode', 'assignedGroup:id,name']),
                'existing_case' => true,
                'message' => 'برای این علمک پرونده تعمیر باز وجود دارد.',
            ]);
        }
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
                'priority' => $data['priority'] ?? 'normal',
                'description' => $data['description'] ?? null,
                'identified_at' => $data['identified_at'] ?? now(),
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

            // The map reads the riser status directly; keep it synchronized with
            // the real open case without changing operation approval states.
            $riser->update(['status' => 'flagRiser']);
            $transfer = $this->workflow->flagRiser($riser, $actor);
            $this->audit->record('riser.flagged_for_maintenance', $riser, null, [
                'riser_id' => $riser->id, 'maintenance_case_id' => $case->id, 'workflow_transfer_id' => $transfer->id,
            ]);

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
            'operations.operationCategory', 'operations.operationMaterial', 'operations.images', 'operations.user:id,name', 'operations.supervisors:id,name',
        ]);
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            $operationIds = $case->operations->modelKeys();
            $auditLogs = AuditLog::query()
                ->with('actor:id,name')
                ->where(function ($query) use ($case, $operationIds): void {
                    $query->where(function ($caseQuery) use ($case): void {
                        $caseQuery->where('auditable_type', MaintenanceCase::class)->where('auditable_id', $case->id);
                    })->orWhereIn('operation_id', $operationIds);
                })
                ->latest('created_at')->latest('id')->limit(120)->get();
            $workflowTransfers = WorkflowTransfer::query()
                ->with(['fromUser:id,name', 'toUser:id,name', 'operation:id,operation_category_id'])
                ->where('riser_id', $case->riser_id)
                ->where(function ($query) use ($operationIds): void {
                    $query->whereIn('operation_id', $operationIds)->orWhereNull('operation_id');
                })
                ->latest('send_date')->latest('id')->limit(80)->get();
            $timeline = collect()
                ->merge($auditLogs->map(fn (AuditLog $log): array => [
                    'kind' => 'audit',
                    'at' => $log->created_at,
                    'title' => $log->event,
                    'actor' => $log->actor?->name,
                    'detail' => $log->metadata,
                ]))
                ->merge($workflowTransfers->map(fn (WorkflowTransfer $transfer): array => [
                    'kind' => 'workflow',
                    'at' => $transfer->send_date,
                    'title' => $transfer->title ?: $transfer->type,
                    'actor' => $transfer->fromUser?->name,
                    'detail' => $transfer->comment,
                ]))
                ->sortByDesc(fn (array $item) => $item['at']?->timestamp ?? 0)
                ->values();
            $materialsSummary = $case->operations->flatMap->operationMaterial
                ->groupBy('id')
                ->map(fn ($items): array => [
                    'id' => (int) $items->first()->id,
                    'title' => $items->first()->title,
                    'unit' => $items->first()->unit,
                    'qty' => $items->sum(fn ($material): float => (float) $material->pivot->qty),
                    'operations' => $items->map(fn ($material): int => (int) $material->pivot->operation_id)->unique()->values()->all(),
                ])->values();
            $actor = $request->user();
            $canEditOperations = $actor->hasRole('supervisor')
                && (int) $case->assigned_group_id === (int) $actor->group_user_id
                && $case->canAcceptOperations();
            $canSubmitOperations = $canEditOperations && $case->operations->isNotEmpty();
            $canReview = $actor->hasRole('boss') && $actor->can('operation.approve')
                && $actor->managesGroup((int) $case->assigned_group_id)
                && $case->status === MaintenanceCase::STATUS_SUBMITTED;
            $canEditPhotos = $actor->isSuperAdmin()
                || ($actor->hasRole('boss') && $actor->can('operation.edit') && $actor->managesGroup((int) $case->assigned_group_id))
                || ($actor->hasRole('supervisor') && (int) $actor->group_user_id === (int) $case->assigned_group_id && $case->canAcceptOperations());

            return view('maintenance-cases.show', compact(
                'case', 'auditLogs', 'workflowTransfers', 'timeline', 'materialsSummary',
                'canEditOperations', 'canSubmitOperations', 'canReview', 'canEditPhotos'
            ));
        }
        $casePhotos = $case->photos->merge($case->operations->flatMap->images)->unique('id')->values();
        $casePhotos->each(function (OperationImage $photo) use ($request): void {
            $photo->makeHidden(['path', 'original_name']);
            $photo->setAttribute('url', $request->is('api/v1/supervisor/*')
                ? url('/api/v1/supervisor/photos/'.$photo->id)
                : route('maintenance-cases.photos.show', $photo));
        });
        $case->setRelation('photos', $casePhotos);
        $case->setRelation('operations', $case->operations->map(fn (Operation $operation) => [
            'id' => $operation->id, 'status' => $operation->status, 'operation_date' => $operation->operation_date,
            'description' => $operation->description, 'updated_at' => $operation->updated_at?->toISOString(),
            'operation_category' => $operation->operationCategory,
            'created_by' => $operation->user?->name,
            'reviewers' => $operation->supervisors->map(fn (User $reviewer) => [
                'name' => $reviewer->name, 'status' => $reviewer->pivot->status,
                'comment' => $reviewer->pivot->comment, 'approved_at' => $reviewer->pivot->approved_at,
            ])->values(),
            'materials' => $operation->operationMaterial->map(fn ($material) => [
                'id' => $material->id, 'title' => $material->title, 'unit' => $material->unit, 'qty' => (string) $material->pivot->qty,
            ])->values(),
            'photos' => $operation->images->map(fn (OperationImage $image) => [
                'id' => $image->id, 'type' => $image->type, 'url' => $request->is('api/v1/supervisor/*')
                    ? url('/api/v1/supervisor/photos/'.$image->id) : route('maintenance-cases.photos.show', $image),
            ])->values(),
        ])->values());
        $case->setAttribute('materials_total', $this->materialsTotal($maintenanceCase->operations()->with('operationMaterial')->get()));

        return response()->json(['case' => $case]);
    }

    /** Render the RTL grouped operation entry form for an existing case. */
    public function createOperations(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);
        if (! $maintenanceCase->canAcceptOperations()) {
            $message = $this->operationEntryStatusMessage($maintenanceCase);
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'status' => $maintenanceCase->status], 409);
            }

            return redirect()->route('riser.show', ['id' => $maintenanceCase->riser_id])->with('error', $message);
        }
        return view('maintenance-cases.operations-create', [
            'maintenanceCase' => $maintenanceCase->load('riser'),
            'operationCategories' => OperationCategory::query()->where('is_active', true)->orderBy('sort')->orderBy('title')->get(['id', 'title', 'code']),
            'materialsPayload' => Material::query()->with('category:id,title')->where('is_active', true)->orderBy('title')->get(['id', 'material_category_id', 'title', 'code', 'unit'])->map(fn (Material $material): array => [
                'id' => (int) $material->id,
                'category_id' => $material->material_category_id ? (int) $material->material_category_id : null,
                'category_title' => $material->category?->title,
                'title' => $material->title,
                'code' => $material->code,
                'unit' => $material->unit,
            ])->values()->all(),
        ]);
    }

    /** Store all selected operations and their materials atomically as drafts. */
    public function storeOperations(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);
        if (! $maintenanceCase->canAcceptOperations()) {
            $message = $this->operationEntryStatusMessage($maintenanceCase);
            if (! $request->expectsJson()) {
                return redirect()->route('riser.show', ['id' => $maintenanceCase->riser_id])->with('error', $message);
            }

            return response()->json([
                'message' => $message,
                'status' => $maintenanceCase->status,
            ], 409);
        }
        $riserDetailsUrl = route('riser.show', ['id' => $maintenanceCase->riser_id]);

        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.operation_category_id' => ['required', 'integer', 'exists:operation_categories,id'],
            'operations.*.operation_date' => ['required', 'date'],
            'operations.*.description' => ['nullable', 'string', 'max:10000'],
            'operations.*.materials' => ['nullable', 'array', 'max:100'],
            'operations.*.materials.*.material_id' => ['required', 'integer', 'distinct'],
            'operations.*.materials.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'photos_before' => ['nullable', 'image', 'max:10240'],
            'photos_after' => ['nullable', 'image', 'max:10240'],
        ], $this->batchValidationMessages());

        $categoryIds = collect($data['operations'])->pluck('operation_category_id')->map(fn ($id) => (int) $id)->unique()->values();
        abort_unless(OperationCategory::query()->where('is_active', true)->whereIn('id', $categoryIds)->count() === $categoryIds->count(), 422, 'یکی از انواع عملیات انتخاب‌شده فعال نیست.');
        $materialIds = collect($data['operations'])->flatMap(fn ($operation) => collect($operation['materials'] ?? [])->pluck('material_id'))->map(fn ($id) => (int) $id)->unique()->values();
        abort_unless(Material::query()->where('is_active', true)->whereIn('id', $materialIds)->count() === $materialIds->count(), 422, 'یکی از مصالح انتخاب‌شده معتبر یا فعال نیست.');

        $supportsIdempotency = Schema::hasColumn('operations', 'client_uuid');
        abort_unless($supportsIdempotency, 503, 'ثبت گروهی تا زمان اعمال migration کلیدهای idempotency در دسترس نیست.');
        if ($supportsIdempotency) {
            $existing = Operation::query()->where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                abort_unless((int) $existing->maintenance_case_id === (int) $maintenanceCase->id && (int) $existing->user_id === (int) $actor->id, 409);
                $existingOperations = $maintenanceCase->operations()->with(['operationCategory', 'operationMaterial'])->get();
                $message = count($existingOperations).' عملیات تعمیراتی قبلاً با موفقیت ثبت شده است.';
                if ($request->expectsJson()) {
                    session()->flash('success', $message);
                    return response()->json([
                        'success' => true,
                        'operations' => $existingOperations,
                        'idempotent_replay' => true,
                        'redirect_url' => $riserDetailsUrl,
                        'message' => $message,
                    ]);
                }

                return redirect()->to($riserDetailsUrl)->with('success', $message);
            }
        }

        $storedPhotoPaths = [];
        $replacedPhotoPaths = [];
        try {
            $created = DB::transaction(function () use ($data, $request, $actor, $maintenanceCase, $supportsIdempotency, &$storedPhotoPaths, &$replacedPhotoPaths): array {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_unless((int) $case->assigned_group_id === (int) $actor->group_user_id, 403);
            abort_unless($case->canAcceptOperations(), 409, $this->operationEntryStatusMessage($case));
            $beforeCase = $this->audit->attributesFor($case);
            $operations = [];
            foreach ($data['operations'] as $operationIndex => $operationData) {
                $attributes = [
                    'riser_id' => $case->riser_id,
                    'maintenance_case_id' => $case->id,
                    'group_user_id' => $case->assigned_group_id,
                    'operation_category_id' => $operationData['operation_category_id'],
                    'operation_date' => $operationData['operation_date'],
                    'status' => 'draft',
                    'priority' => $case->priority,
                    'description' => $operationData['description'] ?? null,
                    'user_id' => $actor->id,
                    'contract_id' => $actor->contract_id,
                ];
                // The first operation carries the batch key. Remaining operations
                // get their own keys because operations.client_uuid is unique.
                if ($supportsIdempotency) {
                    $attributes['client_uuid'] = $operationIndex === array_key_first($data['operations'])
                        ? $data['client_uuid']
                        : (string) Str::uuid();
                }
                $operation = Operation::query()->create($attributes);
                $this->replaceOperationMaterialsFromList($operation, $operationData['materials'] ?? []);
                $this->audit->record('operation.created', $operation, null, $this->audit->attributesFor($operation));
                $this->audit->record('operation.materials_updated', $operation, null, ['materials' => $this->materialSnapshot($operation)]);
                $operations[] = $operation->load(['operationCategory', 'operationMaterial', 'images']);
            }

            foreach (['before', 'after'] as $photoType) {
                $file = $request->file('photos_'.$photoType);
                if (! $file) {
                    continue;
                }

                $existingPhoto = $case->photos()->whereNull('operation_id')->where('type', $photoType)->orderBy('id')->first();
                if ($existingPhoto && $this->sameStoredPhoto($file, $existingPhoto)) {
                    continue;
                }

                $path = $file->store('maintenance-case-photos', 'local');
                $storedPhotoPaths[] = $path;
                if ($existingPhoto) {
                    $replacedPhotoPaths[] = $existingPhoto->path;
                    $existingPhoto->update(['path' => $path, 'original_name' => $file->getClientOriginalName(), 'uploaded_by' => $actor->id]);
                    $photo = $existingPhoto->refresh();
                } else {
                    $photo = OperationImage::query()->create([
                        'operation_id' => null,
                        'maintenance_case_id' => $case->id,
                        'riser_id' => $case->riser_id,
                        'type' => $photoType,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'uploaded_by' => $actor->id,
                    ]);
                }
                $this->audit->record('maintenance_case.photo_added', $photo, null, [
                    'photo_id' => $photo->id, 'type' => $photoType, 'maintenance_case_id' => $case->id,
                ]);
            }
            $case->update(['status' => MaintenanceCase::STATUS_IN_PROGRESS, 'last_edited_by' => $actor->id]);
            $this->audit->record('maintenance_case.operations_batch_created', $case, $beforeCase, $this->audit->attributesFor($case), ['operation_ids' => array_map(fn (Operation $operation) => $operation->getKey(), $operations)]);
            return $operations;
            });
        } catch (Throwable $exception) {
            foreach ($storedPhotoPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        foreach ($replacedPhotoPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        $message = count($created).' عملیات تعمیراتی با موفقیت ثبت شد.';
        if (! $request->expectsJson()) {
            return redirect()->to($riserDetailsUrl)->with('success', $message);
        }

        session()->flash('success', $message);

        return response()->json([
            'success' => true,
            'operations' => $created,
            'materials_total' => $this->materialsTotal(collect($created)),
            'redirect_url' => $riserDetailsUrl,
            'message' => $message,
        ], 201);
    }

    public function photo(Request $request, OperationImage $operationPhoto)
    {
        abort_unless($operationPhoto->maintenance_case_id, 404);
        $case = MaintenanceCase::query()->findOrFail($operationPhoto->maintenance_case_id);
        $this->authorizeCaseView($request->user(), $case);
        abort_unless(Storage::disk('local')->exists($operationPhoto->path), 404);

        return response()->file(Storage::disk('local')->path($operationPhoto->path));
    }

    public function storeCasePhoto(Request $request, MaintenanceCase $maintenanceCase, string $type)
    {
        abort_unless(in_array($type, ['before', 'after'], true), 404);
        $actor = $request->user();
        $this->authorizeCaseView($actor, $maintenanceCase);
        $this->authorizeCasePhotoChange($actor, $maintenanceCase);
        if ($type === 'after') {
            abort_unless($maintenanceCase->operations()->exists(), 422, 'ابتدا حداقل یک عملیات تعمیراتی ثبت کنید.');
        }

        $data = $request->validate(['photo' => ['required', 'image', 'max:10240']]);
        $newPath = $data['photo']->store('maintenance-case-photos', 'local');
        $oldPath = null;
        $photo = null;

        try {
            DB::transaction(function () use ($maintenanceCase, $type, $data, $newPath, &$oldPath, &$photo): void {
                $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
                $existing = $case->photos()->whereNull('operation_id')->where('type', $type)->orderBy('id')->first();
                if ($existing && $this->sameStoredPhoto($data['photo'], $existing)) {
                    Storage::disk('local')->delete($newPath);
                    $photo = $existing;
                    return;
                }

                if ($existing) {
                    $oldPath = $existing->path;
                    $existing->update([
                        'path' => $newPath,
                        'original_name' => $data['photo']->getClientOriginalName(),
                        'uploaded_by' => auth()->id(),
                    ]);
                    $photo = $existing->refresh();
                    return;
                }

                $photo = OperationImage::query()->create([
                    'operation_id' => null,
                    'maintenance_case_id' => $case->id,
                    'riser_id' => $case->riser_id,
                    'type' => $type,
                    'path' => $newPath,
                    'original_name' => $data['photo']->getClientOriginalName(),
                    'uploaded_by' => auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'success' => true,
            'photo' => [
                'id' => $photo->id,
                'type' => $photo->type,
                'url' => route('maintenance-cases.photos.show', $photo),
            ],
        ]);
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

    /** Store one editable operation under an existing repair case. */
    public function storeOperation(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->requireMobileIdempotencySchema($request, 'operations');
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);

        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'operation_date' => ['required', 'date'],
            'operation_category_id' => ['required', 'integer', 'exists:operation_categories,id'],
            'description' => ['nullable', 'string', 'max:10000'],
            'materials' => ['nullable', 'array'],
            'materials.*' => ['numeric', 'gt:0', 'max:99999999.99'],
            'photos_after' => ['nullable', 'array', 'max:20'],
            'photos_after.*' => ['image', 'max:10240'],
        ]);

        abort_unless(in_array($maintenanceCase->status, [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_RETURNED], true), 409);
        $existing = Operation::query()->where('client_uuid', $data['client_uuid'])->first();
        if ($existing) {
            abort_unless((int) $existing->maintenance_case_id === (int) $maintenanceCase->id
                && (int) $existing->user_id === (int) $actor->id, 409);
            return response()->json(['operation' => $existing->load(['operationMaterial', 'operationCategory', 'images']), 'idempotent_replay' => true]);
        }

        $materialIds = array_map('intval', array_keys($data['materials'] ?? []));
        abort_if($materialIds !== [] && Material::query()->where('is_active', true)->whereIn('id', $materialIds)->count() !== count(array_unique($materialIds)), 422, 'One or more materials are unavailable.');
        $operation = DB::transaction(function () use ($request, $data, $actor, $maintenanceCase): Operation {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_unless((int) $case->assigned_group_id === (int) $actor->group_user_id, 403);
            abort_unless(in_array($case->status, [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_RETURNED], true), 409);
            $operation = Operation::query()->create([
                'riser_id' => $case->riser_id,
                'maintenance_case_id' => $case->id,
                'group_user_id' => $case->assigned_group_id,
                'operation_category_id' => $data['operation_category_id'],
                'operation_date' => $data['operation_date'],
                'status' => 'draft',
                'priority' => $case->priority,
                'description' => $data['description'] ?? null,
                'user_id' => $actor->id,
                'contract_id' => $actor->contract_id,
                'client_uuid' => $data['client_uuid'],
            ]);
            $this->replaceOperationMaterials($operation, $data['materials'] ?? []);
            $this->audit->record('operation.created', $operation, null, $this->audit->attributesFor($operation));
            $this->audit->record('operation.materials_updated', $operation, null, ['materials' => $this->materialSnapshot($operation)]);
            foreach ($request->file('photos_after', []) as $file) {
                $photo = $this->storePhoto($file, $actor, $case->riser, $case, $operation, 'after');
                $this->audit->record('operation.photo_added', $photo, null, ['photo_id' => $photo->id, 'operation_id' => $operation->id]);
            }
            $before = $this->audit->attributesFor($case);
            $case->update(['status' => MaintenanceCase::STATUS_IN_PROGRESS, 'last_edited_by' => $actor->id]);
            $this->audit->record('maintenance_case.operation_added', $case, $before, $this->audit->attributesFor($case), ['operation_id' => $operation->id]);
            return $operation->load(['operationMaterial', 'operationCategory', 'images']);
        });

        return response()->json(['operation' => $operation], 201);
    }

    /** Render the tablet-first grouped editor for an existing maintenance case. */
    public function editOperations(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);
        abort_unless($maintenanceCase->canAcceptOperations(), 409, $this->operationEntryStatusMessage($maintenanceCase));

        $maintenanceCase->load(['riser', 'operations.operationCategory', 'operations.operationMaterial']);

        return view('maintenance-cases.operations-create', [
            'maintenanceCase' => $maintenanceCase,
            'editing' => true,
            'operationCategories' => OperationCategory::query()->where('is_active', true)->orderBy('sort')->orderBy('title')->get(['id', 'title', 'code']),
            'materialsPayload' => Material::query()->with('category:id,title')->where('is_active', true)->orderBy('title')->get(['id', 'material_category_id', 'title', 'code', 'unit'])->map(fn (Material $material): array => [
                'id' => (int) $material->id,
                'category_id' => $material->material_category_id ? (int) $material->material_category_id : null,
                'category_title' => $material->category?->title,
                'title' => $material->title,
                'code' => $material->code,
                'unit' => $material->unit,
            ])->values()->all(),
            'operationsPayload' => $maintenanceCase->operations->map(fn (Operation $operation): array => [
                'id' => (int) $operation->id,
                'version' => $operation->updated_at?->toISOString(),
                'operation_category_id' => (int) $operation->operation_category_id,
                'operation_date' => $operation->operation_date,
                'description' => $operation->description,
                'status' => $operation->status,
                'materials' => $operation->operationMaterial->mapWithKeys(fn (Material $material): array => [(string) $material->id => (float) $material->pivot->qty])->all(),
            ])->values()->all(),
        ]);
    }

    /** Update all draft/revision operations belonging to a maintenance case atomically. */
    public function updateOperations(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);
        abort_unless($maintenanceCase->canAcceptOperations(), 409, $this->operationEntryStatusMessage($maintenanceCase));

        $data = $request->validate([
            'operations' => ['required', 'array', 'max:50'],
            'operations.*.id' => ['nullable', 'integer', 'distinct'],
            'operations.*.client_uuid' => ['nullable', 'uuid', 'distinct'],
            'operations.*.operation_category_id' => ['required', 'integer', 'exists:operation_categories,id'],
            'operations.*.operation_date' => ['required', 'date'],
            'operations.*.description' => ['nullable', 'string', 'max:10000'],
            'operations.*.version' => ['nullable', 'date'],
            'operations.*.materials' => ['nullable', 'array', 'max:100'],
            'operations.*.materials.*.material_id' => ['required', 'integer', 'distinct'],
            'operations.*.materials.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'deleted_operation_ids' => ['nullable', 'array', 'max:50'],
            'deleted_operation_ids.*' => ['integer', 'distinct'],
        ], $this->batchValidationMessages());

        $categoryIds = collect($data['operations'])->pluck('operation_category_id')->map(fn ($id) => (int) $id)->unique();
        abort_unless(OperationCategory::query()->where('is_active', true)->whereIn('id', $categoryIds)->count() === $categoryIds->count(), 422, 'یکی از انواع عملیات انتخاب‌شده فعال نیست.');
        $materialIds = collect($data['operations'])->flatMap(fn ($operation) => collect($operation['materials'] ?? [])->pluck('material_id'))->map(fn ($id) => (int) $id)->unique();
        abort_unless(Material::query()->where('is_active', true)->whereIn('id', $materialIds)->count() === $materialIds->count(), 422, 'یکی از مصالح انتخاب‌شده معتبر یا فعال نیست.');

        $updatedCase = DB::transaction(function () use ($data, $actor, $maintenanceCase): MaintenanceCase {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_unless((int) $case->assigned_group_id === (int) $actor->group_user_id, 403);
            abort_unless($case->canAcceptOperations(), 409, $this->operationEntryStatusMessage($case));
            $allowedStatuses = ['draft', 'need_revision'];
            $existing = $case->operations()->lockForUpdate()->get()->keyBy('id');
            $submittedIds = collect($data['operations'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $deletedIds = collect($data['deleted_operation_ids'] ?? [])->map(fn ($id) => (int) $id);
            abort_if($submittedIds->intersect($deletedIds)->isNotEmpty(), 422, 'یک عملیات نمی‌تواند هم‌زمان ویرایش و حذف شود.');

            foreach ($submittedIds->merge($deletedIds)->unique() as $operationId) {
                $operation = $existing->get($operationId);
                abort_unless($operation && in_array($operation->status, $allowedStatuses, true), 409, 'یکی از عملیات قابل ویرایش نیست.');
                if ($deletedIds->contains($operationId)) {
                    abort_unless(! $operation->images()->exists() && ! $operation->workflowTransfers()->exists(), 409, 'عملیاتی که عکس یا سابقه گردش‌کار دارد قابل حذف نیست.');
                }
            }

            foreach ($deletedIds as $operationId) {
                $operation = $existing->get($operationId);
                $before = $this->audit->attributesFor($operation);
                $operation->delete();
                $this->audit->record('operation.soft_deleted', $operation, $before, [
                    'deleted_at' => $operation->deleted_at?->toISOString(),
                ], [
                    'deletion_mode' => 'soft_delete',
                    'maintenance_case_id' => $case->id,
                ]);
            }

            foreach ($data['operations'] as $payload) {
                $operation = ! empty($payload['id']) ? $existing->get((int) $payload['id']) : null;
                if (! $operation && empty($payload['id']) && ! empty($payload['client_uuid'])) {
                    $operation = $existing->first(fn (Operation $candidate): bool => $candidate->client_uuid === $payload['client_uuid']);
                    if ($operation) {
                        // A retry after a lost response is idempotent for newly-created
                        // operations. Do not rewrite it or create a duplicate row.
                        continue;
                    }
                }
                if ($operation) {
                    abort_unless(in_array($operation->status, $allowedStatuses, true), 409, 'یکی از عملیات قابل ویرایش نیست.');
                    $before = $this->audit->attributesFor($operation);
                    if (! empty($payload['version']) && $operation->updated_at?->toISOString() !== \Illuminate\Support\Carbon::parse($payload['version'])->toISOString()) {
                        abort(409, 'اطلاعات یکی از عملیات در جای دیگری تغییر کرده است؛ صفحه را تازه‌سازی کنید.');
                    }
                    $operation->fill(collect($payload)->only(['operation_category_id', 'operation_date', 'description'])->all());
                    $operation->save();
                    $this->audit->record('operation.updated', $operation, $before, $this->audit->attributesFor($operation));
                } else {
                    $operation = Operation::query()->create([
                        'riser_id' => $case->riser_id,
                        'maintenance_case_id' => $case->id,
                        'group_user_id' => $case->assigned_group_id,
                        'operation_category_id' => $payload['operation_category_id'],
                        'operation_date' => $payload['operation_date'],
                        'status' => 'draft',
                        'priority' => $case->priority,
                        'description' => $payload['description'] ?? null,
                        'user_id' => $actor->id,
                        'contract_id' => $actor->contract_id,
                        'client_uuid' => $payload['client_uuid'] ?? (string) Str::uuid(),
                    ]);
                    $this->audit->record('operation.created', $operation, null, $this->audit->attributesFor($operation));
                }
                $this->replaceOperationMaterials($operation, collect($payload['materials'] ?? [])->mapWithKeys(fn ($material) => [(string) $material['material_id'] => $material['qty']])->all());
                $this->audit->record('operation.materials_updated', $operation, null, ['materials' => $this->materialSnapshot($operation)]);
            }

            $case->update(['last_edited_by' => $actor->id]);
            return $case->fresh()->load('operations.operationMaterial');
        });

        return response()->json(['success' => true, 'case' => $updatedCase, 'redirect_url' => route('riser.show', ['id' => $updatedCase->riser_id])]);
    }

    /** Edit an unsubmitted operation using an updated_at concurrency token. */
    public function updateOperation(Request $request, MaintenanceCase $maintenanceCase, Operation $operation)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id
            && (int) $operation->maintenance_case_id === (int) $maintenanceCase->id, 404);
        abort_unless(in_array($maintenanceCase->status, [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_RETURNED], true)
            && in_array($operation->status, ['draft', 'need_revision'], true), 409);
        $data = $request->validate([
            'version' => ['required', 'date'],
            'operation_date' => ['sometimes', 'required', 'date'],
            'operation_category_id' => ['sometimes', 'required', 'integer', 'exists:operation_categories,id'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'materials' => ['sometimes', 'array'],
            'materials.*' => ['numeric', 'gt:0', 'max:99999999.99'],
            'photos_after' => ['nullable', 'array', 'max:20'],
            'photos_after.*' => ['image', 'max:10240'],
        ]);
        if (! $operation->updated_at || $operation->updated_at->toISOString() !== \Illuminate\Support\Carbon::parse($data['version'])->toISOString()) {
            return response()->json(['message' => 'This operation changed elsewhere. Refresh before editing.'], 409);
        }
        $materialIds = array_map('intval', array_keys($data['materials'] ?? []));
        abort_if($materialIds !== [] && Material::query()->where('is_active', true)->whereIn('id', $materialIds)->count() !== count(array_unique($materialIds)), 422, 'One or more materials are unavailable.');

        $updated = DB::transaction(function () use ($request, $data, $actor, $maintenanceCase, $operation): Operation {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_unless((int) $case->assigned_group_id === (int) $actor->group_user_id, 403);
            abort_unless(in_array($case->status, [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_RETURNED], true), 409);
            $locked = Operation::query()->lockForUpdate()->where('maintenance_case_id', $maintenanceCase->id)->findOrFail($operation->id);
            abort_unless(in_array($locked->status, ['draft', 'need_revision'], true), 409);
            if ($locked->updated_at->toISOString() !== \Illuminate\Support\Carbon::parse($data['version'])->toISOString()) {
                abort(409, 'This operation changed elsewhere. Refresh before editing.');
            }
            $before = $this->audit->attributesFor($locked);
            $materialsBefore = $this->materialSnapshot($locked);
            $locked->fill(array_intersect_key($data, array_flip(['operation_date', 'operation_category_id', 'description'])));
            $locked->save();
            if (array_key_exists('materials', $data)) $this->replaceOperationMaterials($locked, $data['materials']);
            $this->audit->record('operation.updated', $locked, $before, $this->audit->attributesFor($locked));
            if (array_key_exists('materials', $data)) $this->audit->record('operation.materials_updated', $locked, ['materials' => $materialsBefore], ['materials' => $this->materialSnapshot($locked)]);
            $caseBefore = $this->audit->attributesFor($case);
            $case->update(['last_edited_by' => $actor->id]);
            $this->audit->record('maintenance_case.operation_updated', $case, $caseBefore, $this->audit->attributesFor($case), ['operation_id' => $locked->id]);
            foreach ($request->file('photos_after', []) as $file) {
                $photo = $this->storePhoto($file, $actor, $case->riser, $case, $locked, 'after');
                $this->audit->record('operation.photo_added', $photo, null, ['photo_id' => $photo->id, 'operation_id' => $locked->id]);
            }
            return $locked->load(['operationMaterial', 'operationCategory', 'images']);
        });
        return response()->json(['operation' => $updated]);
    }

    /** Submit all draft/revision operations for the case as one review batch. */
    public function submitOperations(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeFieldEntry($actor);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless((int) $maintenanceCase->assigned_group_id === (int) $actor->group_user_id, 403);
        $boss = $this->resolveReviewBoss($actor, $maintenanceCase);
        abort_unless($boss, 422, 'برای گروه این پرونده، رئیس مجاز همان سازمان و Zone تعریف نشده است. تنظیم Boss گروه را بررسی کنید.');

        $case = DB::transaction(function () use ($actor, $boss, $maintenanceCase): MaintenanceCase {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_unless((int) $case->assigned_group_id === (int) $actor->group_user_id, 403);
            abort_unless(in_array($case->status, [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_RETURNED], true), 409, 'این پرونده در وضعیت فعلی قابل ارسال نیست.');
            $operations = $case->operations()->lockForUpdate()->whereIn('status', ['draft', 'need_revision'])->get();
            abort_if($operations->isEmpty(), 422, 'برای ارسال، حداقل یک عملیات در وضعیت پیش‌نویس یا نیازمند اصلاح لازم است.');
            foreach ($operations as $operation) {
                $operation->update(['status' => 'submitOperation']);
                OperationSupervisor::query()->updateOrCreate(['operation_id' => $operation->id, 'user_id' => $boss->id], [
                    'status' => 'pending', 'comment' => null, 'approved_at' => null, 'order' => 1,
                ]);
                WorkflowTransfer::query()->create([
                    'user_id_from' => $actor->id, 'user_id_to' => $boss->id,
                    'riser_id' => $case->riser_id, 'operation_id' => $operation->id,
                    'type' => WorkflowTransfer::TYPE_OPERATION_REVIEW_REQUEST,
                    'title' => 'Field repair report submitted for area review',
                    'comment' => $operation->description ?? $case->description, 'send_date' => now(),
                ]);
            }
            $before = $this->audit->attributesFor($case);
            $case->update(['status' => MaintenanceCase::STATUS_SUBMITTED, 'submitted_at' => now(), 'last_edited_by' => $actor->id]);
            $this->audit->record('maintenance_case.submitted', $case, $before, $this->audit->attributesFor($case), ['operation_ids' => $operations->modelKeys()]);
            return $case;
        });
        return response()->json(['success' => true, 'case' => $case->load('operations.operationMaterial')]);
    }

    public function review(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        abort_unless($actor->hasRole('boss') && $actor->can('operation.approve'), 403);
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless($actor->managesGroup((int) $maintenanceCase->assigned_group_id), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'returned'])],
            'operation_id' => ['nullable', 'integer', 'exists:operations,id'],
            'comment' => ['nullable', 'required_if:decision,returned', 'string', 'max:5000'],
        ]);

        $caseOperations = $maintenanceCase->operations();
        if ($data['operation_id'] ?? null) {
            $operation = $caseOperations->whereKey($data['operation_id'])->firstOrFail();
        } else {
            abort_if($caseOperations->count() > 1, 422, 'Select an operation to review.');
            $operation = $caseOperations->firstOrFail();
        }
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
            $hasRevision = $case->operations()->where('status', 'need_revision')->exists();
            $hasPending = $case->operations()->where('status', '!=', 'approved')->exists();
            $case->update([
                'status' => $hasRevision ? MaintenanceCase::STATUS_RETURNED : ($hasPending ? MaintenanceCase::STATUS_SUBMITTED : MaintenanceCase::STATUS_COMPLETED),
                'closed_at' => $approved && ! $hasPending ? now() : null,
                'last_edited_by' => $actor->id,
            ]);
            Riser::query()->whereKey($case->riser_id)->update([
                'status' => $case->status === MaintenanceCase::STATUS_COMPLETED ? 'submitOperation' : 'flagRiser',
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

    /** Final case-level decision used by the authorized Boss review queue. */
    public function finalReview(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        abort_unless($actor->hasRole('boss') && $actor->can('operation.approve'), 403, 'دسترسی تأیید نهایی تعمیرات برای شما مجاز نیست.');
        $this->authorizeCaseView($actor, $maintenanceCase);
        abort_unless($actor->managesGroup((int) $maintenanceCase->assigned_group_id), 403, 'این پرونده خارج از گروه‌های مدیریتی شماست.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'returned'])],
            'comment' => ['nullable', 'required_if:decision,returned', 'string', 'max:5000'],
        ], [
            'decision.required' => 'تصمیم تأیید پرونده الزامی است.',
            'comment.required_if' => 'برای برگشت پرونده، دلیل الزامی است.',
            'comment.max' => 'دلیل برگشت نمی‌تواند بیشتر از ۵۰۰۰ کاراکتر باشد.',
        ]);

        $case = DB::transaction(function () use ($actor, $data, $maintenanceCase): MaintenanceCase {
            $case = MaintenanceCase::query()->lockForUpdate()->findOrFail($maintenanceCase->id);
            abort_unless($actor->managesGroup((int) $case->assigned_group_id), 403);
            abort_unless($case->status === MaintenanceCase::STATUS_SUBMITTED, 409, 'این پرونده دیگر در انتظار تأیید نیست.');
            $operations = $case->operations()->lockForUpdate()->get();
            abort_if($operations->isEmpty(), 422, 'پرونده بدون عملیات معتبر قابل تأیید نیست.');
            abort_if($operations->contains(fn (Operation $operation): bool => ! in_array($operation->status, ['submitOperation', 'approved'], true)), 409, 'همه عملیات پرونده در وضعیت قابل تأیید نیستند.');
            abort_if($case->photos()->whereNull('operation_id')->whereIn('type', ['before', 'after'])->get()->groupBy('type')->contains(fn ($photos): bool => $photos->count() > 1), 409, 'تعداد تصاویر سطح پرونده معتبر نیست.');

            $approved = $data['decision'] === 'approved';
            foreach ($operations as $operation) {
                $pivot = OperationSupervisor::query()
                    ->where('operation_id', $operation->id)
                    ->where('user_id', $actor->id)
                    ->lockForUpdate()->first();
                abort_unless($pivot, 409, 'مسیر تأیید این عملیات برای رئیس جاری ثبت نشده است.');
                $pivot->update([
                    'status' => $approved ? 'approved' : 'need_revision',
                    'comment' => $data['comment'] ?? null,
                    'approved_at' => $approved ? now() : null,
                ]);
                $operation->update(['status' => $approved ? 'approved' : 'need_revision']);
                WorkflowTransfer::query()
                    ->where('operation_id', $operation->id)
                    ->where('user_id_to', $actor->id)
                    ->where('type', WorkflowTransfer::TYPE_OPERATION_REVIEW_REQUEST)
                    ->whereNull('receive_date')
                    ->update(['receive_date' => now()]);

                if (! $approved) {
                    WorkflowTransfer::query()->create([
                        'user_id_from' => $actor->id,
                        'user_id_to' => $operation->user_id ?: $actor->id,
                        'riser_id' => $operation->riser_id,
                        'operation_id' => $operation->id,
                        'type' => WorkflowTransfer::TYPE_OPERATION_REVIEW_RETURNED,
                        'title' => 'Field repair report returned for correction',
                        'comment' => $data['comment'],
                        'send_date' => now(),
                    ]);
                }
            }

            $before = $this->audit->attributesFor($case);
            $case->update([
                'status' => $approved ? MaintenanceCase::STATUS_COMPLETED : MaintenanceCase::STATUS_RETURNED,
                'closed_at' => $approved ? now() : null,
                'last_edited_by' => $actor->id,
            ]);
            Riser::query()->whereKey($case->riser_id)->update([
                'status' => $approved ? 'submitOperation' : 'flagRiser',
            ]);
            $this->audit->record(
                $approved ? 'maintenance_case.final_approved' : 'maintenance_case.returned_for_correction',
                $case,
                $before,
                $this->audit->attributesFor($case),
                ['operation_ids' => $operations->modelKeys(), 'comment' => $data['comment'] ?? null]
            );

            return $case;
        });

        if (! $request->expectsJson()) {
            return redirect()->route('boss.maintenance-cases.index')->with('success', $data['decision'] === 'approved' ? 'تعمیرات با موفقیت تأیید نهایی شد.' : 'پرونده برای اصلاح برگشت داده شد.');
        }

        return response()->json(['success' => true, 'status' => $case->status]);
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
        $operationSpecificChanges = array_intersect(['operation_date', 'operation_category_id', 'materials'], array_keys($data));
        abort_if($operationSpecificChanges !== [] && $maintenanceCase->operations()->count() > 1, 409,
            'Edit an individual operation when this case contains multiple operations.');
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
            $operation = $case->operations()->when($case->operations()->count() === 1)->lockForUpdate()->first();
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

        return response()->json(['success' => true, 'case' => $maintenanceCase->fresh()->load('operations.operationMaterial')]);
    }

    private function operationEntryStatusMessage(MaintenanceCase $case): string
    {
        return match ($case->status) {
            MaintenanceCase::STATUS_SUBMITTED => 'این پرونده برای بررسی ارسال شده و تا تعیین تکلیف قابل ویرایش نیست.',
            MaintenanceCase::STATUS_COMPLETED => 'این پرونده نهایی و بسته شده است و ثبت عملیات جدید برای آن مجاز نیست.',
            default => 'وضعیت فعلی پرونده اجازه ثبت عملیات جدید را نمی‌دهد.',
        };
    }

    private function authorizeFieldEntry(User $user): void
    {
        abort_unless(! $user->isSuperAdmin() && $user->hasRole('supervisor'), 403);
    }

    private function resolveReviewBoss(User $actor, MaintenanceCase $case): ?User
    {
        $group = GroupUser::query()
            ->whereKey($case->assigned_group_id)
            ->where('organization_id', $actor->organization_id)
            ->where('zone_id', $actor->zone_id)
            ->first();
        if (! $group) {
            return null;
        }

        return User::query()
            ->where('organization_id', $actor->organization_id)
            ->where('zone_id', $actor->zone_id)
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'boss')->where('guard_name', 'web'))
            ->where(function ($permissions): void {
                $permissions->whereHas('permissions', fn ($query) => $query->where('name', 'operation.approve')->where('guard_name', 'web'))
                    ->orWhereHas('roles.permissions', fn ($query) => $query->where('name', 'operation.approve')->where('guard_name', 'web'));
            })
            ->where(function ($groups) use ($group): void {
                $groups->whereHas('managedGroups', fn ($managed) => $managed->whereKey($group->id))
                    // Compatibility for a scoped Boss who belongs to a group
                    // created after the managed-group pivot was backfilled.
                    ->orWhere('group_user_id', $group->id);
            })
            ->orderBy('id')
            ->first();
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
            || $user->can('maintenance.case.view') || $user->isGlobalMaintenanceViewer(), 403);
    }

    private function authorizeCaseView(User $user, MaintenanceCase $case): void
    {
        abort_unless(Riser::query()->visibleTo($user)->whereKey($case->riser_id)->exists(), 404);
        if ($user->isSuperAdmin()) return;
        if ($user->isGlobalMaintenanceViewer()) {
            abort_unless($user->can('maintenance.case.view'), 403);
            return;
        }
        if ($user->hasRole('boss')) {
            abort_unless($user->can('operation.view') && $user->managesGroup((int) $case->assigned_group_id), 403);
            return;
        }
        abort_unless($user->hasRole('supervisor') || $user->can('maintenance.case.view'), 403);
        abort_unless((int) $user->group_user_id === (int) $case->assigned_group_id, 403);
    }

    private function authorizeCasePhotoChange(User $user, MaintenanceCase $case): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->hasRole('boss')) {
            abort_unless($user->can('operation.edit') && $user->managesGroup((int) $case->assigned_group_id), 403);
            return;
        }

        abort_unless($user->hasRole('supervisor') && (int) $user->group_user_id === (int) $case->assigned_group_id, 403);
        abort_unless(in_array($case->status, MaintenanceCase::OPERATION_ENTRY_STATUSES, true), 403, 'تصاویر این پرونده در وضعیت فعلی قابل تغییر نیستند.');
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

    private function sameStoredPhoto($file, OperationImage $photo): bool
    {
        if (! Storage::disk('local')->exists($photo->path) || ! $file->getRealPath()) {
            return false;
        }

        return hash_file('sha256', $file->getRealPath()) === hash('sha256', Storage::disk('local')->get($photo->path));
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

    private function replaceOperationMaterials(Operation $operation, array $materials): void
    {
        $operation->operationMaterial()->detach();
        foreach ($materials as $materialId => $quantity) {
            OperationMaterial::query()->create([
                'operation_id' => $operation->id,
                'material_id' => (int) $materialId,
                'qty' => $quantity,
                'price' => 0,
                'total' => 0,
                'description' => 'Field maintenance record; quantities only.',
            ]);
        }
    }

    /** @return array<string, string> */
    private function batchValidationMessages(): array
    {
        return [
            'operations.*.materials.*.material_id.distinct' => 'هر مصالح در هر عملیات فقط یک بار مجاز است.',
            'operations.*.materials.*.material_id.required' => 'شناسه مصالح الزامی است.',
            'operations.*.materials.*.qty.required' => 'مقدار مصالح الزامی است.',
            'operations.*.materials.*.qty.numeric' => 'مقدار مصالح باید عددی باشد.',
            'operations.*.materials.*.qty.gt' => 'مقدار مصالح باید بیشتر از صفر باشد.',
        ];
    }

    /** @param array<int, array{material_id:int|numeric-string,qty:numeric}> $materials */
    private function replaceOperationMaterialsFromList(Operation $operation, array $materials): void
    {
        foreach ($materials as $material) {
            OperationMaterial::query()->create([
                'operation_id' => $operation->id,
                'material_id' => (int) $material['material_id'],
                'qty' => $material['qty'],
                'price' => 0,
                'total' => 0,
                'description' => 'Field maintenance record; quantities only.',
            ]);
        }
    }

    /** @param iterable<Operation> $operations @return array<int, array<string, mixed>> */
    private function materialsTotal(iterable $operations): array
    {
        $totals = [];
        foreach ($operations as $operation) {
            foreach ($operation->operationMaterial as $material) {
                $key = (int) $material->id;
                $totals[$key] ??= ['material_id' => $key, 'title' => $material->title, 'unit' => $material->unit, 'qty' => 0];
                $totals[$key]['qty'] += (float) $material->pivot->qty;
            }
        }
        return array_values($totals);
    }
}
