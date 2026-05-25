<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bps_api_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key', 255)->unique();
            $table->string('domain_code', 10)->nullable()->index();
            $table->string('endpoint_type', 50)->nullable();
            $table->jsonb('response_json');
            $table->timestampTz('fetched_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->boolean('is_stale')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bps_api_cache');
    }
};
