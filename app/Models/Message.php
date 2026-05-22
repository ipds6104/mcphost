<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'role',
        'content',
        'attachments',
        'agent_steps',
        'chart_data',
    ];

    protected $casts = [
        'attachments' => 'array',
        'agent_steps' => 'array',
        'chart_data' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
