<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class PaginatedPayload
{
    /**
     * Cut one page out of a cached list and return the `{data, links, meta}` payload a paginated
     * API Resource collection returns. Only the list is cached; links are built from the current
     * request each time, so one request's Host header never reaches another visitor.
     *
     * @param  list<array<string, mixed>>  $items  Resource arrays, already transformed.
     * @return array<string, mixed>
     */
    public static function make(array $items, int $page, int $perPage = 10): array
    {
        $page = max(1, $page);

        $paginator = new LengthAwarePaginator(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return JsonResource::collection($paginator)->response()->getData(true);
    }
}
