<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = microtime(true);
$client = new App\Services\McpSseClient();
try {
  $tools = $client->listTools('http://host.docker.internal:3001/sse', 'your_secure_access_token');
  echo 'Success: ' . count($tools) . " tools found.\n";
} catch (\Exception $e) {
  echo 'Error: ' . $e->getMessage() . "\n";
}
echo 'Duration: ' . (microtime(true) - $start) . "s\n";
