<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateQrSettingRequest;
use App\Models\QrSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrSettingController extends Controller
{
    /**
     * Texts of the printable QR card, and the page the QR code opens.
     */
    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user()->qrSetting);
    }

    public function update(UpdateQrSettingRequest $request): JsonResponse
    {
        $settings = $request->user()->qrSetting()->updateOrCreate([], $request->validated());

        return $this->respond($settings);
    }

    private function respond(?QrSetting $settings): JsonResponse
    {
        $user = request()->user();

        return response()->json(['data' => [
            'header' => $settings?->header ?? 'How did you like our service ?',
            'title' => $settings?->title ?? $user->name,
            // Printed QR codes from the old app open /review/{name}; new ones do the same.
            'url' => config('tiqs.frontend_url').'/review/'.$user->slug,
        ]]);
    }
}
