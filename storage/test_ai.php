<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Laravel\Ai\Ai;
use App\Ai\Agents\GovtAnalyticsAgent;
use Illuminate\Support\Facades\Http;

// Configure the deepseek driver in config on-the-fly for testing
config([
    'ai.default' => 'deepseek',
    'ai.default_model' => 'gemini-3-flash',
    'ai.providers.deepseek' => [
        'driver' => 'deepseek',
        'key' => 'sk-af6376fcf20b4a148672456a6cae1902',
        'url' => 'https://ai.dvlpid.my.id/v1',
    ]
]);

Http::globalRequestMiddleware(function ($request) {
    echo ">>> HTTP Request: " . $request->getMethod() . " " . $request->getUri()->__toString() . "\n";
    echo "Headers:\n";
    print_r($request->getHeaders());
    echo "Body:\n";
    echo $request->getBody()->__toString() . "\n\n";
    return $request;
});

Http::globalResponseMiddleware(function ($response) {
    echo "<<< HTTP Response: " . $response->getStatusCode() . "\n";
    echo "Headers:\n";
    print_r($response->getHeaders());
    echo "Body:\n";
    echo $response->getBody()->__toString() . "\n\n";
    return $response;
});

$providerName = config('ai.default');
$modelName = config('ai.default_model');

$mockProvider = new \App\Services\MockMcpToolProvider();
$state = (object) ['stepIndex' => 0, 'steps' => []];

$mockToolDefs = $mockProvider->getTools();
$tools = array_map(fn (array $toolDef) => new \App\Ai\Tools\MockMcpTool(
    toolName: $toolDef['name'],
    toolDescription: $toolDef['description'],
    provider: $mockProvider,
    chatId: 'test_chat',
    state: $state,
), $mockToolDefs);

$agent = new GovtAnalyticsAgent(
    instructions: "Anda adalah Asisten Analitis Statistik Pemerintah Indonesia. Selalu gunakan Bahasa Indonesia yang baik dan profesional.",
    tools: $tools,
    messages: []
);

try {
    echo "Sending tool prompt via DeepSeek driver...\n";
    $response = $agent->prompt(
        prompt: "Tolong cari data regional report untuk Kabupaten Sleman tahun 2023.",
        provider: $providerName,
        model: $modelName
    );

    echo "Response Text:\n";
    var_dump($response->text);
    echo "\nResponse Steps:\n";
    print_r($state->steps);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
