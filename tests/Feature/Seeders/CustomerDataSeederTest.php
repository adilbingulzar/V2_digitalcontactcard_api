<?php

namespace Tests\Feature\Seeders;

use App\Models\BankAccount;
use App\Models\Translation;
use App\Models\User;
use App\Services\SeedData\CustomerDataFile;
use Database\Seeders\CustomerDataSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class CustomerDataSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $dataFile;

    private string $translationsFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataFile = (string) tempnam(sys_get_temp_dir(), 'customer-data');
        $this->translationsFile = (string) tempnam(sys_get_temp_dir(), 'translations');
        config(['tiqs.seed_data' => ['key' => CustomerDataFile::generateKey(), 'path' => $this->dataFile, 'translations_path' => $this->translationsFile]]);
    }

    protected function tearDown(): void
    {
        @unlink($this->dataFile);
        @unlink($this->translationsFile);
        parent::tearDown();
    }

    public function test_exported_data_is_seeded_into_a_new_server_with_the_same_ids(): void
    {
        $user = User::factory()->create(['email' => 'lia@example.com', 'slug' => 'lia_auth01']);
        $bankAccount = BankAccount::factory()->for($user)->create(['account_number' => 'NL91ABNA0417164300']);
        Translation::factory()->create(['locale' => 'nl', 'key' => 'Hello', 'text' => 'Hallo']);

        $this->artisan('tiqs:export-seed-data')->assertSuccessful();

        $this->assertStringNotContainsString('lia@example.com', (string) file_get_contents($this->dataFile));
        $this->assertSame('Hallo', json_decode((string) file_get_contents($this->translationsFile), true)['nl']['Hello']['text']);

        // A new server: an empty database and its own APP_KEY.
        User::query()->forceDelete();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $this->seed(CustomerDataSeeder::class);

        $this->assertSame('lia_auth01', User::findOrFail($user->id)->slug);
        $this->assertSame('NL91ABNA0417164300', BankAccount::findOrFail($bankAccount->id)->account_number);
    }

    public function test_a_database_with_users_is_left_alone(): void
    {
        User::factory()->create();
        $this->artisan('tiqs:export-seed-data')->assertSuccessful();

        $this->seed(CustomerDataSeeder::class);

        $this->assertSame(1, User::count());
    }

    public function test_without_a_key_nothing_is_seeded(): void
    {
        User::factory()->create();
        $this->artisan('tiqs:export-seed-data')->assertSuccessful();
        User::query()->forceDelete();
        config(['tiqs.seed_data.key' => '']);

        $this->seed(CustomerDataSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_export_without_a_key_explains_how_to_add_one(): void
    {
        config(['tiqs.seed_data.key' => '']);

        $this->artisan('tiqs:export-seed-data')
            ->expectsOutputToContain('SEED_DATA_KEY=base64:')
            ->assertFailed();
    }
}
