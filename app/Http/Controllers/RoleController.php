<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->paginate(20);

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
        ]);

        Role::create([
            'name' => $request->name,
            'guard_name' => 'web'
        ]);

        return redirect()
            ->route('roles.index')
            ->with('success', 'نقش با موفقیت ایجاد شد.');
    }

    public function edit(Role $role)
    {
        $groupedPermissions = Permission::orderBy('name')
            ->get()
            ->groupBy(function ($permission) {
                return explode('.', $permission->name)[0];
            });

        return view('roles.edit', compact(
            'role',
            'groupedPermissions'
        ));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        $role->syncPermissions(
            $request->permissions ?? []
        );

        return redirect()
            ->route('roles.index')
            ->with('success', 'اطلاعات با موفقیت ذخیره شد.');
    }

    public function destroy(Role $role)
    {
        if ($role->name == 'superadmin') {
            return back()->with('error', 'حذف SuperAdmin مجاز نیست.');
        }

        $role->delete();

        return back()->with('success', 'نقش حذف شد.');
    }
}