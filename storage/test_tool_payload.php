<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Sending direct Http request to DeepSeek Proxy WITH tools...\n";
$startTime = hrtime(true);

try {
    $response = Http::withHeaders([
        'Authorization' => 'Bearer sk-af6376fcf20b4a148672456a6cae1902',
        'Content-Type' => 'application/json',
    ])
    ->timeout(20)
    ->post('https://ai.dvlpid.my.id/v1/chat/completions', [
        'model' => 'gemini-3-flash',
        'messages' => [
            ['role' => 'user', 'content' => 'Tampilkan data regional report untuk Sleman.']
        ],
        'tools' => [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'fetch_regional_report',
                    'description' => 'Mengambil daftar tabel statistik resmi BPS',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'regency_code' => ['type' => 'string']
                        ],
                        'required' => ['regency_code']
                    ]
                ]
            ]
        ],
        'tool_choice' => 'auto'
    ]);

    $latency = (hrtime(true) - $startTime) / 1e6;
    echo "Completed in " . number_format($latency, 2) . " ms\n";
    echo "Status Code: " . $response->status() . "\n";
    echo "Body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
