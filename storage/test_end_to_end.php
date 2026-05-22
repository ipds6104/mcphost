<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

// Get the user
$user = User::first();
if (!$user) {
    echo "Error: No user found in database!\n";
    exit(1);
}
Auth::login($user);

echo "Creating new chat...\n";
$chat = Chat::create([
    'user_id' => $user->id,
    'title' => 'Test Sleman E2E',
]);

$userMessage = Message::create([
    'chat_id' => $chat->id,
    'role' => 'user',
    'content' => 'Tolong berikan analisis data regional report Sleman tahun 2023.',
]);

echo "Dispatching job...\n";
\App\Jobs\ProcessAiAgentQuery::dispatch($chat->id, $userMessage->id);

echo "Waiting for queue worker to process (5 seconds)...\n";
sleep(5);

$messages = Message::where('chat_id', $chat->id)->orderBy('id')->get();
foreach ($messages as $m) {
    echo "========================================\n";
    echo "ROLE: " . strtoupper($m->role) . "\n";
    echo "CONTENT:\n" . $m->content . "\n";
    if ($m->agent_steps) {
        echo "STEPS: " . count($m->agent_steps) . " step(s)\n";
    }
}
echo "========================================\n";
