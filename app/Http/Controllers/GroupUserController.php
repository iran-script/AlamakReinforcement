<?php

namespace App\Http\Controllers;

use App\Models\GroupUser;
use App\Models\Organization;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupUserController extends Controller
{
    public function index()
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);
        $groupusers = GroupUser::query()->with(['organization:id,name', 'zone:id,name', 'managers:id,name'])
            ->withCount('users')->orderByDesc('id')->paginate(20);

        return view('groupuser.index', compact('groupusers'));
    }

    public function create()
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);

        return view('groupuser.create', $this->scopeOptions());
    }

    public function store(Request $request, AuditLogger $audit)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'zone_id' => ['required', 'integer', 'exists:zone,id'],
            'boss_user_ids' => ['nullable', 'array'],
            'boss_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $this->assertZoneOrganization((int) $validated['zone_id'], (int) $validated['organization_id']);
        $bossIds = $this->validateBosses($validated['boss_user_ids'] ?? [], (int) $validated['organization_id'], (int) $validated['zone_id']);

        $group = DB::transaction(function () use ($request, $validated, $bossIds, $audit): GroupUser {
            $group = GroupUser::create(collect($validated)->only(['name', 'description', 'organization_id', 'zone_id'])->all());
            $group->managers()->sync($this->managerPivot($bossIds, $request->user()->id));
            $audit->record('work_group.created', $group, null, $this->snapshot($group, $bossIds));

            return $group;
        });

        return redirect()->route('groupusers.index')->with('success', 'گروه کاری و محدوده آن ثبت شد.');
    }

    public function edit(GroupUser $groupuser)
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);
        $groupuser->load('managers:id,name');

        return view('groupuser.edit', array_merge(['groupuser' => $groupuser], $this->scopeOptions()));
    }

    public function update(Request $request, GroupUser $groupuser, AuditLogger $audit)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'zone_id' => ['required', 'integer', 'exists:zone,id'],
            'boss_user_ids' => ['nullable', 'array'],
            'boss_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $organizationId = (int) $validated['organization_id'];
        $zoneId = (int) $validated['zone_id'];
        $this->assertZoneOrganization($zoneId, $organizationId);

        $scopeChanged = (int) $groupuser->organization_id !== $organizationId || (int) $groupuser->zone_id !== $zoneId;
        if ($scopeChanged && (DB::table('maintenance_cases')->where('assigned_group_id', $groupuser->id)->exists()
            || DB::table('operations')->where('group_user_id', $groupuser->id)->exists())) {
            return back()->withErrors(['zone_id' => 'محدوده گروه دارای سابقه پرونده/عملیات قابل تغییر نیست.'])->withInput();
        }

        $hasOutOfScopeMembers = $groupuser->users()->where(function ($query) use ($organizationId, $zoneId): void {
            $query->where('organization_id', '!=', $organizationId)->orWhereNull('organization_id')
                ->orWhere('zone_id', '!=', $zoneId)->orWhereNull('zone_id');
        })->exists();
        if ($hasOutOfScopeMembers) {
            return back()->withErrors(['zone_id' => 'گروه دارای عضو با سازمان یا Zone متفاوت است؛ ابتدا عضویت‌ها را اصلاح کنید.'])->withInput();
        }

        $bossIds = $this->validateBosses($validated['boss_user_ids'] ?? [], $organizationId, $zoneId);
        DB::transaction(function () use ($request, $validated, $groupuser, $bossIds, $audit): void {
            $before = $this->snapshot($groupuser, $groupuser->managers()->pluck('users.id')->map(fn ($id) => (int) $id)->all());
            $groupuser->update(collect($validated)->only(['name', 'description', 'organization_id', 'zone_id'])->all());
            $groupuser->managers()->sync($this->managerPivot($bossIds, $request->user()->id));
            $audit->record('work_group.updated', $groupuser, $before, $this->snapshot($groupuser->fresh(), $bossIds));
        });

        return redirect()->route('groupusers.index')->with('success', 'اطلاعات گروه کاری به‌روزرسانی شد.');
    }

    public function destroy(GroupUser $groupuser, AuditLogger $audit)
    {
        abort_unless(request()->user()->isSuperAdmin(), 403);
        if ($groupuser->users()->exists() || $groupuser->managers()->exists()
            || DB::table('maintenance_cases')->where('assigned_group_id', $groupuser->id)->exists()
            || DB::table('operations')->where('group_user_id', $groupuser->id)->exists()) {
            return back()->with('error', 'این گروه عضو، پرونده، عملیات یا رئیس تخصیص‌یافته دارد و قابل حذف نیست.');
        }

        $audit->record('work_group.deleted', $groupuser, $this->snapshot($groupuser, []), null);
        $groupuser->delete();

        return redirect()->route('groupusers.index')->with('success', 'گروه کاری حذف شد.');
    }

    private function scopeOptions(): array
    {
        return [
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'organization_id']),
            'bosses' => User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'boss')->where('guard_name', 'web'))
                ->where(function ($permissions): void {
                    $permissions->whereHas('permissions', fn ($query) => $query->where('name', 'operation.approve')->where('guard_name', 'web'))
                        ->orWhereHas('roles.permissions', fn ($query) => $query->where('name', 'operation.approve')->where('guard_name', 'web'));
                })
                ->with('zone:id,name')->orderBy('name')->get(['id', 'name', 'zone_id', 'organization_id']),
        ];
    }

    private function assertZoneOrganization(int $zoneId, int $organizationId): void
    {
        abort_unless(Zone::query()->whereKey($zoneId)->where('organization_id', $organizationId)->exists(), 422,
            'Zone باید متعلق به همان سازمان گروه باشد.');
    }

    private function validateBosses(array $bossIds, int $organizationId, int $zoneId): array
    {
        $ids = array_values(array_unique(array_map('intval', $bossIds)));
        if ($ids === []) return [];

        $validCount = User::query()->whereIn('id', $ids)->where('organization_id', $organizationId)->where('zone_id', $zoneId)
            ->whereHas('roles', fn ($roles) => $roles->where('name', 'boss')->where('guard_name', 'web'))
            ->where(function ($permissions): void {
                $permissions->whereHas('permissions', fn ($query) => $query->where('name', 'operation.approve')->where('guard_name', 'web'))
                    ->orWhereHas('roles.permissions', fn ($query) => $query->where('name', 'operation.approve')->where('guard_name', 'web'));
            })->count();
        abort_unless($validCount === count($ids), 422, 'رئیس ناحیه باید در همان سازمان و Zone گروه باشد.');

        return $ids;
    }

    private function managerPivot(array $bossIds, int $assignedBy): array
    {
        return collect($bossIds)->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $assignedBy]])->all();
    }

    private function snapshot(GroupUser $group, array $bossIds): array
    {
        return [
            'id' => $group->id, 'name' => $group->name, 'description' => $group->description,
            'organization_id' => $group->organization_id, 'zone_id' => $group->zone_id,
            'boss_user_ids' => $bossIds,
        ];
    }
}
