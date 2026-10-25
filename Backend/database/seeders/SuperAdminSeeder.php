<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('netslip.super_admin.email');
        $password = config('netslip.super_admin.password');
        $generated = false;
        if (! is_string($password) || $password === '') {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->fill(['name' => 'NetSlip Super Admin', 'password' => $password, 'is_active' => true]);
            $user->email_verified_at = now();
            $user->save();
            if ($generated && $this->command !== null) {
                $this->command->warn("Super-admin created: {$email} / {$password}  (set NETSLIP_SUPER_ADMIN_PASSWORD to choose your own; change it after first login)");
            }
        }
        $user->assignRole(Role::SUPER_ADMIN->value);
    }
}
