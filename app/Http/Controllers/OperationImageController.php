<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperationImage;
use Illuminate\Support\Facades\Storage;



// app/Http/Controllers/OperationImageController.php
// app/Http/Controllers/OperationPhotoController.php
class OperationImageController extends Controller
{
    public function store(Request $request)
    {
        
        $request->validate([
            'photo' => 'required|image|max:10240',
            'operation_id' => 'nullable|exists:operation,id',
            'riser_id' => 'nullable|exists:riser,id',
            'type' => 'required|in:before,after',
        ]);

        if (!$request->operation_id && !$request->riser_id) {
            return response()->json(['message' => 'باید operation_id یا riser_id ارسال شود.'], 422);
        }

        $path = $request->file('photo')->store('operation-photos', 'public');

        $photo = OperationImage::create([
            'operation_id' => $request->operation_id,
            'riser_id' => $request->riser_id,
            'type' => $request->type,
            'path' => $path,
            'original_name' => $request->file('photo')->getClientOriginalName(),
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => Storage::disk('public')->url($photo->path),
            'type' => $photo->type,
        ]);
    }

    public function pendingBefore($riserId)
    {
        $photos = OperationImage::whereNull('operation_id')
            ->where('riser_id', $riserId)
            ->where('type', 'before')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => ['id' => $p->id, 'url' => asset('storage/' . $p->path)]);

        return response()->json($photos);
    }

    public function destroy(OperationImage $operationPhoto)
    {
        Storage::disk('public')->delete($operationPhoto->path);
        $operationPhoto->delete();

        return response()->json(['success' => true]);
    }
}
