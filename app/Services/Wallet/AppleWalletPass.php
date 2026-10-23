<?php

namespace App\Services\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Builds an Apple Wallet pass (.pkpass) for a business card: a zip with
 * pass.json, images, a manifest of their SHA-1 hashes and a detached PKCS #7
 * signature of that manifest, made with the Pass Type ID certificate.
 */
class AppleWalletPass
{
    public function isConfigured(): bool
    {
        return filled(config('services.apple_wallet.pass_type_id'))
            && filled(config('services.apple_wallet.team_id'))
            && filled(config('services.apple_wallet.certificate'))
            && filled(config('services.apple_wallet.wwdr_certificate'));
    }

    /**
     * @return string The .pkpass file contents
     *
     * @throws WalletException
     */
    public function create(BusinessCard $card, User $user): string
    {
        [$certificate, $privateKey] = $this->certificate();

        $files = [
            'pass.json' => (string) json_encode($this->passData($card), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'icon.png' => (string) file_get_contents(resource_path('wallet/icon.png')),
            'icon@2x.png' => (string) file_get_contents(resource_path('wallet/icon@2x.png')),
            'logo.png' => (string) file_get_contents(resource_path('wallet/logo.png')),
        ];
        $thumbnail = $this->thumbnail($user);
        if ($thumbnail !== null) {
            $files['thumbnail.png'] = $thumbnail;
        }

        $files['manifest.json'] = (string) json_encode(array_map(fn (string $content): string => sha1($content), $files), JSON_UNESCAPED_SLASHES);
        $files['signature'] = $this->sign($files['manifest.json'], $certificate, $privateKey);

        return $this->zip($files);
    }

    /**
     * @return array<string, mixed>
     */
    private function passData(BusinessCard $card): array
    {
        $field = fn (string $key, ?string $label, ?string $value): ?array => filled($value) ? array_filter(['key' => $key, 'label' => $label, 'value' => $value]) : null;
        $fields = fn (array $list): array => array_values(array_filter($list));

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => (string) config('services.apple_wallet.pass_type_id'),
            'teamIdentifier' => (string) config('services.apple_wallet.team_id'),
            'serialNumber' => 'vcard-'.$card->userId,
            'organizationName' => $card->company ?: (string) config('app.name'),
            'description' => $card->name.' business card',
            'logoText' => $card->company ?: (string) config('app.name'),
            'backgroundColor' => $this->rgb($card->backgroundColor),
            'foregroundColor' => $this->rgb($card->textColor),
            'labelColor' => $this->rgb($card->textColor),
            'barcodes' => [['format' => 'PKBarcodeFormatQR', 'message' => $card->url, 'messageEncoding' => 'iso-8859-1', 'altText' => 'Scan to open my card']],
            'generic' => [
                'primaryFields' => $fields([$field('name', null, $card->name)]),
                'secondaryFields' => $fields([$field('title', 'Title', $card->title), $field('company', 'Company', $card->company)]),
                'auxiliaryFields' => $fields([$field('phone', 'Phone', $card->phone), $field('email', 'Email', $card->email)]),
                'backFields' => $fields([
                    $field('back_email', 'Email', $card->email),
                    $field('back_phone', 'Phone', $card->phone),
                    $field('address', 'Address', $card->address),
                    $field('card', 'Business card', $card->url),
                ]),
            ],
        ];
    }

    /**
     * @return array{0: string, 1: \OpenSSLAsymmetricKey}
     *
     * @throws WalletException
     */
    private function certificate(): array
    {
        if (! $this->isConfigured()) {
            throw new WalletException('Apple Wallet is not set up yet.');
        }

        $path = (string) config('services.apple_wallet.certificate');
        $certificates = [];
        if (! is_readable($path) || ! openssl_pkcs12_read((string) file_get_contents($path), $certificates, (string) config('services.apple_wallet.certificate_password'))) {
            throw new WalletException('The Apple Wallet certificate cannot be read. Check the file and its password.');
        }

        $privateKey = openssl_pkey_get_private($certificates['pkey']);
        if ($privateKey === false) {
            throw new WalletException('The Apple Wallet certificate has no usable private key.');
        }

        return [$certificates['cert'], $privateKey];
    }

    /**
     * Apple wants the signature as DER; openssl_pkcs7_sign() writes S/MIME, so the
     * base64 body of the smime.p7s part is taken out and decoded.
     *
     * @throws WalletException
     */
    private function sign(string $manifest, string $certificate, \OpenSSLAsymmetricKey $privateKey): string
    {
        $directory = storage_path('app/private/wallet-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($directory);

        try {
            file_put_contents($directory.'/manifest.json', $manifest);
            $signed = openssl_pkcs7_sign(
                $directory.'/manifest.json',
                $directory.'/signature',
                $certificate,
                $privateKey,
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                (string) config('services.apple_wallet.wwdr_certificate'),
            );

            $smime = $signed ? (string) file_get_contents($directory.'/signature') : '';
            if (preg_match('/filename="smime\.p7s"\R\R(.+?)\R\R/s', $smime, $match) !== 1) {
                throw new WalletException('The Apple Wallet pass could not be signed.');
            }

            return (string) base64_decode($match[1], true);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkpass');

        try {
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::OVERWRITE);
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * The profile photo as a square PNG, when it was uploaded in the new app.
     */
    private function thumbnail(User $user): ?string
    {
        if (blank($user->avatar) || ! str_starts_with($user->avatar, 'avatars/') || ! Storage::disk('public')->exists($user->avatar)) {
            return null;
        }

        $image = @imagecreatefromstring((string) Storage::disk('public')->get($user->avatar));
        if ($image === false) {
            return null;
        }

        $size = min(imagesx($image), imagesy($image));
        $square = imagecreatetruecolor(180, 180);
        imagecopyresampled($square, $image, 0, 0, intdiv(imagesx($image) - $size, 2), intdiv(imagesy($image) - $size, 2), 180, 180, $size, $size);

        ob_start();
        imagepng($square);

        return (string) ob_get_clean();
    }

    private function rgb(string $hex): string
    {
        [$red, $green, $blue] = sscanf($hex, '#%02x%02x%02x');

        return "rgb({$red}, {$green}, {$blue})";
    }
}
