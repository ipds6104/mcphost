<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bps_regencies', function (Blueprint $blueprint) {
            $blueprint->uuid('id')->primary();
            $blueprint->string('code')->unique(); // Kode BPS Resmi (e.g. 6104 untuk Mempawah, 3171 untuk Jakarta Selatan)
            $blueprint->string('name'); // Nama valid resmi
            $blueprint->string('province'); // Provinsi resmi
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bps_regencies');
    }
};
