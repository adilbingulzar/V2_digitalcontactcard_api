<?php

namespace Tests\Feature\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_one_settings_row_and_demo_accounts_in_testing(): void
    {
        // Keep the real customer data file (if this machine has one) out of the test database.
        config(['tiqs.seed_data.path' => sys_get_temp_dir().'/missing-customer-data.json']);

        $this->seed();

        $this->assertSame(1, AppSetting::count());
        $this->assertTrue(User::where('email', 'admin@tiqs.test')->sole()->isAdmin());
        $this->assertTrue(User::where('email', 'business@tiqs.test')->sole()->isBusiness());
    }
}
