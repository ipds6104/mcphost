<?php

declare(strict_types=1);

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\McpSseClient;
use Illuminate\Support\Facades\Log;

echo "========================================================================\n";
echo "🤖 TEST MCP SSE CLIENT - STANDALONE VALIDATION\n";
echo "========================================================================\n\n";

$client = new McpSseClient();
$mcpServer = App\Models\McpServer::where('name', 'BPS Agentic Docker')->first();
if (!$mcpServer) {
    throw new \Exception("Server MCP 'BPS Agentic Docker' tidak ditemukan di database. Pastikan Anda sudah menjalankan seeder!");
}
$sseUrl = $mcpServer->url;
$token = $mcpServer->token;

try {
    echo "1. Handshaking and Listing Tools from: {$sseUrl}...\n";
    $tools = $client->listTools($sseUrl, $token);
    
    echo "🟢 SUCCESS! List of tools received:\n";
    foreach ($tools as $tool) {
        echo "  - Name: " . $tool['name'] . "\n";
        echo "    Description: " . ($tool['description'] ?? 'No description') . "\n";
        echo "    Params: " . json_encode($tool['inputSchema']['properties'] ?? []) . "\n\n";
    }

    echo "------------------------------------------------------------------------\n";
    echo "2. Calling dynamic tool 'bps_query' for Mempawah statistics...\n";
    
    $result = $client->callTool($sseUrl, 'bps_query', [
        'topic' => 'Kemiskinan',
        'region' => 'Mempawah',
        'year' => '2023'
    ], $token);

    echo "🟢 SUCCESS! Tool execution result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

} catch (\Exception $e) {
    echo "❌ FAILED with Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n========================================================================\n";
