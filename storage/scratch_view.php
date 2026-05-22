<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$messages = App\Models\Message::orderBy('id', 'desc')->take(10)->get();
foreach ($messages as $msg) {
    echo "ID: {$msg->id}\n";
    echo "Chat ID: {$msg->chat_id}\n";
    echo "Role: {$msg->role}\n";
    echo "Content Length: " . strlen($msg->content ?? '') . "\n";
    echo "Content Preview: " . substr($msg->content ?? '', 0, 100) . "\n";
    echo "Agent Steps: " . ($msg->agent_steps ? count($msg->agent_steps) : 'NULL') . "\n";
    echo "----------------------------------------\n";
}
