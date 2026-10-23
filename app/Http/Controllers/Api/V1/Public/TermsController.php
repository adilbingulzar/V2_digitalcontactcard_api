<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Content\SanitizeHtml;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class TermsController extends Controller
{
    /**
     * The terms and conditions that people accept when signing up (set by admins).
     */
    public function show(SanitizeHtml $sanitize): JsonResponse
    {
        $settings = AppSetting::current();

        return response()->json(['data' => [
            'html' => $sanitize->handle($settings->terms_and_conditions),
            'updated_at' => $settings->updated_at?->toIso8601String(),
        ]]);
    }
}
