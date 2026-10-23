<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\SeedData\CustomerDataFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills a new database with the customer data from the file made by
 * `php artisan tiqs:export-seed-data` (users, businesses, tips, business cards …),
 * with the same ids, so printed QR codes keep working.
 *
 * Does nothing without the file, or when the database already has users, so
 * running `db:seed` again never duplicates or overwrites customer data.
 */
class CustomerDataSeeder extends Seeder
{
    private const int CHUNK = 500;

    public function run(): void
    {
        if (! CustomerDataFile::exists()) {
            $this->command?->warn('Customer data skipped: database/seeders/data/customer-data.json is missing.');

            return;
        }

        if (User::withTrashed()->exists()) {
            $this->command?->warn('Customer data skipped: the database already has users.');

            return;
        }

        $tables = CustomerDataFile::fromConfig()->read();

        Schema::withoutForeignKeyConstraints(function () use ($tables): void {
            DB::transaction(function () use ($tables): void {
                foreach ($tables as $table => $rows) {
                    $this->insert($table, $rows);
                }
            });
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        // Columns removed by a later migration are left out; new columns get their default.
        $columns = array_flip(Schema::getColumnListing($table));
        $encrypted = CustomerDataFile::ENCRYPTED_COLUMNS[$table] ?? [];

        $rows = array_map(function (array $row) use ($columns, $encrypted): array {
            foreach ($encrypted as $column) {
                if (isset($row[$column])) {
                    $row[$column] = Crypt::encryptString($row[$column]);
                }
            }

            return array_intersect_key($row, $columns);
        }, $rows);

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
