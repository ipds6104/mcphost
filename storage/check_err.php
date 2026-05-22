<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$failedJobs = DB::table('failed_jobs')->orderBy('id', 'desc')->take(5)->get();
if ($failedJobs->isNotEmpty()) {
    foreach ($failedJobs as $failed) {
        echo "========================================\n";
        echo "ID: {$failed->id}\n";
        echo "UUID: {$failed->uuid}\n";
        echo "CONNECTION: {$failed->connection}\n";
        echo "QUEUE: {$failed->queue}\n";
        echo "FAILED AT: {$failed->failed_at}\n";
        echo "EXCEPTION:\n" . $failed->exception . "\n";
        echo "========================================\n";
    }
} else {
    echo "No failed jobs found.\n";
}
