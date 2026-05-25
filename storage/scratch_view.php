<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$bpsKey = config('services.bps.key');
$baseUrl = config('services.bps.base_url');

// Query static table ID 45 with the correct BPS path: view/domain/6104/model/statictable/lang/ind/id/45/key/{apiKey}/
$url = "{$baseUrl}/view/domain/6104/model/statictable/lang/ind/id/45/key/{$bpsKey}/";
echo "Fetching static table from: {$url}\n";

$response = Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    'Accept' => 'application/json',
])->get($url);

echo "Status Code: " . $response->status() . "\n";
$data = $response->json();
if (isset($data['status']) && $data['status'] === 'OK') {
    echo "STATUS OK!\n";
    echo "Title: " . ($data['data'][0]['title'] ?? 'N/A') . "\n";
    echo "Data rows: " . count($data['data'] ?? []) . "\n";
    print_r(array_slice($data['data'] ?? [], 0, 3));
} else {
    echo "Error response:\n";
    print_r($data);
}
