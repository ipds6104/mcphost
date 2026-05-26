<?php

declare(strict_types=1);

/**
 * BPS AI Agent Agnostic Feedback Loop & Premium Dashboard
 * Script ini menguji asisten AI di beberapa wilayah acak se-Indonesia secara berurutan,
 * mengukur latensi riil, wasted calls, efisiensi, dan akurasi statistik otonom,
 * kemudian merender dasbor performa premium di terminal.
 */

// ANSI Color helper
function color(string $text, string $colorCode): string {
    return "\033[{$colorCode}m{$text}\033[0m";
}

echo "\n";
echo color("========================================================================\n", "1;36");
echo color("  🌐 BPS AI AGENT - MULTI-REGION PERFORMANCE DASHBOARD (FEEDBACK LOOP)  \n", "1;37;46");
echo color("========================================================================\n", "1;36");
echo "\n";

$iterations = 3;
$results = [];
$logPath = __DIR__ . '/../storage/logs/bps_agent_tests.json';

// Backup existing log file size or content to know what was added
$initialCount = 0;
if (file_exists($logPath)) {
    $existing = json_decode(file_get_contents($logPath), true);
    if (is_array($existing)) {
        $initialCount = count($existing);
    }
}

for ($i = 1; $i <= $iterations; $i++) {
    echo color("🔄 [Iterasi {$i}/{$iterations}]", "1;33") . " Memilih wilayah acak dan menjalankan pengujian otonom...\n";
    
    // Jalankan command TestBpsAgent dengan region=random di Docker
    $startTime = microtime(true);
    $cmd = "docker compose exec -T web php artisan bps:test-agent --region=random";
    
    // Eksekusi dan tangkap outputnya
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    
    $elapsed = microtime(true) - $startTime;
    
    if ($exitCode !== 0) {
        echo color("❌ Iterasi {$i} Gagal dengan exit code {$exitCode}!\n", "1;31");
        continue;
    }
    
    // Baca entry baru dari json
    if (file_exists($logPath)) {
        $logs = json_decode(file_get_contents($logPath), true);
        if (is_array($logs) && count($logs) > $initialCount) {
            $latestLog = end($logs);
            $results[] = [
                'region' => $latestLog['region'] ?? 'unknown',
                'duration' => $latestLog['duration_seconds'] ?? $elapsed,
                'total_steps' => $latestLog['total_steps'] ?? 0,
                'useful_calls' => $latestLog['useful_calls'] ?? 0,
                'wasted_calls' => $latestLog['wasted_calls'] ?? 0,
                'efficiency' => $latestLog['efficiency_score'] ?? 0.0,
                'accuracy' => $latestLog['accuracy_score'] ?? 0.0,
                'total_claims' => $latestLog['total_claims'] ?? 0,
                'matched_claims' => $latestLog['matched_claims'] ?? 0,
            ];
            
            $regDisplay = strtoupper($latestLog['region'] ?? 'unknown');
            echo color("✅ Sukses!", "1;32") . " Wilayah: " . color($regDisplay, "1;37") . 
                 " | Durasi: " . color(number_format($elapsed, 2) . "s", "1;35") . 
                 " | Akurasi: " . color((string)$latestLog['accuracy_score'] . "%", "1;32") . "\n\n";
        } else {
            echo color("⚠️ Iterasi selesai, tetapi tidak ada entri baru yang tercatat di bps_agent_tests.json.\n\n", "1;33");
        }
    } else {
        echo color("❌ Berkas bps_agent_tests.json tidak ditemukan!\n\n", "1;31");
    }
    
    // Update initialCount agar tidak membaca entry yang sama
    if (file_exists($logPath)) {
        $existing = json_decode(file_get_contents($logPath), true);
        if (is_array($existing)) {
            $initialCount = count($existing);
        }
    }
}

if (empty($results)) {
    echo color("❌ Seluruh iterasi pengujian gagal. Dasbor tidak dapat ditampilkan.\n", "1;31");
    exit(1);
}

