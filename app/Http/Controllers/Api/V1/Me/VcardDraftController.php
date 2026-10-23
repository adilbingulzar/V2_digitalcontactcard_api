<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Services\CardDesign\CardDesign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The card builder's unpublished changes, saved as the user works, so they
 * survive a refresh. They are only a work copy: publishing goes through the
 * card itself (VcardController), which checks everything and removes the draft.
 */
class VcardDraftController extends Controller
{
    /**
     * Fields of the builder's draft. Anything else is not kept.
     */
    private const array FIELDS = [
        'is_active', 'name', 'business_title', 'business_name', 'bio', 'business_email', 'country_code', 'phone',
        'business_address', 'appointment_url', 'google_review_url', 'ambassador_url', 'show_business_name',
        'show_location', 'show_opening_hours', 'show_review', 'show_business_icon', 'show_email_icon',
        'show_phone_icon', 'show_address_icon', 'template', 'accent_color', 'section_order', 'design', 'social_links',
    ];

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->vcardDraft()->first()?->data]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'draft' => ['required', 'array:'.implode(',', self::FIELDS)],
            'draft.social_links' => ['nullable', 'array', 'max:20'],
            'draft.social_links.*' => ['nullable', 'string', 'max:2000'],
            'draft.section_order' => ['nullable', 'array', 'max:20'],
            'draft.section_order.*' => ['nullable', 'string', 'max:30'],
            'draft.design' => ['nullable', 'array'],
            ...collect(self::FIELDS)
                ->reject(fn (string $field): bool => in_array($field, ['social_links', 'section_order', 'design'], true))
                ->mapWithKeys(fn (string $field): array => ["draft.{$field}" => ['nullable', str_starts_with($field, 'show_') || $field === 'is_active' ? 'boolean' : 'string', ...(str_starts_with($field, 'show_') || $field === 'is_active' ? [] : ['max:2000'])]])
                ->all(),
        ]);

        $user = $request->user();
        $draft = $request->input('draft');
        // The design is cleaned like a published one, so the draft never holds anything the card would refuse.
        $draft['design'] = CardDesign::forOutput((new CardDesign($user))->clean($draft['design'] ?? null));

        $user->vcardDraft()->updateOrCreate([], ['data' => $draft]);

        return response()->json(['data' => $draft]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->vcardDraft()->delete();

        return response()->json(null, 204);
    }
}
