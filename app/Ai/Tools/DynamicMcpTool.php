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
        protected int $chatId,
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

        // Broadcast step completed
        event(new AgentStepCompleted($this->chatId, $this->name, $currentStep, $result));

        return json_encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->schemaData;
    }
}
