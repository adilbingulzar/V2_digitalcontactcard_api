<?php

namespace App\Services\CardDesign;

use App\Actions\Images\StoreImage;
use App\Models\User;
use App\Services\Ai\WebsiteReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Pictures a user puts in their card design: uploads, chat attachments and
 * pictures fetched from a website the user pointed to. All are re-encoded as
 * WebP (see StoreImage) and kept in vcard-media/{user id}/.
 */
class MediaLibrary
{
    public const int MAX_FILES = 100;

    private const int MAX_DIMENSION = 1600;

    public function __construct(private StoreImage $storeImage, private WebsiteReader $websites) {}

    /**
     * @throws ValidationException
     */
    public function upload(User $user, UploadedFile $file): string
    {
        $this->ensureRoom($user);

        return $this->storeImage->handle($file, $this->folder($user), self::MAX_DIMENSION);
    }

    /**
     * A picture from bytes (a chat attachment), or null when it is not a usable picture or there is no room.
     */
    public function fromBytes(User $user, string $bytes): ?string
    {
        try {
            $this->ensureRoom($user);

            return $this->storeImage->fromBytes($bytes, $this->folder($user), self::MAX_DIMENSION);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * A picture from a public web address, or null when it cannot be fetched or is not a picture.
     */
    public function fromUrl(User $user, string $url): ?string
    {
        $image = $this->websites->image($url);

        return $image === null ? null : $this->fromBytes($user, $image['body']);
    }

    private function folder(User $user): string
    {
        return 'vcard-media/'.$user->id;
    }

    /**
     * @throws ValidationException
     */
    private function ensureRoom(User $user): void
    {
        if (count(Storage::disk('public')->files($this->folder($user))) >= self::MAX_FILES) {
            throw ValidationException::withMessages(['image' => 'You have uploaded the most pictures allowed. Remove some first.']);
        }
    }
}
