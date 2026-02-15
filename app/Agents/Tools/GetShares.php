<?php

namespace App\Agents\Tools;

use App\Http\Resources\ShareSummaryResource;
use App\Models\Share;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetShares implements Tool
{
    public function description(): string
    {
        return 'Get a list of recent shares sorted by date. Shares are web articles, YouTube videos, and X posts that Nick has curated with his own commentary. Use this when the user wants to browse what Nick has shared recently. Do NOT use this to search for shares about a specific topic — use SearchContent instead.';
    }

    public function handle(Request $request): string
    {
        $limit = min($request->integer('limit', 3), 5);

        $shares = Share::query()
            ->latest()
            ->limit($limit)
            ->get();

        if ($shares->isEmpty()) {
            return 'No shared content found.';
        }

        return ShareSummaryResource::collection($shares)->toJson(JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema
                ->integer()
                ->description('Number of shares to return (1-5, default 3).'),
        ];
    }
}
