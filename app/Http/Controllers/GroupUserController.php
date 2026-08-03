<?php

namespace App\Http\Controllers;

use App\Models\GroupUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupUserController extends Controller
{

    public function index()
    {
        $groupusers = GroupUser::query()
            ->withCount('users')
            ->orderByDesc('id')
            ->paginate(20);

        return view('groupuser.index', compact('groupusers'));
    }


    public function create()
    {
        return view('groupuser.create');
    }



    public function store(Request $request)
    {

        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

        ]);


        GroupUser::create($validated);


        return redirect()
            ->route('groupusers.index')
            ->with(
                'success',
                'گروه کاربری با موفقیت ایجاد شد.'
            );

    }





    public function edit(GroupUser $groupuser)
    {
        return view(
            'groupuser.edit',
            compact('groupuser')
        );
    }





    public function update(Request $request, GroupUser $groupuser)
    {

        $validated = $request->validate([

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

        ]);



        $groupuser->update($validated);



        return redirect()
            ->route('groupusers.index')
            ->with(
                'success',
                'گروه کاربری با موفقیت ویرایش شد.'
            );

    }





    public function destroy(GroupUser $groupuser)
    {


        if ($groupuser->users()->count() > 0) {

            return back()
                ->with(
                    'error',
                    'این گروه دارای کاربر است و قابل حذف نیست.'
                );

        }



        $groupuser->delete();



        return redirect()
            ->route('groupusers.index')
            ->with(
                'success',
                'گروه کاربری حذف شد.'
            );

    }

}