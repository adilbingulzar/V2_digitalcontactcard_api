<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateMissingTexts;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Languages and the texts of the site in one language, for the website.
 */
class TranslationController extends Controller
{
    public function locales(): JsonResponse
    {
        $rtl = config('locales.rtl');

        return response()->json(['data' => collect(config('locales.available'))
            ->map(fn (string $name, string $code): array => ['code' => $code, 'name' => $name, 'dir' => in_array($code, $rtl, true) ? 'rtl' : 'ltr'])
            ->values()]);
    }

    /**
     * All translated texts of a language, keyed by the English text. Texts
     * without a translation are left out: the website then shows the English text.
     */
    public function show(string $locale): JsonResponse
    {
        abort_unless(array_key_exists($locale, config('locales.available')), 404);

        $texts = Cache::rememberForever('translations.'.$locale, fn (): array => Translation::query()
            ->where('locale', $locale)
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->pluck('text', 'key')
            ->all());

        if ($locale !== config('locales.default') && filled(config('services.google_translate.key'))) {
            $total = Cache::remember('translations.en.count', 600, fn (): int => Translation::query()->where('locale', 'en')->count());
            if (count($texts) < $total) {
                TranslateMissingTexts::dispatch($locale);
            }
        }

        return response()->json([
            'data' => [
                'locale' => $locale,
                'dir' => in_array($locale, config('locales.rtl'), true) ? 'rtl' : 'ltr',
                'texts' => (object) $texts,
            ],
        ]);
    }
}
