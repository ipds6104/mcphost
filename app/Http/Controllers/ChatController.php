<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessAiAgentQuery;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Tampilkan daftar chat pengguna
     */
    public function index(): Response
    {
        $chats = Chat::where('user_id', Auth::id())
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Chat/Show', [
            'chats' => $chats,
            'currentChat' => null,
            'messages' => [],
        ]);
    }

    /**
     * Buat sesi obrolan baru (Mendukung inisialisasi Zero-Setup)
     */
    public function store(Request $request): RedirectResponse
    {
        // Mendukung pembuatan instan dari pesan pertama
        if ($request->has('content')) {
            $request->validate([
                'content' => 'required|string',
                'images.*' => 'nullable|image|max:10240',
            ]);

            // Buat judul otomatis yang bersih dari baris pertama pesan
            $cleanTitle = trim(strip_tags($request->content));
            $cleanTitle = \Illuminate\Support\Str::limit($cleanTitle, 40, '...');
            if (empty($cleanTitle)) {
                $cleanTitle = 'Percakapan Baru';
            }

            $chat = Chat::create([
                'user_id' => Auth::id(),
                'title' => $cleanTitle,
            ]);

            // Proses lampiran gambar multi-gambar jika diunggah
            $attachments = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('chats', 'public');
                    $attachments[] = 'storage/' . $path;
                }
            }

            // Simpan pesan awal pengguna
            $userMessage = Message::create([
                'chat_id' => $chat->id,
                'role' => 'user',
                'content' => $request->content,
                'attachments' => count($attachments) > 0 ? $attachments : null,
            ]);

            // Dispatch orkestrasi AI secara asinkron
            ProcessAiAgentQuery::dispatch($chat->id, $userMessage->id);

            return redirect()->route('chats.show', $chat->id);
        }

        // Kemampuan pembuatan sesi manual (Fallback)
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $chat = Chat::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
        ]);

        return redirect()->route('chats.show', $chat->id);
    }

    /**
     * Tampilkan riwayat obrolan dari sesi terpilih
     */
    public function show(Chat $chat): Response
    {
        // Pastikan chat milik user yang terautentikasi
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $chats = Chat::where('user_id', Auth::id())
            ->orderBy('is_pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $messages = Message::where('chat_id', $chat->id)
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Chat/Show', [
            'chats' => $chats,
            'currentChat' => $chat,
            'messages' => $messages,
        ]);
    }

    /**
     * Kirim pesan obrolan pengguna (dengan dukungan upload multi-gambar)
     */
    public function sendMessage(Request $request, Chat $chat): RedirectResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'content' => 'required|string',
            'images.*' => 'nullable|image|max:10240', // Maks 10MB per gambar
        ]);

        // Simpan gambar-gambar lampiran (Multimodal) jika ada
        $attachments = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('chats', 'public');
                $attachments[] = 'storage/' . $path;
            }
        }

        // Simpan pesan user ke database
        $userMessage = Message::create([
            'chat_id' => $chat->id,
            'role' => 'user',
            'content' => $request->content,
            'attachments' => count($attachments) > 0 ? $attachments : null,
        ]);

        // Sentuh timestamp chat agar naik ke urutan teratas
        $chat->touch();

        // Dispatch background processing job untuk diorkestrasi asinkron oleh Laravel AI SDK
        ProcessAiAgentQuery::dispatch($chat->id, $userMessage->id);

        return redirect()->back();
    }

    /**
     * Ubah nama sesi obrolan
     */
    public function rename(Request $request, Chat $chat): RedirectResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $chat->update([
            'title' => $request->title,
        ]);

        return redirect()->back();
    }

    /**
     * Sematkan atau lepas sematan sesi obrolan
     */
    public function togglePin(Chat $chat): RedirectResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $chat->update([
            'is_pinned' => ! $chat->is_pinned,
        ]);

        return redirect()->back();
    }

    /**
     * Hapus sesi obrolan
     */
    public function destroy(Chat $chat): RedirectResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        $chat->delete();

        return redirect()->route('dashboard');
    }

    /**
     * Memicu pembuatan ulang (regenerate) respons AI
     */
    public function regenerate(Chat $chat, Message $message): RedirectResponse
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403);
        }

        // Pastikan message milik chat ini dan memiliki role assistant
        if ($message->chat_id !== $chat->id || $message->role !== 'assistant') {
            abort(400);
        }

        // Cari pesan user terakhir sebelum pesan asisten ini
        $userMessage = Message::where('chat_id', $chat->id)
            ->where('id', '<', $message->id)
            ->where('role', 'user')
            ->orderBy('id', 'desc')
            ->first();

        if (! $userMessage) {
            abort(400);
        }

        // Hapus pesan asisten lama
        $message->delete();

        // Sentuh timestamp chat agar diperbarui
        $chat->touch();

        // Dispatch ulang job pemrosesan AI
        ProcessAiAgentQuery::dispatch($chat->id, $userMessage->id);

        return redirect()->back();
    }
}
