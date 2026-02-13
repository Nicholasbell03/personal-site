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
        return 'Get a list of recently shared links and content. These are URLs Nick has bookmarked or shared with commentary.';
    }

    public function handle(Request $request): string
    {
        $limit = min($request->integer('limit', 5), 10);

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
                ->description('Number of shares to return (1-10, default 5).'),
        ];
    }
}
