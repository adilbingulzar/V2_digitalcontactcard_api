<?php

namespace App\Actions\Business;

use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the weekly shift hours of a team member at a business (or at one
 * of its spots) with the given days. Days left out have no shift.
 *
 * Also keeps the opening hours shown on a user's business card (purpose
 * "vcard"); those belong to the user alone, whatever business they work for.
 */
class ReplaceShifts
{
    /**
     * @param  array<string, list<array{from: string, to: string}>>  $days  e.g. ["Mon" => [["from" => "09:00", "to" => "17:00"]]]
     */
    public function handle(string $purpose, int $userId, ?int $businessId, ?int $spotId, array $days): void
    {
        DB::transaction(function () use ($purpose, $userId, $businessId, $spotId, $days): void {
            $this->query($purpose, $userId, $businessId, $spotId)->delete();

            foreach ($days as $day => $ranges) {
                if ($ranges === []) {
                    continue;
                }

                WorkingHour::create([
                    'purpose' => $purpose,
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'spot_id' => $spotId,
                    'day' => $day,
                    'time_ranges' => array_values($ranges),
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * @return array<string, list<array{from: string, to: string}>>
     */
    public function current(string $purpose, int $userId, ?int $businessId, ?int $spotId): array
    {
        $days = array_fill_keys(WorkingHour::DAYS, []);

        // MySQL JSON columns reorder keys, so each range is rebuilt as from/to.
        foreach ($this->query($purpose, $userId, $businessId, $spotId)->get() as $hours) {
            $days[$hours->day] = array_map(
                fn (array $range): array => ['from' => $range['from'] ?? '', 'to' => $range['to'] ?? ''],
                $hours->time_ranges ?? [],
            );
        }

        return $days;
    }

    /**
     * @return Builder<WorkingHour>
     */
    private function query(string $purpose, int $userId, ?int $businessId, ?int $spotId)
    {
        return WorkingHour::query()
            ->where('purpose', $purpose)
            ->where('user_id', $userId)
            ->when($purpose !== 'vcard', fn ($query) => $query->where('business_id', $businessId))
            ->when($spotId === null, fn ($query) => $query->whereNull('spot_id'), fn ($query) => $query->where('spot_id', $spotId));
    }
}
