<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

// Target User
$user = User::first();
if (!$user) {
    echo "❌ BENCHMARK ERROR: No user found in database!\n";
    exit(1);
}
Auth::login($user);

$scenarios = [
    [
        'name' => 'Scenario 1 (No Tools / Direct Narrative)',
        'prompt' => 'Apa peran utama Badan Pusat Statistik (BPS) dalam pemerintahan Indonesia?',
        'min_expected_steps' => 0,
        'max_expected_steps' => 0,
    ],
    [
        'name' => 'Scenario 2 (Single Tool - search_statistics)',
        'prompt' => 'Tolong cari statistik atau data mengenai "pembangunan keluarga" di BPS.',
        'min_expected_steps' => 1,
        'max_expected_steps' => 2,
    ],
    [
        'name' => 'Scenario 3 (Complex Multi-step - 5 tools called)',
        'prompt' => 'Cari tren indikator IPKP Sleman (3404) tahun 2023-2025, cari tren indikator EPSS Sleman (3404) tahun 2023-2025, cari tren indikator IKP Sleman (3404) tahun 2023-2025, bandingkan Sleman (3404) dengan Mempawah (6104), Sambas (6101), dan Pontianak (6103) untuk metrik IPKP, dan terakhir ambil laporan regional lengkap Sleman tahun 2025.',
        'min_expected_steps' => 5,
        'max_expected_steps' => 7,
    ],
];

echo "============================================================\n";
echo "📊 STARTING MULTI-TOOL & MULTI-STEP STABILITY BENCHMARK\n";
echo "============================================================\n\n";

$results = [];
$cleanupChats = [];

foreach ($scenarios as $idx => $scenario) {
    $num = $idx + 1;
    echo "🏃 Running {$scenario['name']}...\n";
    echo "💬 User Prompt: \"{$scenario['prompt']}\"\n";

    // Create Chat
    $chat = Chat::create([
        'user_id' => $user->id,
        'title' => "Benchmark {$num}: " . substr($scenario['prompt'], 0, 30),
    ]);
    $cleanupChats[] = $chat->id;

    // Create User Message
    $userMsg = Message::create([
        'chat_id' => $chat->id,
        'role' => 'user',
        'content' => $scenario['prompt'],
    ]);

    // Dispatch Query
    $startTime = microtime(true);
    \App\Jobs\ProcessAiAgentQuery::dispatch($chat->id, $userMsg->id);

    echo "⏳ Waiting for Queue Worker to process the job...";
    
    // Poll Database for Assistant Response (up to 120 seconds)
    $assistantMsg = null;
    $maxPoll = 120;
    $polled = 0;
    
    while ($polled < $maxPoll) {
        sleep(1);
        echo ".";
        
        $assistantMsg = Message::where('chat_id', $chat->id)
            ->where('role', 'assistant')
            ->first();
            
        if ($assistantMsg) {
            break;
        }
        $polled++;
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    echo "\n";

    if (!$assistantMsg) {
        echo "❌ TIMEOUT: Job failed to process within {$maxPoll} seconds!\n\n";
        $results[] = [
            'scenario' => $scenario['name'],
            'status' => 'TIMEOUT',
            'duration' => $duration,
            'steps_count' => 0,
            'content_length' => 0,
            'steps' => [],
            'error' => 'Pekerjaan antrean macet atau pekerja mati',
        ];
        continue;
    }

    $steps = $assistantMsg->agent_steps ?? [];
    $stepsCount = count($steps);
    $contentLen = strlen($assistantMsg->content ?? '');

    $status = 'SUCCESS';
    $error = null;

    // Verify Steps
    if ($stepsCount < $scenario['min_expected_steps']) {
        $status = 'FAILED';
        $error = "Terlalu sedikit langkah: Diharapkan >= {$scenario['min_expected_steps']} tapi hanya {$stepsCount}";
    }

    // Verify Content
    if ($contentLen === 0) {
        $status = 'FAILED';
        $error = "Konten respons kosong (0 karakter)! Agen terhenti/abruptly cut off saat memproses langkah";
    }

    if ($status === 'SUCCESS') {
        echo "✅ SUCCESS in {$duration}s | Steps: {$stepsCount} | Length: {$contentLen} chars\n";
        if ($stepsCount > 0) {
            echo "   🛠️ Tools called: " . implode(' -> ', array_column($steps, 'tool')) . "\n";
        }
    } else {
        echo "❌ FAILED in {$duration}s | Steps: {$stepsCount} | Length: {$contentLen} chars\n";
        echo "   ⚠️ Issue: {$error}\n";
    }
    echo "\n";

    $results[] = [
        'scenario' => $scenario['name'],
        'status' => $status,
        'duration' => $duration,
        'steps_count' => $stepsCount,
        'content_length' => $contentLen,
        'steps' => array_column($steps, 'tool'),
        'error' => $error,
    ];
}

// 🧹 Database Cleanup (To keep it completely clean after run)
echo "🧹 Cleaning up benchmark test chats and messages...\n";
foreach ($cleanupChats as $chatId) {
    Message::where('chat_id', $chatId)->delete();
    Chat::where('id', $chatId)->delete();
}
echo "✨ DB Cleanup complete.\n\n";

// ============================================================
// 📊 SUMMARY REPORT
// ============================================================
echo "============================================================\n";
echo "📊 BENCHMARK SUMMARY REPORT\n";
echo "============================================================\n";
$allSuccess = true;
foreach ($results as $idx => $r) {
    $num = $idx + 1;
    $badge = $r['status'] === 'SUCCESS' ? '✅ SUCCESS' : '❌ FAILED';
    if ($r['status'] !== 'SUCCESS') {
        $allSuccess = false;
    }
    echo "{$num}. [{$badge}] {$r['scenario']}\n";
    echo "   ⏱️ Duration: {$r['duration']} seconds\n";
    echo "   📈 Steps Count: {$r['steps_count']} step(s)\n";
    echo "   📝 Content Length: {$r['content_length']} chars\n";
    if (count($r['steps']) > 0) {
        echo "   🛠️ Path: " . implode(' -> ', $r['steps']) . "\n";
    }
    if ($r['error']) {
        echo "   ⚠️ Error Details: {$r['error']}\n";
    }
    echo "\n";
}

echo "============================================================\n";
if ($allSuccess) {
    echo "🎉 CONGRATULATIONS! ALL STABILITY SCENARIOS PASSED 100%!\n";
    exit(0);
} else {
    echo "⚠️ STABILITY ISSUES DETECTED. PLEASE RESOLVE CAUSES AND RUN AGAIN.\n";
    exit(1);
}
