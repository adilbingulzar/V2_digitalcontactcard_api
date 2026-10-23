<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Texts of the site per language (the old "Translation" page). Every English
 * text is a key; other languages translate it.
 */
class TranslationController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $locales = collect(array_keys(config('locales.available')));
        $validated = $request->validate([
            'locale' => ['nullable', 'string', Rule::in($locales->all())],
            'q' => ['nullable', 'string', 'max:200'],
            'missing' => ['nullable', 'boolean'],
        ]);
        $locale = $validated['locale'] ?? 'nl';

        $page = Translation::query()
            ->from('translations as source')
            ->where('source.locale', 'en')
            ->leftJoin('translations as target', fn ($join) => $join->on('target.key', '=', 'source.key')->where('target.locale', $locale))
            ->when($validated['q'] ?? null, fn (Builder $rows, string $q) => $rows->where(fn (Builder $match) => $match
                ->where('source.key', 'like', '%'.$q.'%')
                ->orWhere('source.text', 'like', '%'.$q.'%')
                ->orWhere('target.text', 'like', '%'.$q.'%')))
            ->when($request->boolean('missing'), fn (Builder $rows) => $rows->where(fn (Builder $empty) => $empty->whereNull('target.text')->orWhere('target.text', '')))
            ->orderBy('source.key')
            ->select('source.key', 'source.text as source_text', 'source.group', 'target.text as text', 'target.id as translation_id')
            ->paginate(50)
            ->withQueryString();

        return $this->paginated($page, fn (Translation $row): array => [
            'key' => $row->key,
            'group' => $row->group,
            'source' => $row->source_text,
            'text' => $row->text,
        ], ['locale' => $locale, 'locales' => $locales->all()]);
    }

    /**
     * Save one text. An empty text removes the translation, so the English text shows.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('locales.available')))],
            'key' => ['required', 'string', 'max:500', Rule::exists('translations', 'key')->where('locale', 'en')],
            'text' => ['nullable', 'string', 'max:5000'],
        ]);

        if (blank($validated['text'] ?? null)) {
            abort_if($validated['locale'] === 'en', 422, 'English texts cannot be empty.');
            Translation::query()->where('locale', $validated['locale'])->where('key', $validated['key'])->delete();
        } else {
            $group = Translation::query()->where('locale', 'en')->where('key', $validated['key'])->value('group');
            Translation::query()->updateOrCreate(
                ['locale' => $validated['locale'], 'key' => $validated['key']],
                ['text' => $validated['text'], 'group' => $group],
            );
        }
        cache()->forget('translations.'.$validated['locale']);

        return response()->json(['data' => ['locale' => $validated['locale'], 'key' => $validated['key'], 'text' => $validated['text'] ?? null]]);
    }
}
