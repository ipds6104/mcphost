<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$start = microtime(true);
$client = new App\Services\McpSseClient();
$mcpServer = App\Models\McpServer::where('name', 'BPS Agentic Docker')->first();
if (!$mcpServer) {
    throw new \Exception("Server MCP 'BPS Agentic Docker' tidak ditemukan di database.");
}
try {
  $tools = $client->listTools($mcpServer->url, $mcpServer->token);
  echo 'Success: ' . count($tools) . " tools found.\n";
} catch (\Exception $e) {
  echo 'Error: ' . $e->getMessage() . "\n";
}
echo 'Duration: ' . (microtime(true) - $start) . "s\n";
