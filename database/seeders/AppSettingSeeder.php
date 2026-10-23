<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Create the single platform settings row if it does not exist yet.
     * Existing values (set by admins or the legacy import) are never overwritten.
     */
    public function run(): void
    {
        AppSetting::current();
    }
}
