<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class McpSseClient
{
    /**
     * Connect to the SSE endpoint, read the first event (which should be "endpoint"),
     * and extract the SSE sessionId and HTTP POST endpoint for sending messages.
     *
     * Returns an array containing ['sessionId' => string, 'postUrl' => string, 'stream' => resource]
     */
    protected function handshake(string $sseUrl, ?string $token = null): array
    {
        $parsedUrl = parse_url($sseUrl);
        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }
        if ($token) {
            $query['token'] = $token;
        }

        $queryString = http_build_query($query);
        $finalSseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '') . ($parsedUrl['path'] ?? '/sse') . ($queryString ? '?' . $queryString : '');

        Log::channel('ai_agent')->info('mcp_client.handshake_start', ['url' => $sseUrl]);

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: text/event-stream',
                    'Cache-Control: no-cache',
                    'Connection: keep-alive',
                ],
                'timeout' => 15,
            ],
        ];

        if ($token) {
            $opts['http']['header'][] = "Authorization: Bearer {$token}";
        }

        $context = stream_context_create($opts);
        $stream = fopen($finalSseUrl, 'r', false, $context);

        if (! $stream) {
            throw new \Exception("Failed to open connection to remote SSE MCP server: {$sseUrl}");
        }

        // Set stream read timeout to prevent hanging during handshake
        stream_set_timeout($stream, 10);

        // We must read from the stream until we get the 'endpoint' event
        $sessionId = null;
        $postPath = null;
        $event = '';
        $data = '';

        $maxAttempts = 100;
        $attempts = 0;

        while (! feof($stream) && $attempts++ < $maxAttempts) {
            $line = fgets($stream);
            if ($line === false) {
                $info = stream_get_meta_data($stream);
                if ($info['timed_out']) {
                    fclose($stream);
                    throw new \Exception('Handshake timed out after 10 seconds while reading from SSE stream.');
                }
                break;
            }

            $line = trim($line);
            if ($line === '') {
                // Empty line denotes end of an event block
                if ($event === 'endpoint' && ! empty($data)) {
                    $postPath = $data;
                    break;
                }
                $event = '';
                $data = '';

                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($postPath)) {
            fclose($stream);
            throw new \Exception("Failed to receive 'endpoint' event from SSE server. Got last event: '{$event}', data: '{$data}'");
        }

        // The endpoint path can be relative (e.g. /messages?sessionId=...) or absolute.
        // We will construct the full POST URL.
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '');
        if (str_starts_with($postPath, 'http://') || str_starts_with($postPath, 'https://')) {
            $postUrl = $postPath;
        } else {
            $postUrl = $baseUrl . '/' . ltrim($postPath, '/');
        }

        // Parse sessionId from postUrl query
        $postUrlParts = parse_url($postUrl);
        $postQuery = [];
        if (isset($postUrlParts['query'])) {
            parse_str($postUrlParts['query'], $postQuery);
        }
        $sessionId = $postQuery['sessionId'] ?? '';

        Log::channel('ai_agent')->info('mcp_client.handshake_success', [
            'sessionId' => $sessionId,
            'postUrl' => $postUrl,
        ]);

        return [
            'sessionId' => $sessionId,
            'postUrl' => $postUrl,
            'stream' => $stream,
        ];
    }

    /**
     * Send a JSON-RPC 2.0 message to the server via HTTP POST and wait for
     * the response on the persistent SSE stream.
     */
    protected function sendAndReceive(array $handshake, array $payload, int $id, ?string $token = null): array
    {
        $stream = $handshake['stream'];
        $postUrl = $handshake['postUrl'];
        $sessionId = $handshake['sessionId'];

        // Send POST request
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $postParams = [];
        if ($token) {
            $postParams['token'] = $token;
        }

        Log::channel('ai_agent')->debug('mcp_client.send_post', [
            'url' => $postUrl,
            'payload' => $payload,
        ]);

        $postQueryString = http_build_query($postParams);
        $finalPostUrl = $postUrl . ($postQueryString ? '&' . $postQueryString : '');

        // Send HTTP POST using standard Laravel Http Client with strict timeouts
        $response = Http::withHeaders($headers)
            ->timeout(15)        // Maksimal waktu respon HTTP 15 detik
            ->connectTimeout(5)  // Maksimal waktu koneksi HTTP 5 detik
            ->post($finalPostUrl, $payload);

        if ($response->failed()) {
            fclose($stream);
            throw new \Exception("Failed to send POST message to SSE session {$sessionId}: " . $response->body());
        }

        // Now read from the persistent SSE stream until we find the JSON-RPC response with matching id
        $event = '';
        $data = '';
        $maxAttempts = 500;
        $attempts = 0;

        // Set stream read timeout to a safer 20 seconds limit
        stream_set_timeout($stream, 20);

        while (! feof($stream) && $attempts++ < $maxAttempts) {
            $line = fgets($stream);
            if ($line === false) {
                $info = stream_get_meta_data($stream);
                if ($info['timed_out']) {
                    fclose($stream);
                    throw new \Exception("SSE stream read timed out after 20 seconds while waiting for message id {$id}");
                }
                break;
            }

            $line = trim($line);
            if ($line === '') {
                // Process event block
                if ($event === 'message' && ! empty($data)) {
                    $parsedData = json_decode($data, true);
                    if ($parsedData && isset($parsedData['id']) && (int) $parsedData['id'] === $id) {
                        // Found our response!
                        fclose($stream);

                        return $parsedData;
                    }
                }
                $event = '';
                $data = '';

                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        fclose($stream);
        throw new \Exception("SSE stream ended or maximum attempts reached without receiving response for id {$id}");
    }

    /**
     * Get list of tools from the SSE MCP server.
     */
    public function listTools(string $sseUrl, ?string $token = null): array
    {
        $handshake = $this->handshake($sseUrl, $token);

        $id = 1;
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => $id,
        ];

        $response = $this->sendAndReceive($handshake, $payload, $id, $token);

        if (isset($response['error'])) {
            throw new \Exception('Remote MCP tools/list returned error: ' . json_encode($response['error']));
        }

        return $response['result']['tools'] ?? [];
    }

    /**
     * Call a tool on the remote SSE MCP server.
     */
    public function callTool(string $sseUrl, string $toolName, array $arguments = [], ?string $token = null): array
    {
        $handshake = $this->handshake($sseUrl, $token);

        $id = 2;
        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => $toolName,
                'arguments' => $arguments,
            ],
            'id' => $id,
        ];

        $response = $this->sendAndReceive($handshake, $payload, $id, $token);

        if (isset($response['error'])) {
            throw new \Exception('Remote MCP tools/call returned error: ' . json_encode($response['error']));
        }

        // Return standard result format
        return $response['result'] ?? [];
    }
}
