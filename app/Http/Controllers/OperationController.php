<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Models\Material;
use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\Riser;
use App\Models\WorkflowTransfer;
use App\Services\AuditLogger;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OperationController extends Controller
{
    public function __construct(
        private WorkflowService $workflow,
        private AuditLogger $auditLogger,
    ) {}

    public function index()
    {
        Gate::authorize('viewAny', Operation::class);

        return response()->json(
            Operation::query()
                ->whereHas('riser', fn ($query) => $query->visibleTo(auth()->user()))
                ->when(auth()->user()->hasRole('boss'), fn ($query) => $query->whereIn('group_user_id', auth()->user()->managedGroupIds()))
                ->with(['riser', 'operationCategory', 'user'])
                ->latest('operation_date')
                ->paginate(25)
        );
    }

    public function create()
    {
        Gate::authorize('create', Operation::class);
        return response()->json(['message' => 'Operation form is available through the riser workflow.']);
    }

    public function store(StoreOperationRequest $request)
    {
        $data = $request->validated();
        $riser = Riser::query()->visibleTo($request->user())->findOrFail($data['riser_id']);

        $operation = DB::transaction(function () use ($request, $data, $riser): Operation {
            $operation = Operation::query()->create([
                'riser_id' => $riser->id,
                'operation_category_id' => $data['operationcat'],
                'status' => $data['status'] ?? '0',
                'priority' => $data['priority'] ?? '0',
                'description' => $data['description'] ?? null,
                'operation_date' => now()->toDateString(),
                'user_id' => $request->user()->id,
                'contract_id' => $request->user()->contract_id,
            ]);

            OperationImage::query()
                ->whereNull('operation_id')
                ->where('riser_id', $riser->id)
                ->where('type', 'before')
                ->update(['operation_id' => $operation->id]);

            $this->replaceMaterials($operation, $data['materials'] ?? []);

            $this->auditLogger->record(
                'operation.created',
                $operation,
                null,
                $this->auditLogger->attributesFor($operation),
            );

            $this->auditLogger->record(
                'operation.materials_updated',
                $operation,
                null,
                ['materials' => $this->materialSnapshot($operation)],
                ['financial_fields' => 'legacy_neutral_values_only'],
            );

            foreach ($request->file('photos_after', []) as $file) {
                $photo = OperationImage::query()->create([
                    'operation_id' => $operation->id,
                    'riser_id' => $riser->id,
                    'type' => 'after',
                    'path' => $file->store('operation-photos', 'public'),
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $request->user()->id,
                ]);
                $this->auditLogger->record('operation.photo_added', $photo, null, [
                    'photo_id' => $photo->id, 'type' => 'after', 'riser_id' => $photo->riser_id,
                    'operation_id' => $photo->operation_id,
                ]);
            }

            $riser->update(['status' => 'submitOperation']);
            $technicalInspector = $this->workflow->resolveTechnicalInspector($riser);
            $this->workflow->submitOperationForLeakCheck($operation, $technicalInspector);

            return $operation->refresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Operation created successfully.',
            'operation' => $operation,
        ], 201);
    }

    public function show(Operation $operation)
    {
        Gate::authorize('view', $operation);

        return response()->json(['operation' => $operation->load(['riser', 'operationCategory', 'operationMaterial', 'images'])]);
    }

    public function history(Request $request, Riser $riser)
    {
        abort_unless(
            Riser::query()->visibleTo($request->user())->whereKey($riser->id)->exists(),
            404
        );

        return response()->json(
            Operation::query()
                ->where('riser_id', $riser->id)
                ->when($request->user()->hasRole('boss'), fn ($query) => $query->whereIn('group_user_id', $request->user()->managedGroupIds()))
                ->with(['operationCategory', 'user', 'operationMaterial', 'images', 'supervisors'])
                ->latest('operation_date')
                ->latest('id')
                ->paginate(25)
        );
    }

    public function edit(Operation $operation)
    {
        Gate::authorize('update', $operation);
        return response()->json(['operation' => $operation->load(['operationCategory', 'operationMaterial', 'images'])]);
    }

    public function update(UpdateOperationRequest $request, Operation $operation)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $operation): void {
            $operation = Operation::query()->lockForUpdate()->findOrFail($operation->id);
            Gate::authorize('update', $operation);
            $before = $this->auditLogger->attributesFor($operation);

            if (array_key_exists('riser_id', $data)) {
                Riser::query()->visibleTo($request->user())->findOrFail($data['riser_id']);
            }

            $operation->fill([
                'riser_id' => $data['riser_id'] ?? $operation->riser_id,
                'operation_category_id' => $data['operationcat'] ?? $operation->operation_category_id,
                'status' => array_key_exists('status', $data) ? $data['status'] : $operation->status,
                'priority' => array_key_exists('priority', $data) ? $data['priority'] : $operation->priority,
                'description' => array_key_exists('description', $data) ? $data['description'] : $operation->description,
            ]);

            if (array_key_exists('materials', $data)) {
                $this->replaceMaterials($operation, $data['materials'] ?? []);
            }

            $operation->save();

            $this->auditLogger->record(
                'operation.updated',
                $operation,
                $before,
                $this->auditLogger->attributesFor($operation),
            );

            if (array_key_exists('materials', $data)) {
                $this->auditLogger->record(
                    'operation.materials_updated',
                    $operation,
                    null,
                    ['materials' => $this->materialSnapshot($operation)],
                    ['financial_fields' => 'legacy_neutral_values_only'],
                );
            }

            foreach ($request->file('photos_after', []) as $file) {
                $photo = OperationImage::query()->create([
                    'operation_id' => $operation->id,
                    'riser_id' => $operation->riser_id,
                    'type' => 'after',
                    'path' => $file->store('operation-photos', 'public'),
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $request->user()->id,
                ]);
                $this->auditLogger->record('operation.photo_added', $photo, null, [
                    'photo_id' => $photo->id, 'type' => 'after', 'riser_id' => $photo->riser_id,
                    'operation_id' => $photo->operation_id,
                ]);
            }
        });

        return response()->json(['success' => true, 'operation' => $operation->refresh()->load('operationMaterial')]);
    }

    public function approve(Request $request, Operation $operation)
    {
        Gate::authorize('approve', $operation);

        DB::transaction(function () use ($request, $operation): void {
            $operation = Operation::query()->lockForUpdate()->findOrFail($operation->id);
            Gate::authorize('approve', $operation);
            $operation->supervisors()->updateExistingPivot($request->user()->id, [
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $transfer = WorkflowTransfer::query()
                ->where('operation_id', $operation->id)
                ->where('user_id_to', $request->user()->id)
                ->where('type', WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST)
                ->pending()
                ->first();
            if ($transfer) {
                $this->workflow->resolveLeakCheck($transfer, $request->user(), true);
            }

            $this->auditLogger->record('operation.approved', $operation, null, [
                'supervisor_id' => $request->user()->id,
                'approved_at' => now()->toISOString(),
            ]);
        });

        return response()->json(['success' => true, 'status' => 'approved']);
    }

    public function returnForRevision(Request $request, Operation $operation)
    {
        $data = $request->validate(['comment' => ['required', 'string', 'max:5000']]);
        Gate::authorize('returnForRevision', $operation);

        DB::transaction(function () use ($request, $operation, $data): void {
            $operation = Operation::query()->lockForUpdate()->findOrFail($operation->id);
            Gate::authorize('returnForRevision', $operation);
            $operation->supervisors()->updateExistingPivot($request->user()->id, [
                'status' => 'need_revision',
                'comment' => $data['comment'],
                'approved_at' => null,
            ]);

            $transfer = WorkflowTransfer::query()
                ->where('operation_id', $operation->id)
                ->where('user_id_to', $request->user()->id)
                ->where('type', WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST)
                ->pending()
                ->first();
            if ($transfer) {
                $this->workflow->resolveLeakCheck($transfer, $request->user(), false, $data['comment']);
            }

            $this->auditLogger->record('operation.returned_for_revision', $operation, null, [
                'supervisor_id' => $request->user()->id,
                'comment' => $data['comment'],
            ]);
        });

        return response()->json(['success' => true, 'status' => 'need_revision']);
    }

    public function destroy(Operation $operation)
    {
        Gate::authorize('delete', $operation);

        $this->auditLogger->record('operation.deleted', $operation, $this->auditLogger->attributesFor($operation), null, [
            'photo_ids' => $operation->images()->pluck('id')->all(),
        ]);

        DB::transaction(fn () => $operation->delete());
        return response()->json(['success' => true]);
    }

    public function uploadBefore(Request $request, Operation $operation)
    {
        Gate::authorize('update', $operation);
        return $this->storeOperationPhoto($request, $operation, 'before');
    }

    public function uploadAfter(Request $request, Operation $operation)
    {
        Gate::authorize('update', $operation);
        return $this->storeOperationPhoto($request, $operation, 'after');
    }

    private function storeOperationPhoto(Request $request, Operation $operation, string $type)
    {
        $request->validate(['photo' => ['required', 'image', 'max:10240']]);
        $file = $request->file('photo');

        $photo = OperationImage::query()->create([
            'operation_id' => $operation->id,
            'riser_id' => $operation->riser_id,
            'type' => $type,
            'path' => $file->store('operation-photos', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        $this->auditLogger->record('operation.photo_added', $photo, null, [
            'photo_id' => $photo->id, 'type' => $photo->type, 'riser_id' => $photo->riser_id,
            'operation_id' => $photo->operation_id,
        ]);

        return response()->json(['id' => $photo->id, 'type' => $photo->type]);
    }

    private function replaceMaterials(Operation $operation, array $materials): void
    {
        $operation->operationMaterial()->detach();

        foreach ($materials as $materialId => $qty) {
            $qty = (float) $qty;
            if ($qty <= 0) {
                continue;
            }

            $material = Material::query()->where('is_active', true)->findOrFail($materialId);
            $operation->operationMaterial()->attach($material->id, [
                'qty' => $qty,
                // Financial columns are legacy/non-null fields. Phase 2 records
                // consumption only and deliberately does not calculate money.
                'price' => 0,
                'total' => 0,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function materialSnapshot(Operation $operation): array
    {
        return $operation->load('operationMaterial')->operationMaterial->map(fn (Material $material) => [
            'material_id' => $material->id,
            'title' => $material->title,
            'unit' => $material->unit,
            'qty' => (string) $material->pivot->qty,
        ])->values()->all();
    }
}
