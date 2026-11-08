<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class StoreOwnerSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('store_owner.name'));
        $email = Str::lower(trim((string) config('store_owner.email')));
        $password = (string) config('store_owner.password');

        if ($name === '' && $email === '' && $password === '') {
            return;
        }

        if ($name === '' || $email === '' || $password === '') {
            throw new RuntimeException('Set STORE_OWNER_NAME, STORE_OWNER_EMAIL, and STORE_OWNER_PASSWORD together to seed a store owner.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('STORE_OWNER_EMAIL must be a valid email address.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException('STORE_OWNER_PASSWORD must contain at least 12 characters.');
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser) {
            if (!$existingUser->isStoreOwner()) {
                throw new RuntimeException('The configured store-owner email belongs to a non-owner account. No role was changed.');
            }

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'store_owner',
        ]);
    }
}
