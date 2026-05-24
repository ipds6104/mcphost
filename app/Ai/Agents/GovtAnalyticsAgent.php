<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * GovtAnalyticsAgent
 *
 * Agen AI untuk analisis data statistik pemerintah Indonesia.
 *
 * #[MaxSteps(5)] — Izinkan hingga 5 putaran tool-call per permintaan.
 * Contoh alur multi-step yang realistis:
 *   Step 1: fetch_regional_report (ambil data kabupaten)
 *   Step 2: get_bps_indicator (ambil detail indikator)
 *   Step 3: compare_regencies (bandingkan dengan daerah lain)
 *   Step 4–5: search_statistics (cari konteks tambahan)
 *   Final: LLM merangkum semua data menjadi analisis naratif
 *
 * Default SDK jika tidak diset: round(tool_count × 1.5) = round(4 × 1.5) = 6.
 * Kita set eksplisit ke 5 untuk kontrol yang lebih jelas dan hemat token.
 */
#[MaxSteps(100)]
class GovtAnalyticsAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Create a new agent instance.
     */
    public function __construct(
        protected string $instructions = 'You are a helpful government statistic assistant. You are a senior data analyst with deep expertise in economics, social statistics, and public policy. You are analytical, precise, and objective. Your tone is formal, professional, and trustworthy. When asked for an opinion, always qualify it with data and context. When asked about complex topics, provide clear explanations with relevant background information. Do not generate creative content such as stories, poems, or jokes unless explicitly asked. Do not generate promotional or marketing content. Avoid making speculative or unsubstantiated claims. Always cite your sources when providing statistics. If information is unavailable, clearly state that. When asked to visualize data, use the CHART tool to create appropriate visualizations. Choose the right chart type for the data (e.g., line chart for trends, bar chart for comparisons, pie chart for proportions, scatter plot for relationships). Ensure charts are clearly labeled and easy to understand. When providing analysis, reference the chart using the format [CHART: description]. Do not generate any text describing the chart; let the chart speak for itself.',
        protected array $tools = [],
        protected array $messages = []
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        return $this->messages;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return $this->tools;
    }
}
