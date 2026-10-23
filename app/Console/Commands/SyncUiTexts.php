<?php

namespace App\Console\Commands;

use App\Models\Translation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Adds the texts of the website (database/seeders/data/ui-texts.json, made
 * by "npm run texts" in the frontend) to the translations table. Only adds
 * what is missing, so translations edited by admins are never overwritten.
 * Run it on every deploy.
 */
#[Signature('tiqs:sync-ui-texts')]
#[Description('Add new website texts and their bundled translations without overwriting admin edits')]
class SyncUiTexts extends Command
{
    public function handle(): int
    {
        $file = database_path('seeders/data/ui-texts.json');
        if (! is_file($file)) {
            $this->components->error('database/seeders/data/ui-texts.json is missing.');

            return self::FAILURE;
        }

        /** @var array{texts: list<string>, translations: array<string, array<string, string>>} $data */
        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        $now = now();
        $added = 0;

        $rows = array_map(fn (string $text): array => ['locale' => 'en', 'key' => $text, 'text' => $text, 'group' => 'website', 'created_at' => $now, 'updated_at' => $now], $data['texts']);
        foreach ($data['translations'] as $locale => $texts) {
            foreach ($texts as $key => $text) {
                $rows[] = ['locale' => $locale, 'key' => $key, 'text' => $text, 'group' => 'website', 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $added += Translation::query()->insertOrIgnore($chunk);
        }

        foreach (array_keys(config('locales.available')) as $locale) {
            Cache::forget('translations.'.$locale);
        }
        Cache::forget('translations.en.count');

        $this->components->info("Website texts synced: {$added} added.");

        return self::SUCCESS;
    }
}
