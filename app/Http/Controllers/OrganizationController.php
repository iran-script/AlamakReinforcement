<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount(['zones', 'groupUsers', 'users'])
            ->orderBy('name')
            ->paginate(20);

        return view('organizations.index', compact('organizations'));
    }

    public function create()
    {
        Gate::authorize('create', Organization::class);

        return view('organizations.create', [
            'organization' => new Organization(['status' => 'active']),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'organization_id']),
            'assignedZoneIds' => [],
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        Gate::authorize('create', Organization::class);
        $validated = $this->validated($request);

        $organization = DB::transaction(function () use ($validated, $audit): Organization {
            $organization = Organization::query()->create($validated['organization']);
            $this->assignZones($organization, $validated['zone_ids']);
            $audit->record('organization.created', $organization, null, $this->snapshot($organization));

            return $organization;
        });

        return redirect()->route('organizations.index')->with('success', 'سازمان با موفقیت ایجاد شد.');
    }

    public function edit(Organization $organization)
    {
        Gate::authorize('update', $organization);

        return view('organizations.edit', [
            'organization' => $organization,
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'organization_id']),
            'assignedZoneIds' => $organization->zones()->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, Organization $organization, AuditLogger $audit)
    {
        Gate::authorize('update', $organization);
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $organization, $audit): void {
            $before = $this->snapshot($organization);
            $organization->update($validated['organization']);
            $this->assignZones($organization, $validated['zone_ids']);
            $audit->record('organization.updated', $organization, $before, $this->snapshot($organization->fresh()));
        });

        return redirect()->route('organizations.index')->with('success', 'اطلاعات سازمان به‌روزرسانی شد.');
    }

    public function destroy(Organization $organization, AuditLogger $audit)
    {
        Gate::authorize('delete', $organization);

        $dependencies = $this->dependencies($organization);
        if ($dependencies !== []) {
            return back()->with('error', 'حذف سازمان ممکن نیست؛ وابستگی‌های زیر وجود دارد: '.implode('، ', $dependencies));
        }

        $before = $this->snapshot($organization);
        $organization->delete();
        $audit->record('organization.deleted', $organization, $before, null);

        return redirect()->route('organizations.index')->with('success', 'سازمان حذف شد.');
    }

    /** @return array{organization: array<string, string>, zone_ids: array<int, int>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('organizations', 'code')->ignore($request->route('organization'))],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer', 'distinct', 'exists:zone,id'],
        ]);

        return [
            'organization' => collect($data)->only(['code', 'name', 'status'])->all(),
            'zone_ids' => array_map('intval', $data['zone_ids'] ?? []),
        ];
    }

    /** @param array<int, int> $zoneIds */
    private function assignZones(Organization $organization, array $zoneIds): void
    {
        $zoneIds = array_values(array_unique($zoneIds));
        $zones = Zone::query()->whereIn('id', array_unique([
            ...$zoneIds,
            ...$organization->zones()->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ]))->lockForUpdate()->get();

        if ($zones->count() !== count(array_unique([
            ...$zoneIds,
            ...$organization->zones()->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ]))) {
            throw ValidationException::withMessages(['zone_ids' => 'یکی از Zoneهای انتخاب‌شده پیدا نشد.']);
        }

        foreach ($zones as $zone) {
            $shouldBelong = in_array((int) $zone->id, $zoneIds, true);
            $alreadyBelongs = (int) $zone->organization_id === (int) $organization->id;
            if ($alreadyBelongs && ! $shouldBelong) {
                $this->assertZoneCanBeUnassigned($zone);
            }
            if ($shouldBelong && ! $alreadyBelongs) {
                $this->assertZoneCanBeAssigned($zone, $organization);
            }

            if ($shouldBelong !== $alreadyBelongs) {
                $zone->organization_id = $shouldBelong ? $organization->id : null;
                $zone->save();
            }
        }
    }

    private function assertZoneCanBeAssigned(Zone $zone, Organization $organization): void
    {
        $hasIncompatibleGroup = DB::table('group_users')->where('zone_id', $zone->id)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', '!=', $organization->id))->exists();
        $hasIncompatibleUser = DB::table('users')->where('zone_id', $zone->id)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', '!=', $organization->id))->exists();

        if ($hasIncompatibleGroup || $hasIncompatibleUser) {
            throw ValidationException::withMessages(['zone_ids' => "Zone «{$zone->name}» کاربر یا گروه ناسازگار دارد و قابل واگذاری نیست."]);
        }
    }

    private function assertZoneCanBeUnassigned(Zone $zone): void
    {
        if (DB::table('group_users')->where('zone_id', $zone->id)->exists()
            || DB::table('users')->where('zone_id', $zone->id)->exists()) {
            throw ValidationException::withMessages(['zone_ids' => "Zone «{$zone->name}» دارای کاربر یا گروه است و قابل جداکردن نیست."]);
        }
    }

    /** @return array<int, string> */
    private function dependencies(Organization $organization): array
    {
        $checks = [
            ['users', 'کاربر'], ['zone', 'Zone'], ['group_users', 'گروه کاربران'],
            ['gis_map_settings', 'پروفایل نقشه GIS'], ['gis_map_layers', 'لایه GIS'],
            ['assets', 'Asset'], ['asset_types', 'نوع Asset'], ['spatial_scopes', 'محدوده مکانی'],
            ['work_order_types', 'نوع دستورکار'], ['work_orders', 'دستورکار'],
            ['form_definitions', 'فرم'], ['workflow_definitions', 'گردش‌کار'],
            ['data_access_policies', 'سیاست دسترسی'],
        ];

        return collect($checks)->filter(fn (array $check): bool => Schema::hasTable($check[0])
            && DB::table($check[0])->where('organization_id', $organization->id)->exists())
            ->pluck(1)->values()->all();
    }

    /** @return array<string, mixed> */
    private function snapshot(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'code' => $organization->code,
            'name' => $organization->name,
            'status' => $organization->status,
            'zone_ids' => $organization->zones()->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }
}
