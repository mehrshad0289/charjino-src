<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = mb_strtolower((string) config('charjino.admin.email'));
        $password = (string) config('charjino.admin.password');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('charjino.admin.name'),
                'password' => $password,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
