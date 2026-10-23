<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Content\SanitizeHtml;
use App\Mail\TemplatedMail;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Wording of the emails the app sends (the old "Email template" page). A saved
 * template replaces the built-in text of that email; placeholders such as
 * {name} are filled in when it is sent.
 */
class EmailTemplateController extends AdminController
{
    public function index(): JsonResponse
    {
        $saved = EmailTemplate::query()->get()->keyBy('name');

        return response()->json(['data' => collect(TemplatedMail::TEMPLATES)->map(fn (array $template, string $name): array => [
            'name' => $name,
            'label' => $template['label'],
            'placeholders' => $template['placeholders'],
            'subject' => $saved->get($name)?->subject ?? $template['subject'],
            'html' => $saved->get($name)?->html ?? $template['html'],
            'is_custom' => $saved->has($name) && $saved->get($name)->is_active,
            'updated_at' => $saved->get($name)?->updated_at?->toIso8601String(),
        ])->values()->all()]);
    }

    public function update(Request $request, string $name, SanitizeHtml $sanitize): JsonResponse
    {
        abort_unless(array_key_exists($name, TemplatedMail::TEMPLATES), 404);
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'html' => ['required', 'string', 'max:100000'],
        ]);

        $template = EmailTemplate::query()->updateOrCreate(['name' => $name], [
            'type' => 'transactional',
            'subject' => $validated['subject'],
            'html' => $sanitize->handle($validated['html'], allowPlaceholderLinks: true),
            'is_active' => true,
        ]);

        return response()->json(['data' => ['name' => $name, 'subject' => $template->subject, 'html' => $template->html, 'is_custom' => true]]);
    }

    /**
     * Go back to the built-in text.
     */
    public function destroy(string $name): Response
    {
        abort_unless(array_key_exists($name, TemplatedMail::TEMPLATES), 404);
        EmailTemplate::query()->where('name', $name)->delete();

        return response()->noContent();
    }
}
