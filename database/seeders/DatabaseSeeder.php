<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        if (! User::where('email', 'ihzakarunia@bps.go.id')->exists()) {
            User::factory()->create([
                'name' => 'Ihza Karunia',
                'email' => 'ihzakarunia@bps.go.id',
                'password' => bcrypt('ihzakarunia'),
            ]);
        }

        $this->call([
            McpServerSeeder::class,
            BpsRegencySeeder::class,
            BpsMethodologyBreaksSeeder::class,
            BpsGroundTruthsSeeder::class,
        ]);
    }
}
