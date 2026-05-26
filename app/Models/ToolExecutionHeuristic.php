<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ToolExecutionHeuristic extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tool_name',
        'error_signature',
        'parameter_pattern',
        'parameter_pattern_hash',
        'rewrite_instruction',
        'success_count',
        'failure_count',
        'validation_count',
        'is_active',
        'is_system',
        'last_validated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameter_pattern' => 'array',
        'rewrite_instruction' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'last_validated_at' => 'datetime',
    ];

    /**
     * Menghasilkan hash SHA-256 kanonikal yang stabil untuk pola parameter masukan.
     */
    public static function generatePatternHash(array $pattern): string
    {
        $sortedPattern = $pattern;
        self::ksortRecursive($sortedPattern);
        $canonicalJson = json_encode($sortedPattern, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $canonicalJson);
    }

    /**
     * Urutkan kunci array secara alfabetis dan rekursif.
     */
    private static function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
