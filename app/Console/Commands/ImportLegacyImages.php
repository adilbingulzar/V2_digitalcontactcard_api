<?php

namespace App\Console\Commands;

use App\Actions\Images\StoreImage;
use App\Models\TipPageSetting;
use App\Models\User;
use App\Models\Vcard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Copies the images uploaded in the old app (profile photos, card logos and
 * banners, tip page backgrounds) into this app's storage, re-encoded like new
 * uploads, and points the records at the copies. Until then those images are
 * loaded from the old server (LEGACY_ASSETS_URL). Safe to run again: images
 * that were already copied are skipped.
 */
#[Signature('tiqs:import-images
    {--legacy-path= : Folder of the old app (the one that contains assets/images)}
    {--dry-run : Only report what would be copied}')]
#[Description('Copy the images uploaded in the old app into this app')]
class ImportLegacyImages extends Command
{
    /** @var array<string, array{copied: int, missing: int, unreadable: int}> */
    private array $report = [];

    /** @var list<string> */
    private array $problems = [];

    private string $imagesRoot = '';

    public function handle(StoreImage $storeImage): int
    {
        $root = rtrim((string) $this->option('legacy-path'), '/');
        if ($root === '' || ! is_dir($root.'/assets/images')) {
            $this->components->error('Pass --legacy-path: the folder of the old app, which contains assets/images.');

            return self::FAILURE;
        }
        $images = $root.'/assets/images';
        $this->imagesRoot = (string) realpath($images);

        User::query()->withTrashed()
            ->whereNotNull('avatar')->where('avatar', '!=', '')->where('avatar', '!=', 'user-image.jpg')
            ->where('avatar', 'not like', 'avatars/%')
            ->lazyById()
            ->each(fn (User $user) => $this->copy('profile photos', $user, 'avatar', $images.'/users/'.$user->avatar, 'avatars', 1024, $storeImage));

        Vcard::query()
            ->whereNotNull('logo')->where('logo', '!=', '')->where('logo', 'not like', 'vcard-logos/%')
            ->lazyById()
            ->each(fn (Vcard $card) => $this->copy('card logos', $card, 'logo', $images.'/business/'.$card->logo, 'vcard-logos', 600, $storeImage));

        Vcard::query()
            ->whereNotNull('banner')->where('banner', '!=', '')->where('banner', '!=', 'default.jpg')->where('banner', 'not like', 'vcard-banners/%')
            ->lazyById()
            ->each(fn (Vcard $card) => $this->copy('card banners', $card, 'banner', $images.'/vcard/'.$card->banner, 'vcard-banners', 1600, $storeImage));

        TipPageSetting::query()
            ->whereNotNull('background_image')->where('background_image', '!=', '')->where('background_image', 'not like', 'tip-backgrounds/%')
            ->lazyById()
            ->each(function (TipPageSetting $page) use ($images, $storeImage): void {
                // Stored with or without its folder ("pattern/…" or a bare name in pattern/).
                $candidates = [$images.'/'.$page->background_image, $images.'/pattern/'.$page->background_image];
                $file = collect($candidates)->first(fn (string $path): bool => is_file($path)) ?? $candidates[1];
                $this->copy('tip page backgrounds', $page, 'background_image', $file, 'tip-backgrounds', 1600, $storeImage);
            });

        $this->table(['Images', 'Copied', 'File missing', 'Not an image'], collect($this->report)->map(fn (array $counts, string $kind): array => [$kind, ...array_values($counts)])->values()->all());
        foreach (array_slice($this->problems, 0, 50) as $problem) {
            $this->line('  '.$problem);
        }
        if (count($this->problems) > 50) {
            $this->line('  … and '.(count($this->problems) - 50).' more.');
        }
        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was copied or changed.');
        }

        return self::SUCCESS;
    }

    private function copy(string $kind, Model $record, string $column, string $file, string $folder, int $maxDimension, StoreImage $storeImage): void
    {
        $this->report[$kind] ??= ['copied' => 0, 'missing' => 0, 'unreadable' => 0];
        $real = realpath($file);

        // The stored name comes from the old database; never read outside the old app's images folder.
        if ($real === false || ! str_starts_with($real, $this->imagesRoot.DIRECTORY_SEPARATOR) || ! is_file($real)) {
            $this->report[$kind]['missing']++;
            $this->problems[] = "{$kind}: ".class_basename($record)." {$record->getKey()} – file not found: ".basename($file);

            return;
        }

        if ($this->option('dry-run')) {
            $this->report[$kind]['copied']++;

            return;
        }

        try {
            $record->forceFill([$column => $storeImage->fromFile($real, $folder, $maxDimension)])->save();
            $this->report[$kind]['copied']++;
        } catch (ValidationException) {
            $this->report[$kind]['unreadable']++;
            $this->problems[] = "{$kind}: ".class_basename($record)." {$record->getKey()} – not a readable image: ".basename($file);
        }
    }
}
