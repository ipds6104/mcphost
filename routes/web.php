<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\ChatController;
use App\Http\Controllers\McpServerController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ChatController::class, 'index'])->name('dashboard');
    Route::post('/chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('/chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::post('/chats/{chat}/messages', [ChatController::class, 'sendMessage'])->name('chats.messages.store');
    Route::post('/chats/{chat}/messages/{message}/regenerate', [ChatController::class, 'regenerate'])->name('chats.messages.regenerate');
    Route::patch('/chats/{chat}/rename', [ChatController::class, 'rename'])->name('chats.rename');
    Route::post('/chats/{chat}/toggle-pin', [ChatController::class, 'togglePin'])->name('chats.toggle-pin');
    Route::post('/chats/{chat}/cancel', [ChatController::class, 'cancel'])->name('chats.cancel');
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');

    // Rute Pengelolaan Server MCP
    Route::get('/mcp-servers', [McpServerController::class, 'index'])->name('mcp-servers.index');
    Route::post('/mcp-servers/toggle-builtin', [McpServerController::class, 'toggleBuiltinMcp'])->name('mcp-servers.toggle-builtin');
    Route::post('/mcp-servers', [McpServerController::class, 'store'])->name('mcp-servers.store');
    Route::put('/mcp-servers/{mcp_server}', [McpServerController::class, 'update'])->name('mcp-servers.update');
    Route::patch('/mcp-servers/{mcp_server}/toggle', [McpServerController::class, 'toggleActive'])->name('mcp-servers.toggle');
    Route::post('/mcp-servers/{mcp_server}/test', [McpServerController::class, 'testConnection'])->name('mcp-servers.test');
    Route::delete('/mcp-servers/{mcp_server}', [McpServerController::class, 'destroy'])->name('mcp-servers.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
