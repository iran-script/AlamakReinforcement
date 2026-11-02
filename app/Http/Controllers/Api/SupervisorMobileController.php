<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceCase;
use App\Models\Material;
use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\OperationCategory;
use App\Models\Riser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SupervisorMobileController extends Controller
{
    public function mapZone(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ($user->isSuperAdmin() || ($user->hasRole('supervisor', 'web') && $user->can('map.view'))), 403);
        if ($user->isSuperAdmin()) {
            return response()->json(['is_superadmin' => true, 'geometry' => null])->header('Cache-Control', 'private, no-store');
        }
        abort_unless($user->zone_id && $user->organization_id, 403, 'User zone or organization is not assigned.');

        $zone = DB::table('zone')
            ->where('id', $user->zone_id)
            ->where('organization_id', $user->organization_id)
            ->whereNotNull('geom')
            ->select('id', 'name')
            ->selectRaw('ST_AsGeoJSON(ST_Transform(geom, 4326)) AS geometry')
            ->first();
        abort_unless($zone, 404, 'Assigned zone boundary is unavailable.');

        return response()->json([
            'is_superadmin' => false,
            'zone_id' => (int) $zone->id,
            'zone_name' => $zone->name,
            'geometry' => json_decode($zone->geometry, true),
            'srid' => 4326,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json(['user' => [
            'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
            'organization_id' => $user->organization_id, 'zone_id' => $user->zone_id,
            'group_user_id' => $user->group_user_id, 'role' => 'supervisor',
        ], 'zone' => $user->zone?->only(['id', 'name']), 'group' => $user->groupUser?->only(['id', 'name'])]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $cases = MaintenanceCase::query()->visibleTo($user)->where('reported_by', $user->id);
        return response()->json([
            'open' => (clone $cases)->whereIn('status', [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS])->count(),
            'submitted' => (clone $cases)->where('status', MaintenanceCase::STATUS_SUBMITTED)->count(),
            'returned' => (clone $cases)->where('status', MaintenanceCase::STATUS_RETURNED)->count(),
            'completed' => (clone $cases)->where('status', MaintenanceCase::STATUS_COMPLETED)->count(),
            'zone' => $user->zone?->only(['id', 'name']),
            'group' => $user->groupUser?->only(['id', 'name']),
        ]);
    }

    public function risers(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'between:1,200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'bbox_west' => ['nullable', 'numeric', 'between:-180,180', 'required_with:bbox_south,bbox_east,bbox_north'],
            'bbox_south' => ['nullable', 'numeric', 'between:-85,85', 'required_with:bbox_west,bbox_east,bbox_north'],
            'bbox_east' => ['nullable', 'numeric', 'between:-180,180', 'required_with:bbox_west,bbox_south,bbox_north'],
            'bbox_north' => ['nullable', 'numeric', 'between:-85,85', 'required_with:bbox_west,bbox_south,bbox_east'],
        ]);
        if (isset($data['bbox_west'])) {
            abort_if($data['bbox_west'] >= $data['bbox_east'] || $data['bbox_south'] >= $data['bbox_north'], 422, 'Bounding box is invalid.');
            abort_if(($data['bbox_east'] - $data['bbox_west']) > 2 || ($data['bbox_north'] - $data['bbox_south']) > 2, 422, 'Zoom in to load risers for a smaller map area.');
            abort_if(($data['page'] ?? 1) > 10, 422, 'Load a smaller map area to continue.');
        }
        $query = Riser::query()->visibleTo($request->user())
            ->whereNotNull('riser.geom')
            ->select('riser.id', 'riser.r_giscode', 'riser.giscode', 'riser.status', 'riser.updated_at')
            ->selectSub(MaintenanceCase::query()->select('status')->whereColumn('maintenance_cases.riser_id', 'riser.id')
                ->where('maintenance_cases.assigned_group_id', $request->user()->group_user_id)
                ->orderByRaw("CASE WHEN status IN ('open', 'in_progress', 'submitted', 'returned') THEN 0 ELSE 1 END")
                ->latest('identified_at')->limit(1), 'maintenance_status')
            ->selectRaw('ST_X(ST_PointOnSurface(ST_Transform(riser.geom, 4326))) AS longitude')
            ->selectRaw('ST_Y(ST_PointOnSurface(ST_Transform(riser.geom, 4326))) AS latitude')
            ->when(isset($data['bbox_west']), fn ($query) => $query->whereRaw(
                'ST_Intersects(riser.geom, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
                [$data['bbox_west'], $data['bbox_south'], $data['bbox_east'], $data['bbox_north']]
            ))
            ->when(isset($data['search']), fn ($query) => $query->where(function ($query) use ($data): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['search']).'%';
                $query->where('riser.r_giscode', 'ilike', $term)->orWhere('riser.giscode', 'ilike', $term);
            }))
            ->when(isset($data['status']), fn ($query) => $query->whereExists(fn ($cases) => $cases->selectRaw('1')
                ->from('maintenance_cases')->whereColumn('maintenance_cases.riser_id', 'riser.id')
                ->where('maintenance_cases.assigned_group_id', $request->user()->group_user_id)
                ->where('maintenance_cases.status', $data['status'])))
            ->orderBy('riser.id');

        return response()->json($query->paginate($data['per_page'] ?? 100)->through(fn (Riser $riser) => [
            'id' => $riser->id, 'giscode' => $riser->giscode, 'r_giscode' => $riser->r_giscode,
            'status' => $riser->status, 'maintenance_status' => $riser->maintenance_status,
            'latitude' => (float) $riser->latitude,
            'longitude' => (float) $riser->longitude, 'updated_at' => $riser->updated_at,
        ]));
    }

    public function references(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'zone' => $user->zone?->only(['id', 'name']),
            'group' => $user->groupUser?->only(['id', 'name']),
            'operation_categories' => OperationCategory::query()->where('is_active', true)->orderBy('sort')->orderBy('title')->get(['id', 'title', 'code']),
            'materials' => Material::query()->with('category:id,title,code')->where('is_active', true)
                ->orderBy('title')->get(['id', 'title', 'code', 'unit', 'material_category_id']),
        ]);
    }

    public function riserDetails(Request $request, int $riserId)
    {
        $user = $request->user();
        $riser = Riser::query()->visibleTo($user)->whereKey($riserId)
            ->select('riser.id', 'riser.giscode', 'riser.r_giscode', 'riser.status')
            ->selectRaw('ST_X(ST_PointOnSurface(ST_Transform(riser.geom, 4326))) AS longitude')
            ->selectRaw('ST_Y(ST_PointOnSurface(ST_Transform(riser.geom, 4326))) AS latitude')
            ->firstOrFail();
        $cases = MaintenanceCase::query()->visibleTo($user)->where('riser_id', $riser->id)
            ->where('assigned_group_id', $user->group_user_id)
            ->with(['reporter:id,name', 'assignedGroup:id,name', 'operationCategory:id,title', 'lastEditor:id,name', 'photos:id,maintenance_case_id,operation_id,type,created_at',
                'operations.operationCategory:id,title', 'operations.operationMaterial:id,title,unit', 'operations.images:id,operation_id,maintenance_case_id,type,created_at',
                'operations.user:id,name', 'operations.supervisors:id,name'])
            ->latest('identified_at')->get();
        $operations = Operation::query()->where('riser_id', $riser->id)
            ->where('group_user_id', $user->group_user_id)
            ->with(['operationCategory:id,title', 'operationMaterial:id,title,unit', 'images:id,operation_id,maintenance_case_id,type,created_at'])
            ->latest('operation_date')->get()
            ->map(fn (Operation $operation) => [
                'id' => $operation->id, 'date' => $operation->operation_date,
                'status' => $operation->status, 'description' => $operation->description,
                'category' => $operation->operationCategory?->title,
                'materials' => $operation->operationMaterial->map(fn ($material) => [
                    'id' => $material->id, 'title' => $material->title, 'unit' => $material->unit,
                    'qty' => (string) $material->pivot->qty,
                ])->values(),
                'photos' => $operation->images->map(fn (OperationImage $image) => [
                    'id' => $image->id, 'type' => $image->type,
                    'url' => url('/api/v1/supervisor/photos/'.$image->id),
                ])->values(),
            ])->values();

        return response()->json([
            'riser' => [
                'id' => $riser->id, 'giscode' => $riser->giscode, 'r_giscode' => $riser->r_giscode,
                'status' => $riser->status,
                'latitude' => $riser->latitude === null ? null : (float) $riser->latitude,
                'longitude' => $riser->longitude === null ? null : (float) $riser->longitude,
            ],
            'cases' => $cases->map(fn (MaintenanceCase $case) => [
                'id' => $case->id, 'status' => $case->status, 'priority' => $case->priority,
                'description' => $case->description, 'identified_at' => $case->identified_at,
                'submitted_at' => $case->submitted_at, 'reporter' => $case->reporter?->name,
                'group' => $case->assignedGroup?->name, 'operation_category' => $case->operationCategory?->title,
                'operations' => $case->operations->map(fn (Operation $operation) => [
                    'id' => $operation->id, 'date' => $operation->operation_date, 'status' => $operation->status,
                    'description' => $operation->description, 'updated_at' => $operation->updated_at,
                    'created_by' => $operation->user?->name,
                    'reviewers' => $operation->supervisors->map(fn ($reviewer) => [
                        'name' => $reviewer->name, 'status' => $reviewer->pivot->status,
                        'comment' => $reviewer->pivot->comment, 'approved_at' => $reviewer->pivot->approved_at,
                    ])->values(),
                    'category' => $operation->operationCategory?->title,
                    'operation_category_id' => $operation->operation_category_id,
                    'materials' => $operation->operationMaterial->map(fn ($material) => [
                        'id' => $material->id, 'title' => $material->title, 'unit' => $material->unit,
                        'qty' => (string) $material->pivot->qty,
                    ])->values(),
                    'photos' => $operation->images->map(fn (OperationImage $image) => [
                        'id' => $image->id, 'type' => $image->type,
                        'url' => url('/api/v1/supervisor/photos/'.$image->id),
                    ])->values(),
                ])->values(),
                'photos' => $case->photos->map(fn (OperationImage $image) => [
                    'id' => $image->id, 'type' => $image->type,
                    'url' => url('/api/v1/supervisor/photos/'.$image->id),
                ])->values(),
            ])->values(),
            'operations' => $operations,
        ]);
    }

    public function history(Request $request, int $riserId)
    {
        $user = $request->user();
        abort_unless(Riser::query()->visibleTo($user)->whereKey($riserId)->exists(), 404);
        $operations = Operation::query()->where('riser_id', $riserId)
            ->where('group_user_id', $user->group_user_id)
            ->with(['operationCategory:id,title', 'operationMaterial:id,title,unit', 'images:id,operation_id,type,created_at'])
            ->latest('operation_date')->paginate(25);

        return response()->json($operations->through(fn (Operation $operation) => [
            'id' => $operation->id, 'date' => $operation->operation_date,
            'status' => $operation->status, 'description' => $operation->description,
            'category' => $operation->operationCategory?->title,
            'materials' => $operation->operationMaterial->map(fn ($material) => [
                'id' => $material->id, 'title' => $material->title, 'unit' => $material->unit,
                'qty' => (string) $material->pivot->qty,
            ])->values(),
            'photos' => $operation->images->map(fn (OperationImage $image) => [
                'id' => $image->id, 'type' => $image->type,
                'url' => url('/api/v1/supervisor/photos/'.$image->id),
            ])->values(),
        ]));
    }

    public function photo(Request $request, OperationImage $operationImage)
    {
        $user = $request->user();
        if ($operationImage->maintenance_case_id !== null) {
            $case = MaintenanceCase::query()->findOrFail($operationImage->maintenance_case_id);
            abort_unless((int) $case->assigned_group_id === (int) $user->group_user_id, 404);
            abort_unless(Riser::query()->visibleTo($user)->whereKey($case->riser_id)->exists(), 404);
            $disk = 'local';
        } else {
            $riserId = $operationImage->riser_id;
            abort_unless($riserId && Riser::query()->visibleTo($user)->whereKey($riserId)->exists(), 404);
            if ($operationImage->operation_id !== null) {
                abort_unless(Operation::query()->whereKey($operationImage->operation_id)
                    ->where('group_user_id', $user->group_user_id)->exists(), 404);
            } else {
                abort_unless((int) $operationImage->uploaded_by === (int) $user->id, 404);
            }
            $disk = 'public';
        }
        abort_unless(Storage::disk($disk)->exists($operationImage->path), 404);
        return response()->file(Storage::disk($disk)->path($operationImage->path));
    }

    public function health()
    {
        return response()->json(['ok' => true, 'service' => 'alamak-supervisor-api', 'api_version' => 'v1']);
    }
}
