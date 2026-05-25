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

echo "Creating new chat for Mempawah IPM analysis...\n";
$chat = Chat::create([
    'user_id' => $user->id,
    'title' => 'Tren IPM Mempawah 2010-2024',
]);

$userMessage = Message::create([
    'chat_id' => $chat->id,
    'role' => 'user',
    'content' => 'Tampilkan tren IPM Kabupaten Mempawah dari tahun 2010 sampai 2024 dan analisis perkembangannya',
]);

echo "Dispatching ProcessAiAgentQuery job synchronously...\n";
try {
    \App\Jobs\ProcessAiAgentQuery::dispatchSync($chat->id, $userMessage->id);
    echo "Job completed successfully!\n";
} catch (\Exception $e) {
    echo "Error executing job: " . $e->getMessage() . "\n";
    exit(1);
}

// Fetch the assistant's reply
$messages = Message::where('chat_id', $chat->id)->orderBy('id')->get();
foreach ($messages as $m) {
    echo "========================================\n";
    echo "ROLE: " . strtoupper($m->role) . "\n";
    echo "CONTENT PREVIEW:\n" . substr($m->content, 0, 1500) . "...\n";
    if ($m->agent_steps) {
        echo "STEPS EXECUTED: " . count($m->agent_steps) . "\n";
        foreach ($m->agent_steps as $idx => $step) {
            $tool = $step['tool'] ?? 'N/A';
            $status = isset($step['result']['_meta']['cache_status']) ? $step['result']['_meta']['cache_status'] : 'N/A';
            echo "  Step " . ($idx + 1) . ": Tool '{$tool}' -> status: {$status}\n";
        }
    }
}
echo "========================================\n";
