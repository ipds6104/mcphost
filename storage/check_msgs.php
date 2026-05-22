<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$msgs = \App\Models\Message::where('chat_id', '019e5082-3aff-7082-b74e-7eb458f013f3')
    ->orderBy('id')->get(['id', 'role', 'content', 'created_at']);

foreach ($msgs as $m) {
    echo "[{$m->id}] {$m->role}: " . substr($m->content, 0, 100) . PHP_EOL;
}
echo "\nTotal: " . $msgs->count() . " messages\n";
