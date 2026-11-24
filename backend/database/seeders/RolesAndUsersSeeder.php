<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@docver.com'],
            [
                'name' => 'DocVer System Admin',
                'password' => Hash::make('Admin@123456'),
                'role' => UserRole::SUPER_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // 2. Workspace Owner (Alex Sterling)
        $owner = User::firstOrCreate(
            ['email' => 'alex.sterling@enterprise-legal.com'],
            [
                'name' => 'Alex Sterling',
                'password' => Hash::make('password123'),
                'role' => UserRole::WORKSPACE_OWNER,
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Create Default Enterprise Workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'sterling-partners-llp'],
            [
                'name' => 'Sterling & Partners LLP',
                'owner_id' => $owner->id,
                'plan' => 'enterprise',
                'status' => 'active',
                'max_file_size_mb' => 50,
                'max_page_limit' => 500,
                'retention_days' => 30,
                'settings' => [
                    'auto_purge_retention' => true,
                    'zero_model_retention' => true,
                    'encryption_tier' => 'AES-256',
                ],
            ]
        );

        $workspace->members()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner'],
        ]);

        // 3. Workspace Administrator
        $admin = User::firstOrCreate(
            ['email' => 'sarah.connor@enterprise-legal.com'],
            [
                'name' => 'Sarah Connor',
                'password' => Hash::make('password123'),
                'role' => UserRole::WORKSPACE_ADMIN,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $workspace->members()->syncWithoutDetaching([
            $admin->id => ['role' => 'admin'],
        ]);

        // 4. Standard User / Auditor
        $regularUser = User::firstOrCreate(
            ['email' => 'jordan.lee@enterprise-legal.com'],
            [
                'name' => 'Jordan Lee',
                'password' => Hash::make('password123'),
                'role' => UserRole::USER,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $workspace->members()->syncWithoutDetaching([
            $regularUser->id => ['role' => 'member'],
        ]);
    }
}
