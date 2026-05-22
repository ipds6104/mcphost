<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MockMcpToolProvider;
use App\Ai\Tools\MockMcpTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;

$provider = new MockMcpToolProvider();
$state    = (object) ['stepIndex' => 0, 'steps' => []];
$factory  = new JsonSchemaTypeFactory();

echo "Testing MockMcpTool schema() with JsonSchemaTypeFactory...\n\n";

foreach ($provider->getTools() as $def) {
    echo "Tool: {$def['name']}\n";
    $tool = new MockMcpTool($def['name'], $def['description'], $provider, 'test', $state);

    try {
        $schema      = $tool->schema($factory);
        $schemaArray = filled($schema) ? (new ObjectSchema($schema))->toSchema() : [];
        echo "  ✅ schema() OK — properties: " . implode(', ', array_keys($schemaArray['properties'] ?? [])) . "\n";
    } catch (\Throwable $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n";
        echo "     at " . $e->getFile() . ':' . $e->getLine() . "\n";
    }
}
echo "\nDone.\n";
