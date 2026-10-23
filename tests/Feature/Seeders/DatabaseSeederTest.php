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
        $this->seed();

        $this->assertSame(1, AppSetting::count());
        $this->assertTrue(User::where('email', 'admin@tiqs.test')->sole()->isAdmin());
        $this->assertTrue(User::where('email', 'business@tiqs.test')->sole()->isBusiness());
    }
}
