<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\MockMcpToolProvider;
use App\Ai\Tools\MockMcpTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;

echo "Starting debug of MockMcpTool schema...\n";

try {
    $mockProvider = new MockMcpToolProvider();
    $mockToolDefs = $mockProvider->getTools();
    $state = (object) ['stepIndex' => 0, 'steps' => []];

    foreach ($mockToolDefs as $toolDef) {
        $toolName = $toolDef['name'];
        echo "Processing tool: {$toolName}...\n";

        $tool = new MockMcpTool(
            toolName: $toolName,
            toolDescription: $toolDef['description'],
            provider: $mockProvider,
            chatId: 'test-chat-id',
            state: $state
        );

        $schemaFactory = new JsonSchemaTypeFactory();
        $schema = $tool->schema($schemaFactory);

        echo "  Schema keys: " . implode(', ', array_keys($schema)) . "\n";
        foreach ($schema as $key => $val) {
            echo "    Key: {$key}, Type: " . (is_object($val) ? get_class($val) : gettype($val)) . "\n";
        }

        // Try mapping it as ObjectSchema
        $objectSchema = new ObjectSchema($schema);
        $res = $objectSchema->toSchema();
        echo "  SUCCESS: Mapped successfully!\n";
    }

    echo "ALL SCHEMAS ARE VALID!\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stacktrace:\n" . $e->getTraceAsString() . "\n";
}
