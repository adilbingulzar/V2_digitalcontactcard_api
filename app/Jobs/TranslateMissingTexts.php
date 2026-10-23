<?php

namespace App\Jobs;

use App\Models\Translation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Translates English texts that have no translation in a language yet, with
 * Google Translate, like the old app did page by page. Runs in the background
 * when a language is first asked for; admins can correct the results.
 */
class TranslateMissingTexts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Texts per run; the next visit to the language starts another run.
     */
    private const int LIMIT = 300;

    public int $uniqueFor = 600;

    public function __construct(public string $locale) {}

    public function uniqueId(): string
    {
        return $this->locale;
    }

    public function handle(): void
    {
        $key = config('services.google_translate.key');
        if (blank($key) || $this->locale === config('locales.default')) {
            return;
        }

        $missing = Translation::query()
            ->where('locale', 'en')
            ->whereNotIn('key', Translation::query()->select('key')->where('locale', $this->locale))
            ->limit(self::LIMIT)
            ->get(['key', 'text', 'group']);

        foreach ($missing->chunk(100) as $chunk) {
            $sources = $chunk->map(fn (Translation $row): string => $this->protectPlaceholders((string) ($row->text ?? $row->key)))->values();
            $response = Http::timeout(20)->asForm()->post('https://translation.googleapis.com/language/translate/v2?key='.urlencode((string) $key), [
                'q' => $sources->all(),
                'source' => 'en',
                'target' => $this->locale === 'zh' ? 'zh-CN' : $this->locale,
                'format' => 'text',
            ]);

            if ($response->failed()) {
                report(new \RuntimeException('Google Translate answered HTTP '.$response->status().' for '.$this->locale.'.'));

                return;
            }

            $now = now();
            $rows = $chunk->values()->map(fn (Translation $row, int $index): array => [
                'locale' => $this->locale,
                'key' => $row->key,
                'text' => $this->restorePlaceholders((string) $response->json("data.translations.{$index}.translatedText", '')),
                'group' => $row->group,
                'created_at' => $now,
                'updated_at' => $now,
            ])->filter(fn (array $row): bool => $row['text'] !== '')->all();

            Translation::query()->insertOrIgnore($rows);
        }

        Cache::forget('translations.'.$this->locale);
    }

    /**
     * Placeholders like {name} must come back unchanged, so they are hidden from the translator.
     */
    private function protectPlaceholders(string $text): string
    {
        return (string) preg_replace('/\{([a-z_]+)\}/', '<x id="$1"/>', $text);
    }

    private function restorePlaceholders(string $text): string
    {
        return html_entity_decode((string) preg_replace('/<x id="([a-z_]+)"\s*\/?>(<\/x>)?/', '{$1}', $text), ENT_QUOTES | ENT_HTML5);
    }
}
