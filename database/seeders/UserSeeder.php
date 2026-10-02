<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Admin', 'admin@bangued.ph', 'admin'],
            ['Resident', 'resident@bangued.ph', 'user'],
        ];

        $hasRole = Schema::hasColumn('users', 'role');

        foreach ($users as [$name, $email, $role]) {
            $attributes = [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => 'password',
            ];

            if ($hasRole) {
                $attributes['role'] = $role;
            }

            User::updateOrCreate(['email' => $email], $attributes);
        }
    }
}