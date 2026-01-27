<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the admin user from environment variables.
     *
     * Required env vars: ADMIN_EMAIL, ADMIN_PASSWORD
     * Optional env var: ADMIN_NAME (defaults to 'Admin')
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command->warn('Skipping AdminUserSeeder: ADMIN_EMAIL or ADMIN_PASSWORD not set');

            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Admin user created/verified: {$email}");
    }
}
