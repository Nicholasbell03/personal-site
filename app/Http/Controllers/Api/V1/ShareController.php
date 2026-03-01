<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShareRequest;
use App\Http\Resources\RelatedItemResource;
use App\Http\Resources\ShareResource;
use App\Http\Resources\ShareSummaryResource;
use App\Models\Share;
use App\Services\OpenGraphService;
use App\Services\RelatedContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShareController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24; // 24 hours

    public function index(Request $request): JsonResponse
    {
        $page = $request->integer('page', 1);
        $cacheKey = Share::getApiCacheKey().".index.{$page}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $shares = Share::query()
                ->latest()
                ->paginate(10);

            return ShareSummaryResource::collection($shares)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function featured(): JsonResponse
    {
        $cacheKey = Share::getApiCacheKey().'.featured';

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $shares = Share::query()
                ->latest()
                ->limit(3)
                ->get();

            return ShareSummaryResource::collection($shares)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function show(string $slug): JsonResponse
    {
        $cacheKey = Share::getApiCacheKey().".show.{$slug}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug) {
            $share = Share::query()
                ->where('slug', $slug)
                ->firstOrFail();

            return (new ShareResource($share))->response()->getData(true);
        });

        return response()->json($data);
    }

    public function related(string $slug, RelatedContentService $service): JsonResponse
    {
        $cacheKey = Share::getApiCacheKey().".related.{$slug}";

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($slug, $service) {
            $share = Share::query()
                ->where('slug', $slug)
                ->firstOrFail();

            $next = $service->getNextItem($share);
            $related = $service->getRelatedItems($share);

            return [
                'data' => [
                    'next' => $next ? (new RelatedItemResource($next))->resolve() : null,
                    'related' => $related->map(fn (array $item) => (new RelatedItemResource($item['item']))->resolve())->values()->all(),
                ],
            ];
        });

        return response()->json($data);
    }

    public function store(StoreShareRequest $request, OpenGraphService $openGraphService): JsonResponse
    {
        $validated = $request->validated();

        $ogData = $openGraphService->fetch($validated['url']);

        $share = Share::create([
            'url' => $validated['url'],
            'source_type' => $ogData['source_type'],
            'title' => $validated['title'] ?? $ogData['title'],
            'description' => $validated['description'] ?? $ogData['description'],
            'image_url' => $ogData['image'],
            'site_name' => $ogData['site_name'],
            'author' => $ogData['author'],
            'commentary' => $validated['commentary'] ?? null,
            'embed_data' => $ogData['embed_data'],
            'og_raw' => $ogData['og_raw'],
            'post_to_x' => $validated['post_to_x'] ?? true,
        ]);

        return (new ShareResource($share))
            ->response()
            ->setStatusCode(201);
    }
}
