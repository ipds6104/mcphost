<?php

declare(strict_types=1);

// Automated Package Provider Profiler via Exclusion
$packagesCacheFile = __DIR__ . '/bootstrap/cache/packages.php';
if (! file_exists($packagesCacheFile)) {
    echo "❌ packages.php cache file not found! Run php artisan package:discover first.\n";
    exit(1);
}

$originalPackages = include $packagesCacheFile;
$backupFile = $packagesCacheFile . '.bak';

// Make a backup
copy($packagesCacheFile, $backupFile);

echo "========================================================\n";
echo "    MCPHost Gateway - Automated Provider Profiler\n";
echo "========================================================\n\n";

// Function to measure total boot time of Laravel
function measureBootTime()
{
    $t0 = microtime(true);
    // Run php artisan inspire via exec to measure clean CLI boot time
    exec('php artisan inspire', $output, $retval);

    return (microtime(true) - $t0) * 1000;
}

// 1. Measure baseline boot time (all providers active)
echo "Measuring baseline boot time (all providers active)...\n";
$baselines = [];
for ($i = 0; $i < 3; $i++) {
    $baselines[] = measureBootTime();
}
$baseline = array_sum($baselines) / count($baselines);
echo sprintf("Baseline Boot Time: %.2f ms\n\n", $baseline);

echo "Profiling packages by exclusion (slowest package will show largest time drop):\n";
echo "--------------------------------------------------------\n";

$results = [];

foreach ($originalPackages as $packageName => $packageData) {
    echo "Excluding package: $packageName ... ";

    // Create an array without this package
    $tempPackages = $originalPackages;
    unset($tempPackages[$packageName]);

    // Write temporary packages.php
    $content = '<?php return ' . var_export($tempPackages, true) . ';';
    file_put_contents($packagesCacheFile, $content);

    // Clear OPcache if active so it picks up the changed file
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($packagesCacheFile, true);
    }

    // Measure boot times
    $times = [];
    for ($i = 0; $i < 3; $i++) {
        $times[] = measureBootTime();
    }
    $avgTime = array_sum($times) / count($times);
    $difference = $baseline - $avgTime;

    $results[$packageName] = [
        'boot_time' => $avgTime,
        'difference' => $difference,
    ];

    echo sprintf("%.2f ms (Diff: %+.2f ms)\n", $avgTime, -$difference);
}

// Restore original packages.php
copy($backupFile, $packagesCacheFile);
unlink($backupFile);
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($packagesCacheFile, true);
}

echo "--------------------------------------------------------\n";
echo "\n=== PROFILING RESULTS (Sorted by impact) ===\n";
uasort($results, function ($a, $b) {
    return $a['boot_time'] <=> $b['boot_time']; // Slowest packages when excluded will have smallest boot_time, meaning their exclusion caused the biggest speedup!
});

foreach ($results as $package => $data) {
    $diff = -$data['difference'];
    $flag = $diff < -200 ? ' ⚠️  CRITICAL BOTTLENECK' : ($diff < -50 ? ' 🔶 MODERATE' : '');
    echo sprintf("  %-30s : %8.2f ms (Diff: %+.2f ms)%s\n", $package, $data['boot_time'], $diff, $flag);
}
echo "========================================================\n";
