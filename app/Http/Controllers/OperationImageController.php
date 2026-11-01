<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\MaintenanceCase;
use App\Models\Riser;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OperationImageController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:10240'],
            'operation_id' => ['nullable', 'integer', 'exists:operations,id'],
            'riser_id' => ['nullable', 'integer', 'exists:riser,id'],
            'type' => ['required', 'in:before,after'],
        ]);

        if (empty($validated['operation_id']) && empty($validated['riser_id'])) {
            return response()->json(['message' => 'operation_id or riser_id is required.'], 422);
        }

        $operation = null;
        if (! empty($validated['operation_id'])) {
            $operation = Operation::query()->with('riser')->findOrFail($validated['operation_id']);
            Gate::authorize('update', $operation);

            if (! empty($validated['riser_id']) && (int) $validated['riser_id'] !== (int) $operation->riser_id) {
                return response()->json(['message' => 'The operation and riser do not match.'], 422);
            }

            $validated['riser_id'] = $operation->riser_id;
        } else {
            Gate::authorize('create', Operation::class);
            Riser::query()->visibleTo($request->user())->findOrFail($validated['riser_id']);
        }

        $photo = $this->createPhoto($request, $validated, $operation);
        $this->audit->record($operation ? 'operation.photo_added' : 'maintenance_case.photo_added', $photo, null, [
            'photo_id' => $photo->id, 'type' => $photo->type, 'riser_id' => $photo->riser_id,
            'operation_id' => $photo->operation_id, 'maintenance_case_id' => $photo->maintenance_case_id,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => Storage::disk('public')->url($photo->path),
            'type' => $photo->type,
        ]);
    }

    public function pendingBefore(Request $request, $riserId)
    {
        Gate::authorize('viewAny', Operation::class);
        Riser::query()->visibleTo($request->user())->findOrFail($riserId);

        $photos = OperationImage::query()
            ->whereNull('operation_id')
            ->where('riser_id', $riserId)
            ->where('type', 'before')
            ->orderBy('id')
            ->get()
            ->map(fn (OperationImage $photo) => [
                'id' => $photo->id,
                'url' => Storage::disk('public')->url($photo->path),
            ]);

        return response()->json($photos);
    }

    public function destroy(Request $request, OperationImage $operationPhoto)
    {
        if ($operationPhoto->operation_id !== null) {
            $operation = Operation::query()->findOrFail($operationPhoto->operation_id);
            Gate::authorize('update', $operation);
        } elseif ($operationPhoto->maintenance_case_id !== null) {
            $case = MaintenanceCase::query()->findOrFail($operationPhoto->maintenance_case_id);
            abort_unless(Riser::query()->visibleTo($request->user())->whereKey($case->riser_id)->exists(), 404);
            $user = $request->user();
            if (! $user->isSuperAdmin()) {
                $canEditCase = $user->hasRole('boss')
                    ? $user->can('operation.edit') && $user->managesGroup((int) $case->assigned_group_id)
                    : ($user->hasRole('supervisor') || $user->can('maintenance.case.create'))
                        && (int) $user->group_user_id === (int) $case->assigned_group_id;
                abort_unless($canEditCase, 403);
            }
        } else {
            Gate::authorize('create', Operation::class);
            Riser::query()->visibleTo($request->user())->findOrFail($operationPhoto->riser_id);
        }

        $this->audit->record('operation.photo_deleted', $operationPhoto, [
            'photo_id' => $operationPhoto->id, 'type' => $operationPhoto->type,
            'operation_id' => $operationPhoto->operation_id, 'maintenance_case_id' => $operationPhoto->maintenance_case_id,
        ], null);
        $path = $operationPhoto->path;
        $disk = $operationPhoto->maintenance_case_id !== null ? 'local' : 'public';
        $operationPhoto->delete();
        Storage::disk($disk)->delete($path);

        return response()->json(['success' => true]);
    }

    private function createPhoto(Request $request, array $validated, ?Operation $operation): OperationImage
    {
        $file = $request->file('photo');

        return OperationImage::query()->create([
            'operation_id' => $operation?->id,
            'riser_id' => $validated['riser_id'] ?? $operation?->riser_id,
            'type' => $validated['type'],
            'path' => $file->store('operation-photos', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);
    }
}
