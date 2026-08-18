<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.admin_email', env('ADMIN_EMAIL', 'admin@example.com'));
        $password = config('app.admin_password', env('ADMIN_PASSWORD', 'change_me_admin_password'));
        $name = config('app.admin_name', env('ADMIN_NAME', 'System Administrator'));

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (!$user->hasRole(Role::SUPER_ADMIN)) {
            $user->assignRole(Role::SUPER_ADMIN);
        }

        if (app()->environment('local', 'testing') && env('ADMIN_PASSWORD', '') === '') {
            $this->command?->info("Admin account: {$email} / {$password}");
        }
    }
}
