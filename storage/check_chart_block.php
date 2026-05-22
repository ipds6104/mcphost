<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Message;

$msg = Message::orderBy('id', 'desc')->first();

echo "Message ID: {$msg->id}\n";
if (preg_match('/```json-chart(.*?)\s*```/s', $msg->content, $matches)) {
    echo "Found json-chart block!\n";
    echo "Raw Block Content:\n" . $matches[1] . "\n";
} else {
    echo "No json-chart block found in message content.\n";
}
