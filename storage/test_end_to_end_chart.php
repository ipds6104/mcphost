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
    'title' => 'Test Sleman E2E Chart Repair',
]);

$prompt = implode("\n", [
    "Tolong berikan analisis data regional report Sleman tahun 2023.",
    "Wajib sertakan satu blok visualisasi data di akhir jawaban Anda menggunakan format tag berikut:",
    "```json-chart",
    "{",
    "  'type': 'bar',",
    "  'data': {",
    "    'labels': ['IPKP', 'EPSS', 'IKP',],",
    "    'datasets': [",
    "      { 'label': 'Sleman', 'data': [79.2, 83.0, 77.5], }",
    "    ]",
    "  }",
    "}",
    "```",
    "Sengaja gunakan format JSON di atas persis seperti itu (dengan single quotes, trailing commas, dll.) untuk menguji sistem auto-repair kami."
]);

$userMessage = Message::create([
    'chat_id' => $chat->id,
    'role' => 'user',
    'content' => $prompt,
]);

echo "Dispatching job...\n";
\App\Jobs\ProcessAiAgentQuery::dispatch($chat->id, $userMessage->id);

echo "Waiting for queue worker to process (15 seconds)...\n";
sleep(15);

$messages = Message::where('chat_id', $chat->id)->orderBy('id')->get();
foreach ($messages as $m) {
    echo "========================================\n";
    echo "ROLE: " . strtoupper($m->role) . "\n";
    if ($m->role === 'assistant') {
        echo "CONTENT LENGTH: " . strlen($m->content) . "\n";
        echo "CHART DATA:\n";
        if ($m->chart_data) {
            echo json_encode($m->chart_data, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "NONE\n";
        }
    } else {
        echo "CONTENT:\n" . $m->content . "\n";
    }
}
echo "========================================\n";
