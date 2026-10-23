<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Spot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates numbered spots in one go, as the old "one click multiple spot"
 * option did. Each spot gets a free address within the business.
 */
class CreateNumberedSpots
{
    /**
     * @return Collection<int, Spot>
     */
    public function handle(Business $business, string $name, int $count, bool $space): Collection
    {
        return DB::transaction(function () use ($business, $name, $count, $space): Collection {
            $taken = $business->spots()->pluck('slug')->flip();
            $spots = collect();

            for ($number = 1; $number <= $count; $number++) {
                $spotName = $name.($space ? ' ' : '').$number;
                $slug = $base = Str::slug($spotName) ?: 'spot-'.$number;
                for ($suffix = 2; $taken->has($slug); $suffix++) {
                    $slug = $base.'-'.$suffix;
                }
                $taken->put($slug, true);

                $spots->push($business->spots()->create(['name' => $spotName, 'slug' => $slug, 'is_active' => true]));
            }

            return $spots;
        });
    }
}
