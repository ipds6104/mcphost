<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

class GovtAnalyticsAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Create a new agent instance.
     */
    public function __construct(
        protected string $instructions = 'You are a helpful government statistic assistant.',
        protected array $tools = [],
        protected array $messages = []
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return $this->tools;
    }
}
