<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OwnerAuthController extends Controller
{
    public function showLogin()
    {
        return view('owner.auth.login');
    }

    public function login(Request $request)
    {
        $email = $request->input('email');
        $request->merge(['email' => is_string($email) ? Str::lower(trim($email)) : $email]);
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $owner = User::query()->where('email', $credentials['email'])->first();
        if (!$owner || !$owner->isStoreOwner() || !Hash::check($credentials['password'], $owner->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak valid.',
            ]);
        }

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();

        return redirect()->intended(route('owner.dashboard'))->with('status', 'Berhasil masuk ke console AntaMart.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('owner.login')->with('status', 'Kamu telah keluar dari console.');
    }
}
