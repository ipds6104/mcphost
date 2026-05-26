<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bps_variable_maps', function (Blueprint $table) {
            $table->id();
            $table->string('domain_code', 10);
            $table->string('domain_level', 20);
            $table->string('indicator_slug', 80);
            $table->unsignedInteger('var_id');
            $table->text('notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['domain_code', 'indicator_slug']);
            $table->index('domain_level');
            $table->index('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bps_variable_maps');
    }
};
