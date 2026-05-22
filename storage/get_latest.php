<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Message;

$msgs = Message::orderBy('id', 'desc')->take(5)->get();

foreach ($msgs as $m) {
    echo "========================================\n";
    echo "ID: {$m->id} | ROLE: " . strtoupper($m->role) . " | CHAT_ID: {$m->chat_id}\n";
    echo "CONTENT:\n" . $m->content . "\n";
}
echo "========================================\n";
