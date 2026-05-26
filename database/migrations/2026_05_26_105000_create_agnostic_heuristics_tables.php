<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tool_execution_heuristics', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name', 100);
            $table->string('error_signature', 100);
            $table->jsonb('parameter_pattern');
            $table->string('parameter_pattern_hash', 64);
            $table->jsonb('rewrite_instruction');
            $table->integer('success_count')->default(1);
            $table->integer('failure_count')->default(0);
            $table->integer('validation_count')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_validated_at')->useCurrent();
            $table->timestamps();

            // Unique constraint berbasis hash untuk kestabilan JSONB di PostgreSQL
            $table->unique(['tool_name', 'error_signature', 'parameter_pattern_hash'], 'idx_tool_err_param_hash');
        });

        Schema::create('agnostic_trajectory_memories', function (Blueprint $table) {
            $table->id();
            $table->text('intent_pattern');
            $table->jsonb('successful_execution_graph');
            $table->jsonb('applied_heuristic_ids')->nullable(); // Menghubungkan lintasan dengan heuristik
            $table->float('score');
            $table->float('duration');
            $table->integer('use_count')->default(1);
            $table->timestamp('last_used_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_execution_heuristics');
        Schema::dropIfExists('agnostic_trajectory_memories');
    }
};
