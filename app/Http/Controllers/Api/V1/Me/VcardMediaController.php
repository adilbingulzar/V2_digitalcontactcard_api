<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Services\CardDesign\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pictures for the blocks of the user's card design (logo, cover, photos, gallery).
 * They only show on the card once the design that uses them is published.
 */
class VcardMediaController extends Controller
{
    public function store(Request $request, MediaLibrary $media): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120', 'dimensions:min_width=20,min_height=20'],
        ]);

        $path = $media->upload($request->user(), $request->file('image'));

        return response()->json(['data' => ['path' => $path, 'url' => Storage::disk('public')->url($path)]], 201);
    }
}
