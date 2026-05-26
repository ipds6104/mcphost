<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * BpsVariableMap — Peta var_id yang telah terverifikasi dari hasil eksekusi agen.
 *
 * Tabel ini menyimpan pengetahuan domain-spesifik yang dipelajari secara mandiri
 * oleh agen setiap kali berhasil mengambil data dari BPS WebAPI.
 * Digunakan untuk mengeliminasi pencarian var_id yang redundant pada eksekusi berikutnya.
 *
 * @property string $domain_code       Kode domain BPS (e.g. '5171', '5100', '0000')
 * @property string $domain_level      Level wilayah ('kota', 'kabupaten', 'provinsi', 'nasional')
 * @property string $indicator_slug    Slug indikator (e.g. 'ipm', 'gini', 'tpt')
 * @property int    $var_id            ID variabel di BPS WebAPI
 * @property int|null $vervar_id       ID variabel vertikal (filter sub-wilayah). NULL = tidak perlu filter.
 * @property string|null $vervar_label Label human-readable untuk vervar_id (debug hanya)
 * @property string|null $notes        Catatan khusus (e.g. cross-domain pivot notes)
 * @property \Carbon\Carbon|null $verified_at  Waktu terakhir data ini berhasil diverifikasi
 */
class BpsVariableMap extends Model
{
    protected $fillable = [
        'domain_code',
        'route_to_domain',
        'domain_level',
        'indicator_slug',
        'var_id',
        'vervar_id',
        'vervar_label',
        'notes',
        'verified_at',
    ];

    protected $casts = [
        'var_id'      => 'integer',
        'vervar_id'   => 'integer',
        'verified_at' => 'datetime',
    ];

    /**
     * Temukan var_id untuk indikator tertentu di domain tertentu.
     * Fallback ke domain level yang sama jika kode domain spesifik tidak ditemukan.
     */
    public static function findVarId(string $domainCode, string $indicatorSlug): ?self
    {
        // Coba exact domain match dulu
        $exact = static::where('domain_code', $domainCode)
            ->where('indicator_slug', $indicatorSlug)
            ->first();

        if ($exact) {
            return $exact;
        }

        // Fallback: cari berdasarkan level yang sama (misalnya semua 'kota' share var_id yang sama)
        $level = static::where('domain_code', $domainCode)->value('domain_level');
        if ($level) {
            return static::where('domain_level', $level)
                ->where('indicator_slug', $indicatorSlug)
                ->whereNotNull('verified_at')
                ->orderByDesc('verified_at')
                ->first();
        }

        return null;
    }

    /**
     * Rekam var_id baru yang ditemukan agen. Dipanggil dari TestBpsAgent command.
     */
    public static function recordDiscovery(
        string $domainCode,
        string $domainLevel,
        string $indicatorSlug,
        int $varId,
        ?string $notes = null,
        ?int $vervarId = null,
        ?string $vervarLabel = null,
        ?string $routeToDomain = null,
    ): self {
        return static::updateOrCreate(
            ['domain_code' => $domainCode, 'indicator_slug' => $indicatorSlug],
            [
                'route_to_domain' => $routeToDomain,
                'domain_level'  => $domainLevel,
                'var_id'        => $varId,
                'vervar_id'     => $vervarId,
                'vervar_label'  => $vervarLabel,
                'notes'         => $notes,
                'verified_at'   => now(),
            ]
        );
    }
}
