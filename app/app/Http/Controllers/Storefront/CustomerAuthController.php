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
        $skipProfile = $request->boolean('skip_profile');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if (!$skipProfile) {
            $rules = array_merge($rules, [
                'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
                'recipient_name' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
                'province' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'district' => ['nullable', 'string', 'max:100'],
                'village' => ['nullable', 'string', 'max:150'],
                'address_line1' => ['nullable', 'string', 'max:255'],
                'address_line2' => ['nullable', 'string', 'max:255'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            ]);
        }

        $data = $request->validate($rules, [
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
        ]);

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
        ];

        if (!$skipProfile) {
            foreach (['birth_date', 'recipient_name', 'phone', 'province', 'city', 'district', 'village', 'address_line1', 'address_line2', 'postal_code', 'latitude', 'longitude'] as $field) {
                $attributes[$field] = $data[$field] ?? null;
            }
        }

        $user = User::create($attributes);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $statusMessage = $skipProfile
            ? 'Akun berhasil dibuat. Kamu dapat melengkapi tanggal lahir dan alamat pada halaman profil.'
            : 'Akun berhasil dibuat.';

        return redirect()->route('storefront.account.index')->with('status', $statusMessage);
    }

    public function account(Request $request)
    {
        return view('storefront.account.index', ['user' => $request->user('web')]);
    }

    public function showProfile(Request $request)
    {
        return view('storefront.account.profile', ['user' => $request->user('web')]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user('web');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
        ]);

        $user->update($data);

        return redirect()->route('storefront.account.profile.show')
            ->with('status', 'Data profil berhasil diperbarui.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home')->with('status', 'Kamu telah keluar dari akun.');
    }
}
