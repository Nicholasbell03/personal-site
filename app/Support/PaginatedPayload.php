<?php

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class PaginatedPayload
{
    private const PER_PAGE = 10;

    /**
     * Cut one page out of a cached list and return the `{data, links, meta}` payload a paginated
     * API Resource collection returns. Only the list is cached; links are built from the current
     * request each time, so one request's Host header never reaches another visitor.
     *
     * @param  list<array<string, mixed>>  $items  Resource arrays, already transformed.
     * @return array<string, mixed>
     */
    public static function make(array $items, int $page): array
    {
        $page = max(1, $page);
        $total = count($items);

        // Guard first: an absurd ?page= would overflow the offset into a float and 500.
        $slice = $page > (int) ceil($total / self::PER_PAGE)
            ? []
            : array_slice($items, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $paginator = new LengthAwarePaginator(
            $slice,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        return JsonResource::collection($paginator)->response()->getData(true);
    }
}
