<?php

namespace Tests\Feature\Seeders;

use App\Models\Translation;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TranslationSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeds_every_translation_in_the_data_file(): void
    {
        $this->seed(TranslationSeeder::class);

        $this->assertSame($this->entriesInDataFile(), Translation::count());
    }

    public function test_running_again_does_not_duplicate_translations(): void
    {
        $this->seed(TranslationSeeder::class);
        $this->seed(TranslationSeeder::class);

        $this->assertSame($this->entriesInDataFile(), Translation::count());
    }

    public function test_keys_that_differ_only_in_case_are_kept_apart(): void
    {
        Translation::factory()->create(['locale' => 'nl', 'key' => 'Email', 'text' => 'E-mail']);

        Translation::factory()->create(['locale' => 'nl', 'key' => 'email', 'text' => 'e-mailadres']);

        $this->assertSame(2, Translation::where('locale', 'nl')->whereIn('key', ['Email', 'email'])->count());
    }

    private function entriesInDataFile(): int
    {
        $locales = json_decode(file_get_contents(database_path('seeders/data/translations.json')), true);

        return array_sum(array_map('count', $locales));
    }
}
