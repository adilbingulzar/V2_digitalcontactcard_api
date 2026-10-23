<?php

namespace App\Services\SeedData;

/**
 * The customer data (users, businesses, tips, business cards …) as one JSON
 * file, so a new server (Forge) can be seeded without a copy of the old
 * database. Written by `php artisan tiqs:export-seed-data`, read by
 * CustomerDataSeeder.
 *
 * The file is plain text and holds personal data, so it is not in git: it is
 * uploaded to the server by hand.
 */
class CustomerDataFile
{
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
     * and the seeder encrypts them again.
     */
    public const array ENCRYPTED_COLUMNS = [
        'app_settings' => ['payout_debtor_iban'],
        'bank_accounts' => ['account_number'],
    ];

    public function __construct(private readonly string $path) {}

    public static function fromConfig(): self
    {
        return new self((string) config('tiqs.seed_data.path'));
    }

    public static function exists(): bool
    {
        return is_file((string) config('tiqs.seed_data.path'));
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables  Table name => rows
     */
    public function write(array $tables): void
    {
        file_put_contents($this->path, json_encode($tables, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, list<array<string, mixed>>> Table name => rows
     */
    public function read(): array
    {
        return json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
    }
}
