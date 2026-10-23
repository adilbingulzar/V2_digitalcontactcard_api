<?php

namespace App\Console\Commands;

use App\Services\SeedData\CustomerDataFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes the current database to the seed files, so `php artisan db:seed` on
 * a new server (Forge) recreates it:
 *
 * - database/seeders/data/customer-data.enc: every customer table, encrypted
 *   with SEED_DATA_KEY (CustomerDataSeeder)
 * - database/seeders/data/translations.json: every translation (TranslationSeeder)
 *
 * Run it after the data changed here (for example after tiqs:import-legacy), then commit both files.
 */
#[Signature('tiqs:export-seed-data')]
#[Description('Write the current customer data and translations to the seed files')]
class ExportSeedData extends Command
{
    public function handle(): int
    {
        if ((string) config('tiqs.seed_data.key') === '') {
            $this->components->error('SEED_DATA_KEY is not set. Add this line to .env (and later to the server), then run again:');
            $this->line('SEED_DATA_KEY='.CustomerDataFile::generateKey());

            return self::FAILURE;
        }

        $tables = [];
        $listing = Schema::getTableListing(schema: DB::connection()->getDatabaseName(), schemaQualified: false);
        foreach (array_diff($listing, CustomerDataFile::SKIPPED_TABLES) as $table) {
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'id')) {
                $query->orderBy('id');
            }

            $rows = $query->get()->map(fn (object $row): array => $this->decrypted($table, (array) $row))->all();
            if ($rows !== []) {
                $tables[$table] = $rows;
            }
        }
        ksort($tables);

        CustomerDataFile::fromConfig()->write($tables);
        $translations = $this->writeTranslations();

        $this->table(['Table', 'Rows'], collect($tables)->map(fn (array $rows, string $table): array => [$table, count($rows)])->values()->all());
        $this->components->info(sprintf('Customer data written to database/seeders/data/customer-data.enc; %d translations written to translations.json.', $translations));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function decrypted(string $table, array $row): array
    {
        foreach (CustomerDataFile::ENCRYPTED_COLUMNS[$table] ?? [] as $column) {
            if ($row[$column] !== null) {
                $row[$column] = Crypt::decryptString($row[$column]);
            }
        }

        return $row;
    }

    private function writeTranslations(): int
    {
        $locales = [];
        foreach (DB::table('translations')->orderBy('id')->get(['locale', 'key', 'text', 'group']) as $translation) {
            $locales[$translation->locale][$translation->key] = ['text' => $translation->text, 'group' => $translation->group];
        }

        ksort($locales, SORT_STRING);
        foreach ($locales as &$entries) {
            ksort($entries, SORT_STRING);
        }
        unset($entries);

        $json = json_encode($locales, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents((string) config('tiqs.seed_data.translations_path'), $json."\n");

        return array_sum(array_map('count', $locales));
    }
}
