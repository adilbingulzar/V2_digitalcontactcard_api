<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\VisitorLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Visits to the public tip and review pages (the old "Visitor logs" page).
 */
class VisitorLogController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_type' => ['nullable', 'in:review,paytip,review_spot,paytip_spot'],
            'from' => ['nullable', 'date'],
            'until' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = VisitorLog::query()
            ->when($validated['page_type'] ?? null, fn (Builder $visits, string $type) => $visits->where('page_type', $type))
            ->when($validated['from'] ?? null, fn (Builder $visits, string $from) => $visits->whereDate('created_at', '>=', $from))
            ->when($validated['until'] ?? null, fn (Builder $visits, string $until) => $visits->whereDate('created_at', '<=', $until));

        $byType = (clone $query)->selectRaw('page_type, COUNT(*) as visits')->groupBy('page_type')->pluck('visits', 'page_type');
        $page = $query->latest('id')->paginate(50)->withQueryString();

        return $this->paginated($page, fn (VisitorLog $visit): array => [
            'id' => $visit->id,
            'page_type' => $visit->page_type,
            'page_url' => $visit->page_url,
            'ip_address' => $visit->ip_address,
            'completed' => $visit->completed,
            'created_at' => $visit->created_at?->toIso8601String(),
        ], ['by_type' => $byType]);
    }
}
