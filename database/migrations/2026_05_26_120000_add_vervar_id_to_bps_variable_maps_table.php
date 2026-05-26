<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan vervar_id ke bps_variable_maps.
 *
 * vervar_id adalah "Variabel Vertikal" — filter sub-wilayah dari respons BPS.
 * Contoh: domain 5171 (Denpasar) mengembalikan data untuk SEMUA kab/kota di Bali.
 * vervar_id memungkinkan agen langsung memfilter baris "Kota Denpasar" tanpa perlu
 * melakukan keyword scan tambahan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bps_variable_maps', function (Blueprint $table) {
            // ID vervar yang sesuai untuk wilayah domain_code ini
            // NULL berarti tidak perlu filter vervar (data hanya untuk 1 wilayah)
            $table->unsignedInteger('vervar_id')->nullable()->after('var_id');

            // Label vervar untuk memudahkan debugging (opsional, tidak digunakan LLM)
            $table->string('vervar_label', 120)->nullable()->after('vervar_id');
        });
    }

    public function down(): void
    {
        Schema::table('bps_variable_maps', function (Blueprint $table) {
            $table->dropColumn(['vervar_id', 'vervar_label']);
        });
    }
};