// ──────────────────────────────────────────────────────────────────────────
// TAMPILKAN DASBOR AGREGAT PREMIUM
// ──────────────────────────────────────────────────────────────────────────

$totalDuration = 0;
$totalAccuracy = 0;
$totalEfficiency = 0;
$totalWasted = 0;
$totalSteps = 0;

foreach ($results as $res) {
    $totalDuration += $res['duration'];
    $totalAccuracy += $res['accuracy'];
    $totalEfficiency += $res['efficiency'];
    $totalWasted += $res['wasted_calls'];
    $totalSteps += $res['total_steps'];
}

$count = count($results);
$avgDuration = $totalDuration / $count;
$avgAccuracy = $totalAccuracy / $count;
$avgEfficiency = $totalEfficiency / $count;

$statusText = "STABLE";
$statusColor = "1;32"; // Green
if ($avgAccuracy < 80 || $avgEfficiency < 75 || $avgDuration > 45) {
    $statusText = "WARNING";
    $statusColor = "1;33"; // Yellow
}
if ($avgAccuracy < 50 || $avgDuration > 60) {
    $statusText = "DEGRADED";
    $statusColor = "1;31"; // Red
}

echo "\n";
echo color("┌──────────────────────────────────────────────────────────────────────┐\n", "1;36");
echo color("│                ✨ AGENT MULTI-REGION PERFORMANCE SUMMARY ✨            │\n", "1;36");
echo color("├──────────────────────────────────────────────────────────────────────┤\n", "1;36");
echo sprintf("│ " . color("Status Sistem", "1;37") . "      : %-37s │\n", color($statusText, $statusColor));
echo sprintf("│ " . color("Rata-rata Latensi", "1;37") . "  : %-37s │\n", color(number_format($avgDuration, 2) . " detik", $avgDuration < 35 ? "1;32" : "1;33"));
echo sprintf("│ " . color("Rata-rata Akurasi", "1;37") . "  : %-37s │\n", color(number_format($avgAccuracy, 2) . "%", $avgAccuracy > 80 ? "1;32" : "1;33"));
echo sprintf("│ " . color("Efisiensi Perkakas", "1;37") . " : %-37s │\n", color(number_format($avgEfficiency, 2) . "%", $avgEfficiency > 80 ? "1;32" : "1;33"));
echo sprintf("│ " . color("Total Wasted Calls", "1;37") . " : %-37s │\n", color((string)$totalWasted, $totalWasted === 0 ? "1;32" : "1;33"));
echo color("└──────────────────────────────────────────────────────────────────────┘\n", "1;36");
echo "\n";

// Render Table
echo color("📋 PERBANDINGAN METRIK PER WILAYAH:\n", "1;34");
echo color("┌──────────────────────┬─────────────┬─────────────┬─────────────┬─────────────┐\n", "1;37");
echo color("│ WILAYAH TARGET       │ LATENSI (S) │ ACCURACY    │ EFFICIENCY  │ WASTED / TOT│\n", "1;37");
echo color("├──────────────────────┼─────────────┼─────────────┼─────────────┼─────────────┤\n", "1;37");

foreach ($results as $res) {
    $regionName = strtoupper(substr($res['region'], 0, 20));
    $latencyStr = number_format($res['duration'], 1) . "s";
    $accuracyStr = number_format($res['accuracy'], 1) . "%";
    $efficiencyStr = number_format($res['efficiency'], 1) . "%";
    $callsStr = $res['wasted_calls'] . " / " . $res['total_steps'];
    
    echo sprintf(
        "│ %-20s │ %-11s │ %-11s │ %-11s │ %-11s │\n",
        color($regionName, "1;37"),
        color($latencyStr, $res['duration'] < 35 ? "32" : "33"),
        color($accuracyStr, $res['accuracy'] > 85 ? "32" : "33"),
        color($efficiencyStr, $res['efficiency'] > 80 ? "32" : "33"),
        color($callsStr, $res['wasted_calls'] === 0 ? "32" : "31")
    );
}
echo color("└──────────────────────┴─────────────┴─────────────┴─────────────┴─────────────┘\n", "1;37");
echo "\n";
