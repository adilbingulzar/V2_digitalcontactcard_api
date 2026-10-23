<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Base of the admin API. The routes already require an admin (see the "admin" gate).
 */
abstract class AdminController extends Controller
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @param  callable(mixed): array<string, mixed>  $map
     * @param  array<string, mixed>  $extraMeta
     */
    protected function paginated(LengthAwarePaginator $page, callable $map, array $extraMeta = []): JsonResponse
    {
        return response()->json([
            'data' => collect($page->items())->map($map)->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                ...$extraMeta,
            ],
        ]);
    }

    protected function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
