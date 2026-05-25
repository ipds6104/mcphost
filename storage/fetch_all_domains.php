<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = config('services.bps.key');
if (empty($apiKey)) {
    echo "API Key is empty!\n";
    exit(1);
}

echo "Fetching all domains from BPS WebAPI with headers...\n";
try {
    $url = "https://webapi.bps.go.id/v1/api/domain/type/all/key/{$apiKey}/";
    
    $response = Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Accept' => 'application/json, text/plain, */*',
        'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'Sec-Fetch-Dest' => 'empty',
        'Sec-Fetch-Mode' => 'cors',
        'Sec-Fetch-Site' => 'same-site',
        'Sec-Ch-Ua' => '"Chromium";v="125", "Google Chrome";v="125", "Not-A.Brand";v="99"',
        'Sec-Ch-Ua-Mobile' => '?0',
        'Sec-Ch-Ua-Platform' => '"Windows"',
        'Referer' => 'https://webapi.bps.go.id/',
        'Origin' => 'https://webapi.bps.go.id',
    ])
    ->timeout(20)
    ->get($url);

    if ($response->failed()) {
        echo "HTTP Error: " . $response->status() . "\n";
        exit(1);
    }

    $json = $response->json();
    if (!isset($json['data'][1])) {
        echo "No data array in response!\n";
        print_r($json);
        exit(1);
    }

    $domains = $json['data'][1];
    echo "Total domains found: " . count($domains) . "\n";
    echo "First 15 domains:\n";
    for ($i = 0; $i < min(15, count($domains)); $i++) {
        print_r($domains[$i]);
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
