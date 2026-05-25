<?php
// Script helper: tulis migration files dengan PHP murni
// Jalankan: docker compose exec -T web php /var/www/html/storage/gen_migrations.php

$m1 = __DIR__ . '/../database/migrations/2026_05_25_142351_create_bps_api_cache_table.php';
$m2 = __DIR__ . '/../database/migrations/2026_05_25_142356_create_bps_methodology_breaks_table.php';

$content1 = <<<'MIGRATION'
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
MIGRATION;

$content2 = <<<'MIGRATION'
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
MIGRATION;

file_put_contents($m1, $content1);
file_put_contents($m2, $content2);
echo "✅ Both migration files written successfully.\n";
echo "   M1: {$m1}\n";
echo "   M2: {$m2}\n";
