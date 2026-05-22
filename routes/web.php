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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ChatController::class, 'index'])->name('dashboard');
    Route::post('/chats', [ChatController::class, 'store'])->name('chats.store');
    Route::get('/chats/{chat}', [ChatController::class, 'show'])->name('chats.show');
    Route::post('/chats/{chat}/messages', [ChatController::class, 'sendMessage'])->name('chats.messages.store');
    Route::patch('/chats/{chat}/rename', [ChatController::class, 'rename'])->name('chats.rename');
    Route::post('/chats/{chat}/toggle-pin', [ChatController::class, 'togglePin'])->name('chats.toggle-pin');
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
