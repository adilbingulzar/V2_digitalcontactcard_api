<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The customer data (CustomerDataSeeder, needs SEED_DATA_KEY) and the
     * reference data are seeded in every environment. Demo accounts and sample
     * tips are only seeded locally and in testing, and only into a database
     * without customers.
     */
    public function run(): void
    {
        $this->call([
            // First, so its settings row is the one AppSettingSeeder finds.
            CustomerDataSeeder::class,
            AppSettingSeeder::class,
            TranslationSeeder::class,
        ]);
        // Website texts and their bundled translations (also run on every deploy).
        Artisan::call('tiqs:sync-ui-texts');

        if (app()->environment(['local', 'testing']) && ! User::withTrashed()->exists()) {
            $this->call(DemoSeeder::class);
        }
    }
}
