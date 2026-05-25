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
        Schema::create('bps_ground_truths', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('domain_code', 10);
            $table->integer('year');
            $table->string('indicator_code', 30);
            $table->double('value');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['domain_code', 'year', 'indicator_code'], 'bps_ground_truths_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bps_ground_truths');
    }
};
