<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function showLogin()
    {
        return view('storefront.auth.login');
    }

    public function login(Request $request)
    {
        $email = $request->input('email');
        $request->merge(['email' => is_string($email) ? Str::lower(trim($email)) : $email]);
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();
        if (!$user || !$user->isCustomer() || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak valid.',
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('storefront.account.index'))->with('status', 'Berhasil masuk ke akun AntaMart.');
    }

    public function showRegistration()
    {
        return view('storefront.auth.register');
    }

    public function register(Request $request)
    {
        $email = $request->input('email');
        $request->merge(['email' => is_string($email) ? Str::lower(trim($email)) : $email]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('storefront.account.index')->with('status', 'Akun berhasil dibuat.');
    }

    public function account(Request $request)
    {
        return view('storefront.account.index', ['user' => $request->user('web')]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home')->with('status', 'Kamu telah keluar dari akun.');
    }
}
