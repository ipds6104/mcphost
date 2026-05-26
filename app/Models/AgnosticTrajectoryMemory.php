<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgnosticTrajectoryMemory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'intent_pattern',
        'successful_execution_graph',
        'applied_heuristic_ids',
        'score',
        'duration',
        'use_count',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'successful_execution_graph' => 'array',
        'applied_heuristic_ids' => 'array',
        'score' => 'float',
        'duration' => 'float',
        'use_count' => 'integer',
        'last_used_at' => 'datetime',
    ];
}
