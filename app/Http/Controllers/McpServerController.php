<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\McpServer;
use App\Services\McpSseClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class McpServerController extends Controller
{
    /**
     * Tampilkan daftar Server MCP yang terdaftar
     */
    public function index(): Response
    {
        // 1. Dapatkan riwayat obrolan untuk sidebar
        $chats = Chat::where('user_id', Auth::id())
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        // 2. Dapatkan daftar server MCP (Milik user atau global)
        $mcpServers = McpServer::where('is_global', true)
            ->orWhere('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('McpServers/Index', [
            'chats' => $chats,
            'mcpServers' => $mcpServers,
            'disableBuiltinMcp' => (bool) Auth::user()->disable_builtin_mcp,
        ]);
    }

    /**
     * Ubah status preferensi penonaktifan klien MCP bawaan
     */
    public function toggleBuiltinMcp(Request $request): RedirectResponse
    {
        $request->validate([
            'disable_builtin' => 'required|boolean',
        ]);

        Auth::user()->update([
            'disable_builtin_mcp' => $request->disable_builtin,
        ]);

        $statusText = $request->disable_builtin
            ? 'diaktifkan (Hanya menggunakan Server MCP Dinamis).'
            : 'dinonaktifkan (Menggunakan MCP Bawaan & Dinamis).';

        return redirect()->back()->with('success', 'Mode Server MCP Dinamis Eksklusif berhasil ' . $statusText);
    }

    /**
     * Simpan Server MCP baru ke database
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'token' => 'nullable|string|max:1024',
            'is_active' => 'required|boolean',
        ]);

        McpServer::create([
            'name' => $request->name,
            'url' => $request->url,
            'token' => $request->token,
            'is_global' => true, // Default global agar dapat dipakai oleh asisten chatbot
            'user_id' => Auth::id(),
            'is_active' => $request->is_active,
        ]);

        return redirect()->back()->with('success', 'Server MCP berhasil didaftarkan.');
    }

    /**
     * Perbarui Server MCP terpilih
     */
    public function update(Request $request, McpServer $mcpServer): RedirectResponse
    {
        // Cek otorisasi jika bukan global atau dibuat oleh user lain
        if (! $mcpServer->is_global && $mcpServer->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'token' => 'nullable|string|max:1024',
            'is_active' => 'required|boolean',
        ]);

        $mcpServer->update([
            'name' => $request->name,
            'url' => $request->url,
            'token' => $request->token,
            'is_active' => $request->is_active,
        ]);

        return redirect()->back()->with('success', 'Server MCP berhasil diperbarui.');
    }

    /**
     * Toggle status aktif Server MCP
     */
    public function toggleActive(McpServer $mcpServer): RedirectResponse
    {
        if (! $mcpServer->is_global && $mcpServer->user_id !== Auth::id()) {
            abort(403);
        }

        $mcpServer->update([
            'is_active' => ! $mcpServer->is_active,
        ]);

        return redirect()->back()->with('success', 'Status Server MCP diperbarui.');
    }

    /**
     * Uji koneksi ke Server MCP secara instan menggunakan handshake SSE & tools/list
     */
    public function testConnection(McpServer $mcpServer): JsonResponse
    {
        if (! $mcpServer->is_global && $mcpServer->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menguji server ini.',
            ], 403);
        }

        $sseClient = new McpSseClient();

        try {
            Log::info("Menguji koneksi server MCP: {$mcpServer->name} ke {$mcpServer->url}");

            // Lakukan pemanggilan listTools untuk mengetes handshake + request tools
            $tools = $sseClient->listTools($mcpServer->url, $mcpServer->token);
            $toolNames = array_map(fn ($t) => $t['name'], $tools);

            return response()->json([
                'success' => true,
                'message' => 'Koneksi Sukses!',
                'details' => 'Server aktif dan merespons dengan sempurna.',
                'tools' => $toolNames,
            ]);
        } catch (\Exception $e) {
            Log::error("Gagal melakukan tes koneksi ke server MCP {$mcpServer->name}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Koneksi Gagal!',
                'details' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Hapus Server MCP dari database
     */
    public function destroy(McpServer $mcpServer): RedirectResponse
    {
        if (! $mcpServer->is_global && $mcpServer->user_id !== Auth::id()) {
            abort(403);
        }

        $mcpServer->delete();

        return redirect()->back()->with('success', 'Server MCP berhasil dihapus.');
    }
}
