<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\Riser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OperationImageController extends Controller
{
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
        } else {
            Gate::authorize('create', Operation::class);
            Riser::query()->visibleTo($request->user())->findOrFail($operationPhoto->riser_id);
        }

        Storage::disk('public')->delete($operationPhoto->path);
        $operationPhoto->delete();

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
