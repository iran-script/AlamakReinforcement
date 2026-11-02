<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\GroupUser;
use App\Models\Organization;
use App\Models\User;
use App\Models\Zone;
use App\Policies\UserPolicy;
use App\Services\AuditLogger;
use App\Services\UserManagementScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', User::class);
        $actor = $request->user();

        $query = User::query()->with(['roles', 'zone', 'groupUser', 'contract']);
        if ($actor->hasRole('boss')) {
            $query = app(UserManagementScope::class)->usersQuery($actor)
                ->with(['roles', 'zone', 'groupUser', 'contract']);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(fn ($users) => $users
                ->where('name', 'ilike', '%'.$search.'%')
                ->orWhere('username', 'ilike', '%'.$search.'%'));
        }

        $users = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(Request $request)
    {
        Gate::authorize('create', User::class);
        $actor = $request->user();

        return view('users.create', $this->formOptions($actor));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', User::class);
        $actor = $request->user();
        $isBoss = $actor->hasRole('boss');
        $isSuperAdmin = $actor->hasRole('superadmin');
        $roleNames = $this->assignableRoles($actor)->pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($roleNames)],
            'group_user_id' => [$isBoss ? 'required' : 'nullable', 'integer', 'exists:group_users,id'],
            'zone_id' => [$isBoss ? 'required' : 'nullable', 'integer', 'exists:zone,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $role = Role::query()->where('guard_name', 'web')->where('name', $validated['role'])->firstOrFail();
        if ($isBoss) {
            $this->assertBossGroup($actor, (int) $validated['group_user_id']);
            $this->assertBossZone($actor, (int) $validated['zone_id']);
            $this->assertBossContract($actor, $validated['contract_id'] ?? null);
            $validated['organization_id'] = $actor->organization_id;
        }
        $this->assertAssignmentScope($validated);

        DB::transaction(function () use ($validated, $actor, $role, $isSuperAdmin): void {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                // User::$casts hashes this value exactly once.
                'password' => $validated['password'],
                'role_id' => $role->id,
                'group_user_id' => $validated['group_user_id'] ?? null,
                'zone_id' => $validated['zone_id'] ?? null,
                'contract_id' => $validated['contract_id'] ?? null,
                'organization_id' => $validated['organization_id'] ?? ($isSuperAdmin ? null : $actor->organization_id),
            ]);

            $user->syncRoles([$role]);
            app(AuditLogger::class)->record('user.created', $user, null, [
                'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                'zone_id' => $user->zone_id, 'group_user_id' => $user->group_user_id,
                'organization_id' => $user->organization_id, 'role' => $role->name,
            ]);
        });

        return redirect()->route('users.index')->with('success', 'کاربر جدید با موفقیت ایجاد شد.');
    }

    public function edit(Request $request, User $user)
    {
        Gate::authorize('update', $user);
        $actor = $request->user();
        $user->load(['roles', 'zone', 'groupUser']);

        return view('users.edit', ['user' => $user] + $this->formOptions($actor));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('update', $user);
        $actor = $request->user();
        $isBoss = $actor->hasRole('boss');
        $isSuperAdmin = $actor->hasRole('superadmin');
        $roleNames = $this->assignableRoles($actor)->pluck('name')->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($roleNames)],
            'group_user_id' => [$isBoss ? 'required' : 'nullable', 'integer', 'exists:group_users,id'],
            'zone_id' => [$isBoss ? 'required' : 'nullable', 'integer', 'exists:zone,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $role = Role::query()->where('guard_name', 'web')->where('name', $validated['role'])->firstOrFail();
        if ($isBoss) {
            $this->assertBossGroup($actor, (int) $validated['group_user_id']);
            $this->assertBossZone($actor, (int) $validated['zone_id']);
            $this->assertBossContract($actor, $validated['contract_id'] ?? null);
            $validated['organization_id'] = $actor->organization_id;
        }
        $this->assertAssignmentScope($validated);
        if ($isSuperAdmin) {
            $this->assertOrganizationChangeSafe($user, $validated['organization_id'] ?? null);
        }

        DB::transaction(function () use ($validated, $user, $role, $actor): void {
            $before = [
                'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                'zone_id' => $user->zone_id, 'group_user_id' => $user->group_user_id,
                'organization_id' => $user->organization_id, 'role' => $user->getRoleNames()->all(),
            ];
            $data = [
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'role_id' => $role->id,
                'group_user_id' => array_key_exists('group_user_id', $validated) ? $validated['group_user_id'] : $user->group_user_id,
                'zone_id' => array_key_exists('zone_id', $validated) ? $validated['zone_id'] : $user->zone_id,
                'contract_id' => array_key_exists('contract_id', $validated) ? $validated['contract_id'] : $user->contract_id,
                'organization_id' => array_key_exists('organization_id', $validated) ? $validated['organization_id'] : $user->organization_id,
            ];
            if (filled($validated['password'] ?? null)) {
                $data['password'] = $validated['password'];
            }

            $user->update($data);
            $user->syncRoles([$role]);
            $after = [
                'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
                'zone_id' => $user->zone_id, 'group_user_id' => $user->group_user_id,
                'organization_id' => $user->organization_id, 'role' => $user->getRoleNames()->all(),
            ];
            app(AuditLogger::class)->record('user.updated', $user, $before, $after);
            if ($before['role'] !== $after['role']) {
                app(AuditLogger::class)->record('user.role_changed', $user, ['role' => $before['role']], ['role' => $after['role']]);
            }
        });

        return redirect()->route('users.index')->with('success', 'اطلاعات کاربر به‌روزرسانی شد.');
    }

    public function destroy(User $user)
    {
        abort_if((int) $user->id === (int) request()->user()->id, 403);
        Gate::authorize('delete', $user);
        app(AuditLogger::class)->record('user.deleted', $user, [
            'id' => $user->id, 'name' => $user->name, 'username' => $user->username,
            'zone_id' => $user->zone_id, 'group_user_id' => $user->group_user_id,
            'roles' => $user->getRoleNames()->all(),
        ], null);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'کاربر حذف شد.');
    }

    private function assignableRoles(User $actor)
    {
        $query = Role::query()->where('guard_name', 'web');
        if ($actor->hasRole('boss')) {
            $query->whereIn('name', UserPolicy::BOSS_ASSIGNABLE_ROLES);
        }

        return $query->orderBy('name')->get();
    }

    /** @return array{roles: mixed, zones: mixed, groupusers: mixed, contracts: mixed, isBoss: bool, isSuperAdmin: bool, organizations: mixed} */
    private function formOptions(User $actor): array
    {
        $isBoss = $actor->hasRole('boss');
        $isSuperAdmin = $actor->hasRole('superadmin');

        return [
            'roles' => $this->assignableRoles($actor),
            'zones' => $this->manageableZones($actor)->orderBy('id')->get(),
            'groupusers' => $isBoss
                ? GroupUser::query()->whereIn('id', $actor->managedGroupIds())->orderBy('name')->get()
                : GroupUser::query()->orderBy('name')->get(),
            'contracts' => $isBoss
                ? Contract::query()->where('creator_id', $actor->id)->orderBy('id')->get()
                : Contract::query()->orderBy('id')->get(),
            'isBoss' => $isBoss,
            'isSuperAdmin' => $isSuperAdmin,
            'organizations' => $isSuperAdmin
                ? Organization::query()->orderBy('name')->get(['id', 'name', 'code', 'status'])
                : collect(),
        ];
    }

    private function manageableZones(User $actor)
    {
        $query = Zone::query();
        if ($actor->hasRole('boss')) {
            $query->whereKey($actor->zone_id)->where('organization_id', $actor->organization_id);
        } elseif (! $actor->hasRole('superadmin')) {
            $query->where('organization_id', $actor->organization_id);
        }

        return $query;
    }

    private function assertBossZone(User $actor, int $zoneId): void
    {
        abort_unless($this->manageableZones($actor)->whereKey($zoneId)->exists(), 403);
    }

    private function assertBossGroup(User $actor, int $groupId): void
    {
        abort_unless(in_array($groupId, $actor->managedGroupIds(), true)
            && GroupUser::query()->whereKey($groupId)
                ->where('organization_id', $actor->organization_id)
                ->where('zone_id', $actor->zone_id)->exists(), 403);
    }

    private function assertBossContract(User $actor, mixed $contractId): void
    {
        if ($contractId === null) {
            return;
        }

        abort_unless(Contract::query()->where('creator_id', $actor->id)->whereKey($contractId)->exists(), 403);
    }

    /** @param array<string, mixed> $validated */
    private function assertAssignmentScope(array $validated): void
    {
        $organizationId = isset($validated['organization_id']) && $validated['organization_id'] !== ''
            ? (int) $validated['organization_id'] : null;
        $group = ! empty($validated['group_user_id']) ? GroupUser::query()->find((int) $validated['group_user_id']) : null;
        $zone = ! empty($validated['zone_id']) ? Zone::query()->find((int) $validated['zone_id']) : null;

        if ($group && (($group->organization_id === null && $organizationId !== null)
            || ($group->organization_id !== null && (int) $group->organization_id !== (int) $organizationId))) {
            throw ValidationException::withMessages(['organization_id' => 'سازمان کاربر باید با سازمان گروه کاربران یکسان باشد.']);
        }
        if ($zone && (($zone->organization_id === null && $organizationId !== null)
            || ($zone->organization_id !== null && (int) $zone->organization_id !== (int) $organizationId))) {
            throw ValidationException::withMessages(['organization_id' => 'سازمان کاربر باید با سازمان Zone یکسان باشد.']);
        }
        if ($group && $group->zone_id !== null && (int) $group->zone_id !== (int) ($validated['zone_id'] ?? 0)) {
            throw ValidationException::withMessages(['zone_id' => 'Zone کاربر باید با Zone گروه کاربران یکسان باشد.']);
        }
    }

    private function assertOrganizationChangeSafe(User $user, mixed $organizationId): void
    {
        $newOrganizationId = $organizationId === null || $organizationId === '' ? null : (int) $organizationId;
        if ((int) $user->organization_id === (int) $newOrganizationId) return;

        $references = [
            ['operations', 'user_id', 'عملیات'],
            ['maintenance_cases', 'reported_by', 'پرونده‌های تعمیر'],
            ['maintenance_cases', 'last_edited_by', 'ویرایش پرونده‌های تعمیر'],
            ['operation_supervisors', 'user_id', 'تأییدیه عملیات'],
            ['workflow_transfers', 'user_id_from', 'انتقال‌های گردش‌کار'],
            ['workflow_transfers', 'user_id_to', 'دریافت‌های گردش‌کار'],
            ['work_orders', 'requester_id', 'دستورکارهای درخواستی'],
            ['work_orders', 'created_by', 'دستورکارهای ایجادشده'],
            ['work_orders', 'assigned_to', 'دستورکارهای تخصیص‌یافته'],
            ['workflow_tasks', 'assigned_to', 'وظایف گردش‌کار'],
            ['saved_reports', 'user_id', 'گزارش‌های ذخیره‌شده'],
        ];
        foreach ($references as [$table, $column, $label]) {
            if (Schema::hasTable($table) && DB::table($table)->where($column, $user->id)->exists()) {
                throw ValidationException::withMessages(['organization_id' => "تغییر سازمان این کاربر به دلیل وجود {$label} ممکن نیست."]);
            }
        }

        if ($user->hasRole('boss') && Schema::hasTable('boss_managed_groups')) {
            $hasForeignManagedGroup = DB::table('boss_managed_groups AS managed')
                ->join('group_users AS groups', 'groups.id', '=', 'managed.group_user_id')
                ->where('managed.boss_user_id', $user->id)
                ->where(function ($query) use ($newOrganizationId): void {
                    $query->whereNull('groups.organization_id')->orWhere('groups.organization_id', '!=', $newOrganizationId);
                })->exists();
            if ($hasForeignManagedGroup) {
                throw ValidationException::withMessages(['organization_id' => 'گروه‌های مدیریتی Boss با سازمان جدید سازگار نیستند.']);
            }
        }
    }
}
