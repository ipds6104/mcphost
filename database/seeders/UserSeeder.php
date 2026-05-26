<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Menggunakan updateOrCreate berbasis 'email' agar 100% idempoten
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'ihzakarunia@bps.go.id'],
            [
                'name' => 'Ihza Karunia',
                'password' => Hash::make('ihzakarunia'),
            ]
        );
    }
}
