<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpServer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'transport',
        'url',
        'token',
        'is_global',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the user-specific MCP server.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
