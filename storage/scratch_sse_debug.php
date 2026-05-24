<?php

declare(strict_types=1);

// Disable output buffering
if (ob_get_level() > 0) ob_end_clean();

$sseUrl = 'http://host.docker.internal:3001/sse';
$token = 'your_secure_access_token';

$parsedUrl = parse_url($sseUrl);
$query = ['token' => $token];
$queryString = http_build_query($query);
$finalSseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '') . ($parsedUrl['path'] ?? '/sse') . ($queryString ? '?' . $queryString : '');

echo "Connecting to: $finalSseUrl\n";
$start = microtime(true);

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => [
            'Accept: text/event-stream',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            "Authorization: Bearer $token",
        ],
        'timeout' => 15,
    ],
];
$context = stream_context_create($opts);
$stream = fopen($finalSseUrl, 'r', false, $context);

if (!$stream) {
    die("Failed to open stream\n");
}

echo "Stream opened in " . round((microtime(true) - $start) * 1000, 2) . " ms\n";

$event = '';
$data = '';
$postPath = null;
$sessionId = null;

stream_set_timeout($stream, 5); // Short timeout for debugging

echo "Reading handshake...\n";
while (!feof($stream)) {
    $lineStart = microtime(true);
    $line = fgets($stream);
    $elapsed = round((microtime(true) - $lineStart) * 1000, 2);
    
    if ($line === false) {
        $info = stream_get_meta_data($stream);
        echo "fgets returned FALSE. Elapsed: $elapsed ms. Timed out: " . ($info['timed_out'] ? 'YES' : 'NO') . "\n";
        break;
    }
    
    $trimmed = trim($line);
    echo "Line read in $elapsed ms: '$trimmed'\n";
    
    if ($trimmed === '') {
        if ($event === 'endpoint' && !empty($data)) {
            $postPath = $data;
            echo "Found endpoint: $postPath\n";
            break;
        }
        $event = '';
        $data = '';
        continue;
    }
    
    if (str_starts_with($trimmed, 'event:')) {
        $event = trim(substr($trimmed, 6));
    } elseif (str_starts_with($trimmed, 'data:')) {
        $data = trim(substr($trimmed, 5));
    }
}

if (!$postPath) {
    die("Handshake failed to find endpoint\n");
}

$baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '');
$postUrl = str_starts_with($postPath, 'http') ? $postPath : $baseUrl . '/' . ltrim($postPath, '/');

$postUrlParts = parse_url($postUrl);
$postQuery = [];
if (isset($postUrlParts['query'])) {
    parse_str($postUrlParts['query'], $postQuery);
}
$sessionId = $postQuery['sessionId'] ?? '';

echo "Session ID: $sessionId\n";
echo "POST URL: $postUrl\n";

// Now, let's send a post request in a separate command or curl, or we can use file_get_contents to POST
$payload = json_encode([
    'jsonrpc' => '2.0',
    'method' => 'tools/list',
    'id' => 1
]);

echo "Sending listTools POST request...\n";
$postStart = microtime(true);

$postOpts = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
        ],
        'content' => $payload,
        'ignore_errors' => true,
    ]
];
$postContext = stream_context_create($postOpts);
$postResponse = file_get_contents($postUrl . "&token=" . $token, false, $postContext);

echo "POST response received in " . round((microtime(true) - $postStart) * 1000, 2) . " ms. Response: $postResponse\n";

echo "Reading SSE stream for response...\n";
stream_set_timeout($stream, 10); // set to 10s

while (!feof($stream)) {
    $lineStart = microtime(true);
    $line = fgets($stream);
    $elapsed = round((microtime(true) - $lineStart) * 1000, 2);
    
    if ($line === false) {
        $info = stream_get_meta_data($stream);
        echo "fgets returned FALSE. Elapsed: $elapsed ms. Timed out: " . ($info['timed_out'] ? 'YES' : 'NO') . "\n";
        break;
    }
    
    $trimmed = trim($line);
    echo "Line read in $elapsed ms: '$trimmed'\n";
}

fclose($stream);
echo "Done\n";
