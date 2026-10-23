<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    /**
     * The signed-in user's notifications, newest first, with the number still unread.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = UserNotification::query()->whereBelongsTo($request->user());

        return UserNotificationResource::collection((clone $notifications)->latest('id')->paginate(20))
            ->additional(['meta' => ['unread_count' => $notifications->unread()->count()]]);
    }

    public function read(Request $request, UserNotification $notification): UserNotificationResource
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return new UserNotificationResource($notification);
    }

    public function readAll(Request $request): Response
    {
        UserNotification::query()->whereBelongsTo($request->user())->unread()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
