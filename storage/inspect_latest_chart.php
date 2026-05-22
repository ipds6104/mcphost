<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Message;

$msg = Message::where('role', 'assistant')->orderBy('id', 'desc')->first();

if (!$msg) {
    echo "No assistant message found.\n";
    exit(1);
}

echo "=== Message Info ===\n";
echo "ID: {$msg->id}\n";
echo "Chat ID: {$msg->chat_id}\n";
echo "Role: {$msg->role}\n";
echo "Content Length: " . strlen($msg->content) . "\n";
echo "\n=== Message Content ===\n";
echo $msg->content . "\n";
echo "\n=== Agent Steps ===\n";
if ($msg->agent_steps) {
    echo json_encode($msg->agent_steps, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No agent steps recorded.\n";
}

echo "\n=== Chart Data ===\n";
if ($msg->chart_data) {
    echo json_encode($msg->chart_data, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No chart data recorded.\n";
}
