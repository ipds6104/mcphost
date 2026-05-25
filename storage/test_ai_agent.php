<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Ai\Agents\GovtAnalyticsAgent;
use App\Ai\Tools\BpsApiTool;
use App\Services\BpsApiService;
use Illuminate\Support\Facades\Http;

Http::globalRequestMiddleware(function ($request) {
    echo ">>> HTTP Request: " . $request->getMethod() . " " . $request->getUri()->__toString() . "\n";
    if (str_contains($request->getUri()->__toString(), 'ai.dvlpid.my.id')) {
        echo "Headers: " . json_encode($request->getHeaders()) . "\n";
        echo "Body: " . substr($request->getBody()->__toString(), 0, 1000) . "\n\n";
    }
    return $request;
});

Http::globalResponseMiddleware(function ($response) {
    echo "<<< HTTP Response: " . $response->getStatusCode() . "\n";
    echo "Body: " . substr($response->getBody()->__toString(), 0, 1000) . "\n\n";
    return $response;
});

$providerName = config('ai.default', 'deepseek');
$modelName = config('ai.default_model', 'gemini-3-flash');

$bpsService = new BpsApiService((string) config('services.bps.key', ''));
$state = (object) ['stepIndex' => 0, 'steps' => []];

$tools = [];
$bpsToolDefinitions = [
    [
        'name' => 'fetch_regional_report',
        'description' => 'Mengambil daftar tabel statistik resmi BPS untuk suatu kabupaten/kota berdasarkan kode domain BPS (data real-time dari webapi.bps.go.id).',
    ],
    [
        'name' => 'get_bps_indicator',
        'description' => 'Mengambil data dari tabel statistik BPS tertentu untuk suatu wilayah (data real-time dari webapi.bps.go.id).',
    ],
    [
        'name' => 'compare_regencies',
        'description' => 'Membandingkan data statistik BPS antar beberapa kabupaten/kota secara paralel (data real-time dari webapi.bps.go.id).',
    ],
    [
        'name' => 'search_statistics',
        'description' => 'Mencari variabel dan indikator statistik BPS berdasarkan kata kunci untuk suatu wilayah (data real-time dari webapi.bps.go.id).',
    ],
];

foreach ($bpsToolDefinitions as $toolDef) {
    $tools[] = new BpsApiTool(
        toolName: $toolDef['name'],
        toolDescription: $toolDef['description'],
        bpsService: $bpsService,
        chatId: 'test_grounding_agent',
        state: $state
    );
}

$agent = new GovtAnalyticsAgent(
    instructions: "Anda adalah Asisten Analitis Statistik Pemerintah Indonesia. Selalu gunakan Bahasa Indonesia yang baik dan profesional.",
    tools: $tools,
    messages: []
);

try {
    echo "Sending prompt to agent...\n";
    $response = $agent->prompt(
        prompt: "Tampilkan tren IPM Kabupaten Mempawah dari tahun 2010 sampai 2024 dan analisis perkembangannya",
        provider: $providerName,
        model: $modelName
    );

    echo "=== Final Response ===\n";
    echo $response->text . "\n";
    echo "======================\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
