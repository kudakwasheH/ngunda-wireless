<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, PackageSeeder::class]);

        // The initial super administrator is created ONLY from environment values.
        // No default password is ever shipped. Set NWS_ADMIN_EMAIL / NWS_ADMIN_PASSWORD before seeding.
        $email = env('NWS_ADMIN_EMAIL');
        $password = env('NWS_ADMIN_PASSWORD');

        if ($email && $password && ! User::where('email', $email)->exists()) {
            $user = new User(['name' => env('NWS_ADMIN_NAME', 'Super Administrator'), 'email' => $email,
                'password' => $password]);
            $user->role_id = Role::where('slug', Role::SUPER_ADMIN)->value('id');
            $user->is_active = true;
            $user->save();
            $this->command?->info("Super administrator created: {$email}");
        } elseif (! User::exists()) {
            $this->command?->warn('No admin user created. Set NWS_ADMIN_EMAIL and NWS_ADMIN_PASSWORD in .env then re-run: php artisan db:seed');
        }
    }
}
