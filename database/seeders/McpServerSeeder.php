<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\McpServer;
use Illuminate\Database\Seeder;

class McpServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        McpServer::updateOrCreate(
            ['name' => 'BPS Agentic Docker'],
            [
                'transport' => 'sse',
                'url' => 'http://bps-mcp-server:3000/sse',
                'token' => 'your_secure_access_token',
                'is_global' => true,
                'is_active' => true,
            ]
        );
    }
}
