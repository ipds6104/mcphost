<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Pastikan tabel tracking seeder_history tersedia
        if (! Schema::hasTable('seeder_history')) {
            Schema::create('seeder_history', function ($table) {
                $table->id();
                $table->string('seeder')->unique();
                $table->timestamp('seeded_at')->useCurrent();
            });
        }

        // 2. Daftar seeder yang akan dijalankan secara idempoten
        $seeders = [
            UserSeeder::class,
            McpServerSeeder::class,
            BpsRegencySeeder::class,
            BpsMethodologyBreaksSeeder::class,
            BpsGroundTruthsSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            $alreadyRun = DB::table('seeder_history')->where('seeder', $seeder)->exists();
            if (! $alreadyRun) {
                $this->call($seeder);
                DB::table('seeder_history')->insert(['seeder' => $seeder]);
            }
        }
    }
}
