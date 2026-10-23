<?php

namespace Database\Seeders;

use App\Models\Translation;
use Illuminate\Database\Seeder;

class TranslationSeeder extends Seeder
{
    /**
     * Load the UI translations from database/seeders/data/translations.json.
     *
     * Safe to run again: existing rows are updated in place, so admin edits
     * to keys that are not in the file are kept.
     */
    public function run(): void
    {
        /** @var array<string, array<string, array{text: string|null, group: string|null}>> $locales */
        $locales = json_decode(
            file_get_contents((string) config('tiqs.seed_data.translations_path')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $now = now();

        foreach ($locales as $locale => $entries) {
            $rows = [];

            foreach ($entries as $key => $entry) {
                $rows[] = [
                    'locale' => $locale,
                    'key' => $key,
                    'text' => $entry['text'],
                    'group' => $entry['group'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                Translation::query()->upsert($chunk, ['locale', 'key'], ['text', 'group', 'updated_at']);
            }
        }
    }
}
