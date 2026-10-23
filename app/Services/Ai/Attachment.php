<?php

namespace App\Services\Ai;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A picture or PDF the user attached in the assistant chat: just sent, or
 * remembered from an earlier message, so the user can say "that is not the
 * same yet" without attaching it again.
 */
final readonly class Attachment
{
    public function __construct(
        public string $name,
        public string $mimeType,
        public string $bytes,
        public bool $remembered = false,
    ) {}

    public static function fromUpload(UploadedFile $file): self
    {
        return new self($file->getClientOriginalName(), (string) $file->getMimeType(), (string) $file->get());
    }

    /**
     * An attachment kept on the private disk, or null when the file is gone.
     */
    public static function fromStorage(string $path, string $name, string $mimeType): ?self
    {
        $bytes = Storage::disk('local')->get($path);

        return $bytes === null ? null : new self($name, $mimeType, $bytes, remembered: true);
    }

    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }
}
