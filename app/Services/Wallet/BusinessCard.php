<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Vcard;
use App\Models\VcardTheme;

/**
 * What a business card shows, in the shape every export needs: the .vcf
 * download and the Google and Apple wallet passes.
 */
final readonly class BusinessCard
{
    public function __construct(
        public int $userId,
        public string $name,
        public ?string $title,
        public ?string $company,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $country,
        public string $url,
        public ?string $photoUrl,
        public ?string $bannerUrl,
        public string $backgroundColor,
        public string $textColor,
    ) {}

    public static function for(User $user, Vcard $card, ?VcardTheme $theme): self
    {
        $palette = VcardTheme::paletteFor($card, $theme);

        return new self(
            userId: $user->id,
            name: $user->name,
            title: $card->business_title,
            company: $card->business_name,
            email: $card->business_email ?: $user->email,
            phone: filled($user->phone) ? trim('+'.ltrim((string) $user->country_code, '+').' '.$user->phone) : null,
            address: $card->show_location ? $card->business_address : null,
            country: $user->country,
            url: rtrim((string) config('tiqs.frontend_url'), '/').'/vcards/'.$user->slug,
            photoUrl: $user->avatarUrl(),
            bannerUrl: $card->bannerUrl(),
            backgroundColor: $palette['primary_background'],
            textColor: $palette['primary_text'],
        );
    }
}
