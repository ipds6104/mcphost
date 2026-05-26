<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Events\AgentStepCompleted;
use App\Events\AgentStepStarted;
use App\Services\McpSseClient;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * McpSseTool
 *
 * Implementasi Tool Laravel AI SDK dinamis untuk server MCP berbasis SSE.
 * Menghubungkan setiap perkakas dari Server MCP Universal eksternal ke agen chatbot AI.
 */
class McpSseTool implements Tool
{
    public function __construct(
        protected string $toolName,
        protected string $toolDescription,
        protected array $rawSchema,
        protected string $serverUrl,
        protected ?string $serverToken,
        protected McpSseClient $sseClient,
        protected string $chatId,
        protected object $state,
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): Stringable|string
    {
        return $this->toolDescription;
    }

    public function handle(Request $request): Stringable|string
    {
        $this->state->stepIndex++;
        $currentStep = $this->state->stepIndex;
        $args = $request->all();

        // 1. Kirim event bahwa langkah agen dimulai
        event(new AgentStepStarted($this->chatId, $this->toolName, $currentStep));

        // 2. Hubungi Server MCP eksternal melalui SSE Client
        try {
            Log::info("Memanggil Server MCP Eksternal: '{$this->toolName}' pada {$this->serverUrl}");
            Log::info('Argumen panggilan: ' . json_encode($args, JSON_UNESCAPED_SLASHES));

            $callResponse = $this->sseClient->callTool(
                $this->serverUrl,
                $this->toolName,
                $args,
                $this->serverToken
            );

            // Tambahkan metadata penelusuran jika berhasil
            $result = array_merge($callResponse, [
                '_meta' => [
                    'source' => "Universal MCP Server ({$this->serverUrl})",
                    'status' => 'SUCCESS',
                    'fetched_at' => now()->toIso8601String(),
                ],
            ]);

            Log::info('Hasil panggilan Server MCP sukses.');
        } catch (\Exception $e) {
            Log::error("Gagal memanggil Server MCP Eksternal '{$this->toolName}': " . $e->getMessage());

            $result = [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Kesalahan memanggil server MCP: ' . $e->getMessage(),
                    ],
                ],
                'isError' => true,
                '_meta' => [
                    'source' => "Universal MCP Server ({$this->serverUrl})",
                    'status' => 'ERROR',
                    'fetched_at' => now()->toIso8601String(),
                ],
            ];
        }

        // 3. Rekam jejak langkah ke state agen
        $this->state->steps[] = [
            'step' => $currentStep,
            'tool' => $this->toolName,
            'arguments' => $args,
            'result' => $result,
        ];

        // 4. Kirim event bahwa langkah agen selesai
        event(new AgentStepCompleted($this->chatId, $this->toolName, $currentStep, $result));

        return json_encode($result);
    }

    /**
     * Konversi skema properti JSON Schema standar dari Server MCP
     * menjadi representasi builder JsonSchema SDK Laravel AI.
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = $this->rawSchema['properties'] ?? [];
        $requiredFields = $this->rawSchema['required'] ?? [];

        $mapped = [];

        foreach ($properties as $name => $prop) {
            $builder = $this->parseType($schema, $prop);

            if (in_array($name, $requiredFields, true)) {
                $builder = $builder->required();
            }

            $mapped[$name] = $builder;
        }

        return $mapped;
    }

    protected function parseType(JsonSchema $schema, array $prop)
    {
        $type = $prop['type'] ?? 'string';
        $description = $prop['description'] ?? '';

        $builder = match ($type) {
            'integer' => $schema->integer(),
            'number' => $schema->number(),
            'boolean' => $schema->boolean(),
            'array' => $schema->array(),
            'object' => $schema->object(),
            default => $schema->string(),
        };

        if ($type === 'array' && isset($prop['items'])) {
            if (method_exists($builder, 'items')) {
                $builder->items($this->parseType($schema, $prop['items']));
            }
        }

        if ($description && method_exists($builder, 'description')) {
            $builder->description($description);
        }
        
        if (isset($prop['enum']) && method_exists($builder, 'enum')) {
            $builder->enum($prop['enum']);
        }

        return $builder;
    }
}
