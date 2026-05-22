<?php

declare(strict_types=1);

use App\Models\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chats.{chatId}', function ($user, $chatId) {
    $chat = Chat::find($chatId);

    return $chat && (int) $chat->user_id === (int) $user->id;
});
