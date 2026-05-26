<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bps_variable_maps', function (Blueprint $table) {
            // Target domain to route queries to (e.g. '3100' for Jakarta Timur)
            $table->string('route_to_domain', 10)->nullable()->after('domain_code');
        });
    }

    public function down(): void
    {
        Schema::table('bps_variable_maps', function (Blueprint $table) {
            $table->dropColumn('route_to_domain');
        });
    }
};
