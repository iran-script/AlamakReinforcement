<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with(['roles', 'zone'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get();

        $zones = Zone::query()
            ->orderBy('id')
            ->get();

        return view('users.create', compact('roles', 'zones'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:100',
                'unique:users,username',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],

            'role' => [
                'required',
                'exists:roles,name',
            ],

            'zone_id' => [
                'nullable',
                'exists:zone,id',
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'zone_id' => $validated['zone_id'] ?? null,
        ]);

        // هر کاربر فقط یک نقش دارد
        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('users.index')
            ->with('success', 'کاربر جدید با موفقیت ایجاد شد.');
    }

    public function edit(User $user)
    {
        $user->load(['roles', 'zone']);

        $roles = Role::query()
            ->orderBy('name')
            ->get();

        $zones = Zone::query()
            ->orderBy('id')
            ->get();

        return view('users.edit', compact('user', 'roles', 'zones'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($user->id),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],

            'role' => [
                'required',
                'exists:roles,name',
            ],

            'zone_id' => [
                'nullable',
                'exists:zone,id',
            ],
        ]);

        $data = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'zone_id' => $validated['zone_id'] ?? null,
        ];

        // فقط وقتی رمز وارد شده باشد تغییر می‌کند
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        // نقش قبلی پاک می‌شود و نقش انتخاب‌شده ثبت می‌شود
        $user->syncRoles([$validated['role']]);

        return redirect()
            ->route('users.index')
            ->with('success', 'اطلاعات کاربر با موفقیت به‌روزرسانی شد.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with(
                'error',
                'امکان حذف حساب کاربری خودتان وجود ندارد.'
            );
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'کاربر با موفقیت حذف شد.');
    }
}
