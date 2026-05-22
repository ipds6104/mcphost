<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentResponseGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $chatId,
        public int|string $messageId,
        public string $content,
        public ?array $chartData = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chats.{$this->chatId}"),
        ];
    }
}
