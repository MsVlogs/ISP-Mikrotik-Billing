<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use environment-provided bootstrap credentials; never ship personal/default credentials.
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new \RuntimeException('SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD must be set before seeding the super admin.');
        }

        $superAdmin = User::create([
            'name' => env('SUPER_ADMIN_NAME', 'X-Link Super Admin'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $superAdmin->assignRole('Super Admin');
    }
}
