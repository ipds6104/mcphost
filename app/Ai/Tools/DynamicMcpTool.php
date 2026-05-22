<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Events\AgentStepCompleted;
use App\Events\AgentStepStarted;
use App\Services\McpRemoteClientService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DynamicMcpTool implements Tool
{
    public function __construct(
        protected string $name,
        protected string $description,
        protected array $schemaData,
        protected McpRemoteClientService $mcpClient,
        protected string $chatId,
        protected object $state,
        protected ?string $sessionId = null
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): Stringable|string
    {
        return $this->description;
    }

    public function handle(Request $request): Stringable|string
    {
        $this->state->stepIndex++;
        $currentStep = $this->state->stepIndex;

        // Broadcast step started
        event(new AgentStepStarted($this->chatId, $this->name, $currentStep));

        // Panggil remote MCP tool
        try {
            $result = $this->mcpClient->callTool($this->name, $request->all(), $this->sessionId, 'data');
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage()];
        }

        $this->state->steps[] = [
            'step' => $currentStep,
            'tool' => $this->name,
            'arguments' => $request->all(),
            'result' => $result,
        ];

        // Truncate payload for WebSocket broadcasting to prevent "Payload too large" errors (limit to 10KB range)
        $truncatedResult = $this->truncatePayload($result);

        // Broadcast step completed with safety try-catch to prevent WebSocket failure from crashing the AI flow
        try {
            event(new AgentStepCompleted($this->chatId, $this->name, $currentStep, $truncatedResult));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to broadcast AgentStepCompleted: ' . $e->getMessage());
        }

        return json_encode($result);
    }

    /**
     * Recursively truncate data payloads for WebSocket safety.
     */
    protected function truncatePayload(mixed $data, int $maxStringLength = 1000, int $maxArraySize = 10): mixed
    {
        if (is_array($data)) {
            $isAssoc = array_keys($data) !== range(0, count($data) - 1);
            if (! $isAssoc) {
                // List / Sequential array
                if (count($data) > $maxArraySize) {
                    $sliced = array_slice($data, 0, $maxArraySize);
                    $sliced[] = '... [Truncated remaining ' . (count($data) - $maxArraySize) . ' items]';
                    $data = $sliced;
                }
            } else {
                // Associative array
                if (count($data) > 50) {
                    $sliced = array_slice($data, 0, 50, true);
                    $sliced['__truncated_keys_count__'] = count($data) - 50;
                    $data = $sliced;
                }
            }
            foreach ($data as $key => $value) {
                $data[$key] = $this->truncatePayload($value, $maxStringLength, $maxArraySize);
            }

            return $data;
        }

        if (is_string($data)) {
            if (strlen($data) > $maxStringLength) {
                return substr($data, 0, $maxStringLength) . '... [Truncated, total ' . strlen($data) . ' chars]';
            }

            return $data;
        }

        return $data;
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->schemaData;
    }
}
