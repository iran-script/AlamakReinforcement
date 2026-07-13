<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * نمایش صفحه لاگین
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * لاگین کاربر
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $credentials = [
            'username' => $request->username,
            'password' => $request->password
        ];

        if (Auth::attempt($credentials)) {

            $request->session()->regenerate();

            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'mobile' => 'شماره موبایل یا رمز عبور اشتباه است'
        ])->withInput();
    }

    /**
     * خروج کاربر
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * گرفتن کاربر فعلی (برای استفاده در UI یا API ساده)
     */
    public function me()
    {
        return response()->json([
            'user' => Auth::user()
        ]);
    }

    /**
     * تغییر رمز عبور
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6'
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'رمز فعلی اشتباه است'
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return back()->with('success', 'رمز عبور تغییر کرد');
    }
}