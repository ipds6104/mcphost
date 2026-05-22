<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class McpRemoteClientService
{
    protected string $dataServerUrl;

    protected string $dataClientId;

    protected string $dataClientSecret;

    protected string $apiServerUrl;

    protected string $apiClientId;

    protected string $apiClientSecret;

    public function __construct()
    {
        $this->dataServerUrl = rtrim(env('MCP_DATA_SERVER_URL', 'https://mcp-data.bps.go.id'), '/');
        $this->dataClientId = env('MCP_DATA_CLIENT_ID', 'client_laravel_portal');
        $this->dataClientSecret = env('MCP_DATA_CLIENT_SECRET', 'secure_oauth_secret_key_123');

        $this->apiServerUrl = rtrim(env('MCP_API_SERVER_URL', 'https://mcp-api.bps.go.id'), '/');
        $this->apiClientId = env('MCP_API_CLIENT_ID', 'client_laravel_portal');
        $this->apiClientSecret = env('MCP_API_CLIENT_SECRET', 'secure_oauth_secret_key_456');
    }

    /**
     * Get OAuth 2.1 access token for a given server type ('data' or 'api')
     */
    public function getAccessToken(string $type = 'data'): string
    {
        $cacheKey = "mcp_{$type}_access_token";

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($type) {
            $url = $type === 'data' ? $this->dataServerUrl : $this->apiServerUrl;
            $clientId = $type === 'data' ? $this->dataClientId : $this->apiClientId;
            $clientSecret = $type === 'data' ? $this->dataClientSecret : $this->apiClientSecret;

            // Handshake OAuth 2.1 Client Credentials
            $response = Http::asForm()->post("{$url}/oauth/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->failed()) {
                throw new \Exception("Handshake OAuth 2.1 failed for Remote MCP {$type}: " . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * List tools available on the Remote MCP Server
     */
    public function getTools(string $type = 'data'): array
    {
        $cacheKey = "mcp_{$type}_tools";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($type) {
            $url = $type === 'data' ? $this->dataServerUrl : $this->apiServerUrl;
            $token = $this->getAccessToken($type);

            $response = Http::withToken($token)
                ->withHeaders([
                    'Mcp-Session-Id' => Str::uuid()->toString(),
                ])
                ->get("{$url}/mcp/tools");

            if ($response->failed()) {
                throw new \Exception("Failed to fetch tools from Remote MCP {$type}: " . $response->body());
            }

            return $response->json('tools', []);
        });
    }

    /**
     * Call/Execute a tool on the Remote MCP Server under a stateful session
     */
    public function callTool(string $toolName, array $arguments = [], ?string $sessionId = null, string $type = 'data'): array
    {
        $url = $type === 'data' ? $this->dataServerUrl : $this->apiServerUrl;
        $token = $this->getAccessToken($type);
        $sessionId = $sessionId ?? Str::uuid()->toString();

        $response = Http::withToken($token)
            ->withHeaders([
                'Mcp-Session-Id' => $sessionId,
            ])
            ->post("{$url}/mcp/tools/execute", [
                'name' => $toolName,
                'arguments' => $arguments,
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to execute tool '{$toolName}' on Remote MCP {$type}: " . $response->body());
        }

        return $response->json();
    }
}
