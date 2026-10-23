<?php

namespace App\Services\Wallet;

/**
 * Builds an "Add to Google Wallet" link for a business card.
 *
 * The pass travels inside a JWT signed with the service account key, so no
 * call to Google's API is needed: Google creates the pass when the link is
 * opened. Saving again later replaces the pass with the current details.
 */
class GoogleWalletPass
{
    public function isConfigured(): bool
    {
        return filled(config('services.google_wallet.issuer_id')) && filled(config('services.google_wallet.credentials'));
    }

    /**
     * @throws WalletException
     */
    public function saveUrl(BusinessCard $card): string
    {
        $account = $this->serviceAccount();
        $issuerId = (string) config('services.google_wallet.issuer_id');
        $frontendUrl = rtrim((string) config('tiqs.frontend_url'), '/');

        $claims = [
            'iss' => $account['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'origins' => [$frontendUrl],
            'payload' => [
                'genericClasses' => [['id' => $issuerId.'.business_card']],
                'genericObjects' => [$this->passObject($issuerId, $card, $frontendUrl)],
            ],
        ];

        return 'https://pay.google.com/gp/v/save/'.$this->sign($claims, $account['private_key']);
    }

    /**
     * @return array<string, mixed>
     */
    private function passObject(string $issuerId, BusinessCard $card, string $frontendUrl): array
    {
        $text = fn (string $value): array => ['defaultValue' => ['language' => 'en', 'value' => $value]];
        $image = fn (string $uri, string $description): array => ['sourceUri' => ['uri' => $uri], 'contentDescription' => $text($description)];

        $details = array_values(array_filter([
            $card->email ? ['id' => 'email', 'header' => 'Email', 'body' => $card->email] : null,
            $card->phone ? ['id' => 'phone', 'header' => 'Phone', 'body' => $card->phone] : null,
            $card->address ? ['id' => 'address', 'header' => 'Address', 'body' => $card->address] : null,
        ]));

        $links = array_values(array_filter([
            ['id' => 'card', 'uri' => $card->url, 'description' => 'Business card'],
            $card->phone ? ['id' => 'call', 'uri' => 'tel:'.str_replace(' ', '', $card->phone), 'description' => 'Call'] : null,
            $card->address ? ['id' => 'map', 'uri' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($card->address), 'description' => 'Directions'] : null,
        ]));

        return array_filter([
            'id' => $issuerId.'.vcard-'.$card->userId,
            'classId' => $issuerId.'.business_card',
            'state' => 'ACTIVE',
            'cardTitle' => $text($card->company ?: (string) config('app.name')),
            'header' => $text($card->name),
            'subheader' => $card->title ? $text($card->title) : null,
            'hexBackgroundColor' => $card->backgroundColor,
            'logo' => $image($frontendUrl.'/images/wallet-logo.png', (string) config('app.name')),
            'heroImage' => $card->bannerUrl ? $image($card->bannerUrl, 'Banner') : null,
            'barcode' => ['type' => 'QR_CODE', 'value' => $card->url, 'alternateText' => 'Scan to open my card'],
            'textModulesData' => $details ?: null,
            'linksModuleData' => ['uris' => $links],
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{client_email: string, private_key: string}
     *
     * @throws WalletException
     */
    private function serviceAccount(): array
    {
        if (! $this->isConfigured()) {
            throw new WalletException('Google Wallet is not set up yet.');
        }

        $path = (string) config('services.google_wallet.credentials');
        $account = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($account) || ! is_string($account['client_email'] ?? null) || ! is_string($account['private_key'] ?? null)) {
            throw new WalletException('The Google Wallet service account key cannot be read.');
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $claims
     *
     * @throws WalletException
     */
    private function sign(array $claims, string $privateKey): string
    {
        $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsigned = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new WalletException('The Google Wallet pass could not be signed.');
        }

        return $unsigned.'.'.$encode($signature);
    }
}
