<?php

namespace App\Console\Commands;

use App\Actions\Images\StoreImage;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * For testing the card builder: puts a user's business card back to before
 * their first visit, so "Do you already have a business card?" shows again.
 * Removes the card, its colours, unpublished changes, the assistant chat with
 * its attachments, and the design pictures. The account, profile (name,
 * photo), tips and reviews stay. Never runs in production.
 */
#[Signature('tiqs:reset-card {user : The email address, URL name (slug) or id of the account} {--force : Do not ask for confirmation}')]
#[Description('Testing only: remove a user\'s business card, card draft and assistant chat')]
class ResetCard extends Command
{
    public function handle(StoreImage $storeImage): int
    {
        if (app()->isProduction()) {
            $this->error('This command only runs outside production.');

            return self::FAILURE;
        }

        $value = (string) $this->argument('user');
        $users = User::query()
            ->where(fn ($query) => $query->where('email', strtolower($value))->orWhere('slug', $value)->when(ctype_digit($value), fn ($query) => $query->orWhere('id', (int) $value)))
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->error("No account found for \"{$value}\".");

            return self::FAILURE;
        }
        if ($users->count() > 1) {
            $this->error('More than one account uses this email address. Use the id or URL name instead:');
            $this->table(['Id', 'URL name', 'Name'], $users->map(fn (User $user): array => [$user->id, $user->slug, $user->name])->all());

            return self::FAILURE;
        }

        /** @var User $user */
        $user = $users->first();
        if (! $this->option('force') && ! $this->confirm("Remove the business card, draft and assistant chat of {$user->name} ({$user->email}, /vcards/{$user->slug})?")) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $storeImage): void {
            if ($card = $user->vcard) {
                $storeImage->delete($card->logo);
                $storeImage->delete($card->banner);
                $card->delete();
            }
            $user->vcardTheme()->delete();
            $user->vcardDraft()->delete();
            $user->vcardAssistantMessages()->delete();
        });
        Storage::disk('local')->deleteDirectory('assistant-attachments/'.$user->id);
        Storage::disk('public')->deleteDirectory('vcard-media/'.$user->id);

        $this->info("The card of {$user->name} is reset. Open Card Settings to start again.");

        return self::SUCCESS;
    }
}
