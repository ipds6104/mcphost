<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bps_methodology_breaks', function (Blueprint $table) {
            $table->id();
            $table->string('sector', 100)->index();
            $table->smallInteger('break_year')->index();
            $table->string('break_type', 80);
            $table->string('old_version', 200)->nullable();
            $table->string('new_version', 200)->nullable();
            $table->text('description');
            $table->string('brs_reference', 255)->nullable();
            $table->boolean('is_backcasted')->default(false);
            $table->smallInteger('safe_comparison_start_year')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bps_methodology_breaks');
    }
};
