<?php

namespace App\Services;

use App\Models\Share;
use Illuminate\Support\Facades\Log;

use function Laravel\Ai\agent;

class SummaryService
{
    public function generate(Share $share): ?string
    {
        $commentary = trim(strip_tags($share->commentary ?? ''));

        if ($commentary === '') {
            Log::info('SummaryService: no commentary to summarise, skipping', [
                'share_id' => $share->id,
            ]);

            return null;
        }

        $context = implode("\n", array_filter([
            $share->title ? "Title: {$share->title}" : null,
            $share->description ? "Description: {$share->description}" : null,
        ]));

        $prompt = <<<PROMPT
        Condense the following commentary into a short, punchy take — max 280 characters. The commentary IS the opinion/angle — faithfully distil the author's thoughts, don't reinterpret the original content independently. The title and description are context only.

        {$context}

        Commentary: {$commentary}
        PROMPT;

        try {
            $response = agent(instructions: 'You are a concise writing assistant. Output only the condensed take — no quotes, no labels, no preamble.')
                ->prompt(
                    $prompt,
                    provider: config('services.summary.provider'),
                    model: config('services.summary.model'),
                );

            $summary = trim($response->text);

            if ($summary === '') {
                Log::warning('SummaryService: AI returned empty summary', [
                    'share_id' => $share->id,
                ]);

                return null;
            }

            $summary = mb_substr($summary, 0, 280);

            Log::info('SummaryService: summary generated', [
                'share_id' => $share->id,
                'length' => mb_strlen($summary),
            ]);

            return $summary;
        } catch (\Throwable $e) {
            Log::error('SummaryService: summary generation failed', [
                'share_id' => $share->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
