<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Zone;
use App\Models\GroupUser;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{

    public function index()
    {
        $query = User::query()
            ->with([
                'roles',
                'zone',
                'groupUser'
            ]);

        $currentUser = auth()->user();


        if ($currentUser->hasRole('boss')) {

            $query->where('group_user_id', $currentUser->group_user_id);
        }


        $users = $query
            ->orderByDesc('id')
            ->paginate(20);


        return view('users.index', compact('users'));
    }






    public function create()
    {
        $currentUser = auth()->user();

        // نقش‌ها
        if ($currentUser->hasRole('boss')) {
            $roles = Role::where('name', 'supervisor')->get();
        } else {
            $roles = Role::orderBy('name')->get();
        }

        // زون‌ها
        $zones = Zone::query();

        if ($currentUser->hasRole('boss')) {
            $zones->where('creator_id', $currentUser->id);
        }

        $zones = $zones->orderBy('id')->get();

        // گروه‌های کاربری
        $groupusers = GroupUser::query();

        if ($currentUser->hasRole('boss')) {
            $groupusers->where('id', $currentUser->group_user_id);
        }

        $groupusers = $groupusers->orderBy('name')->get();

        // قراردادها
        $contracts = Contract::all();

        return view('users.create', compact(
            'roles',
            'zones',
            'groupusers',
            'contracts'
        ));
    }





    public function store(Request $request)
    {

        $validated = $request->validate([


            'name' => [
                'required',
                'string',
                'max:255'
            ],



            'username' => [
                'required',
                'string',
                'max:100',
                'unique:users,username'
            ],



            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email'
            ],



            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed'
            ],



            'role' => [
                'required',
                'exists:roles,name'
            ],



            'group_user_id' => [
                'nullable',
                'exists:group_users,id'
            ],



            'zone_id' => [
                'nullable',
                'exists:zone,id'
            ],

            'contract_id' =>
            [
                'nullable',
                'exists:contracts,id'
            ]



        ]);
        $currentUser = auth()->user();

        if ($currentUser->hasRole('boss')) {

            if ($validated['role'] !== 'supervisor') {
                abort(403, 'شما اجازه ایجاد این نقش را ندارید.');
            }

            $validated['group_user_id'] = $currentUser->group_user_id;

            if (!empty($validated['zone_id'])) {

                $zoneExists = Zone::where('id', $validated['zone_id'])
                    ->where('creator_id', $currentUser->id)
                    ->exists();

                if (!$zoneExists) {
                    abort(403, 'زون انتخاب شده معتبر نیست.');
                }
            }
        }






        $user = User::create([

            'name' => $validated['name'],

            'username' => $validated['username'],

            'email' => $validated['email'],

            'contract_id' => $validated['contract_id'],

            'password' => Hash::make($validated['password']),


            'group_user_id' =>
            $validated['group_user_id'] ?? null,


            'zone_id' =>
            $validated['zone_id'] ?? null,

        ]);



        // ثبت نقش کاربر
        $user->syncRoles([
            $validated['role']
        ]);



        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'کاربر جدید با موفقیت ایجاد شد.'
            );
    }







    public function edit(User $user)
    {
        $this->checkUserAccess($user);

        $currentUser = auth()->user();

        $user->load([
            'roles',
            'zone',
            'groupUser'
        ]);

        // نقش‌ها
        if ($currentUser->hasRole('boss')) {
            $roles = Role::where('name', 'supervisor')->get();
        } else {
            $roles = Role::orderBy('name')->get();
        }

        // زون‌ها
        $zones = Zone::query();

        if ($currentUser->hasRole('boss')) {
            $zones->where('creator_id', $currentUser->id);
        }

        $zones = $zones->orderBy('id')->get();

        // گروه‌های کاربری
        $groupusers = GroupUser::query();

        if ($currentUser->hasRole('boss')) {
            $groupusers->where('id', $currentUser->group_user_id);
        }

        $groupusers = $groupusers->orderBy('name')->get();

        $contracts = Contract::all();

        return view('users.edit', compact(
            'user',
            'roles',
            'zones',
            'groupusers',
            'contracts'
        ));
    }







    public function update(Request $request, User $user)
    {

        $this->checkUserAccess($user);

        $validated = $request->validate([


            'name' => [
                'required',
                'string',
                'max:255'
            ],



            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')
                    ->ignore($user->id)
            ],



            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user->id)
            ],



            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed'
            ],



            'role' => [
                'required',
                'exists:roles,name'
            ],



            'group_user_id' => [
                'nullable',
                'exists:group_users,id'
            ],



            'zone_id' => [
                'nullable',
                'exists:zone,id'
            ],

            'contract_id' =>
            [
                'nullable',
                'exists:contracts,id'
            ]


        ]);

        $currentUser = auth()->user();
        if ($currentUser->hasRole('boss')) {

            // فقط اجازه ثبت نقش Supervisor
            if ($validated['role'] !== 'supervisor') {
                abort(403, 'شما اجازه تغییر این نقش را ندارید.');
            }

            // گروه کاربری همیشه گروه خودش باشد
            $validated['group_user_id'] = $currentUser->group_user_id;

            // زون باید متعلق به خود رئیس باشد
            if (!empty($validated['zone_id'])) {

                $zoneExists = Zone::where('id', $validated['zone_id'])
                    ->where('creator_id', $currentUser->id)
                    ->exists();

                if (!$zoneExists) {
                    abort(403, 'زون انتخاب شده معتبر نیست.');
                }
            }
        }





        $data = [


            'name' => $validated['name'],


            'username' => $validated['username'],


            'email' => $validated['email'],



            'group_user_id' =>
            $validated['group_user_id'] ?? null,



            'zone_id' =>
            $validated['zone_id'] ?? null,

            'contract_id' =>
            $validated['contract_id'] ?? null,



        ];





        if (!empty($validated['password'])) {

            $data['password'] =
                Hash::make($validated['password']);
        }




        $user->update($data);




        // بروزرسانی نقش
        $user->syncRoles([
            $validated['role']
        ]);




        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'اطلاعات کاربر با موفقیت به‌روزرسانی شد.'
            );
    }








    public function destroy(User $user)
    {

        $this->checkUserAccess($user);
        if ($user->id === auth()->id()) {


            return back()->with(
                'error',
                'امکان حذف حساب کاربری خودتان وجود ندارد.'
            );
        }



        $user->delete();



        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'کاربر با موفقیت حذف شد.'
            );
    }

    private function checkUserAccess(User $user)
    {

        $currentUser = auth()->user();


        // supervisor همه دسترسی دارد
        if ($currentUser->hasRole('superadmin')) {
            return true;
        }



        // boss فقط افراد زون خودش
        if (
            $currentUser->hasRole('boss')
            &&
            $user->geoup_user_id == $currentUser->geoup_user_id
        ) {
            return true;
        }



        abort(403, 'شما اجازه دسترسی به این کاربر را ندارید.');
    }
}
