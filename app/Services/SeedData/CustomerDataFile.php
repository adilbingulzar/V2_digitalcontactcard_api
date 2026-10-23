<?php

namespace App\Services\SeedData;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The customer data (users, businesses, tips, business cards …) as one
 * encrypted file that is committed with the code, so a new server (Forge) can
 * be seeded without a copy of the old database. Written by
 * `php artisan tiqs:export-seed-data`, read by CustomerDataSeeder.
 *
 * The file is encrypted with SEED_DATA_KEY (AES-256-GCM). The key lives only
 * in the .env files, never in git.
 */
class CustomerDataFile
{
    private const string CIPHER = 'aes-256-gcm';

    /**
     * Tables that are not customer data: framework bookkeeping, and the
     * translations, which have their own seeder (TranslationSeeder).
     */
    public const array SKIPPED_TABLES = [
        'cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations',
        'password_reset_tokens', 'personal_access_tokens', 'sessions', 'translations',
    ];

    /**
     * Columns with Laravel's "encrypted" cast. They are encrypted with the
     * APP_KEY, which differs per server, so the file holds them as plain text
     * (inside its own encryption) and the seeder encrypts them again.
     */
    public const array ENCRYPTED_COLUMNS = [
        'app_settings' => ['payout_debtor_iban'],
        'bank_accounts' => ['account_number'],
    ];

    public function __construct(private readonly string $path, private readonly string $key) {}

    public static function fromConfig(): self
    {
        $key = (string) config('tiqs.seed_data.key');
        if ($key === '') {
            throw new RuntimeException('SEED_DATA_KEY is not set. Copy it from the .env of the machine that exported the data.');
        }

        return new self((string) config('tiqs.seed_data.path'), $key);
    }

    public static function exists(): bool
    {
        return is_file((string) config('tiqs.seed_data.path'));
    }

    /**
     * A new random key, in the "base64:" form used in .env files.
     */
    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey(self::CIPHER));
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables  Table name => rows
     */
    public function write(array $tables): void
    {
        $json = json_encode($tables, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->path, $this->encrypter()->encryptString(base64_encode((string) gzencode($json, 9))));
    }

    /**
     * @return array<string, list<array<string, mixed>>> Table name => rows
     */
    public function read(): array
    {
        $payload = $this->encrypter()->decryptString((string) file_get_contents($this->path));

        return json_decode((string) gzdecode(base64_decode($payload)), true, flags: JSON_THROW_ON_ERROR);
    }

    private function encrypter(): Encrypter
    {
        $key = Str::startsWith($this->key, 'base64:') ? base64_decode(Str::after($this->key, 'base64:')) : $this->key;

        return new Encrypter($key, self::CIPHER);
    }
}
