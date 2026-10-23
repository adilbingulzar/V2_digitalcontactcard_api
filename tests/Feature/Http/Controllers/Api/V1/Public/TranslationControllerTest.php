<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Jobs\TranslateMissingTexts;
use App\Models\Translation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TranslationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_languages_with_their_writing_direction(): void
    {
        $response = $this->getJson(route('api.v1.public.locales'))->assertOk();

        $languages = collect($response->json('data'))->keyBy('code');
        $this->assertSame('ltr', $languages['nl']['dir']);
        $this->assertSame('rtl', $languages['ar']['dir']);
        $this->assertSame('Nederlands', $languages['nl']['name']);
    }

    public function test_returns_only_translated_texts_of_a_language(): void
    {
        Translation::factory()->create(['locale' => 'en', 'key' => 'Hello', 'text' => 'Hello']);
        Translation::factory()->create(['locale' => 'nl', 'key' => 'Hello', 'text' => 'Hallo']);
        Translation::factory()->create(['locale' => 'nl', 'key' => 'Empty', 'text' => '']);

        $this->getJson(route('api.v1.public.translations.show', 'nl'))
            ->assertOk()
            ->assertJsonPath('data.dir', 'ltr')
            ->assertExactJson(['data' => ['locale' => 'nl', 'dir' => 'ltr', 'texts' => ['Hello' => 'Hallo']]]);

        $this->getJson(route('api.v1.public.translations.show', 'xx'))->assertNotFound();
    }

    public function test_missing_texts_are_translated_in_the_background_when_google_is_set_up(): void
    {
        Queue::fake();
        Translation::factory()->create(['locale' => 'en', 'key' => 'Hello', 'text' => 'Hello']);

        $this->getJson(route('api.v1.public.translations.show', 'de'))->assertOk();
        Queue::assertNothingPushed();

        config(['services.google_translate.key' => 'key']);
        $this->getJson(route('api.v1.public.translations.show', 'de'))->assertOk();
        Queue::assertPushed(TranslateMissingTexts::class, fn (TranslateMissingTexts $job): bool => $job->locale === 'de');
    }

    public function test_the_job_keeps_placeholders_and_never_overwrites(): void
    {
        config(['services.google_translate.key' => 'key']);
        Translation::factory()->create(['locale' => 'en', 'key' => 'Hi {name}', 'text' => 'Hi {name}']);
        Translation::factory()->create(['locale' => 'en', 'key' => 'Thanks', 'text' => 'Thanks']);
        Translation::factory()->create(['locale' => 'de', 'key' => 'Thanks', 'text' => 'Danke (admin)']);
        Http::fake(['translation.googleapis.com/*' => Http::response(['data' => ['translations' => [['translatedText' => 'Hallo <x id="name"/>']]]])]);

        (new TranslateMissingTexts('de'))->handle();

        $this->assertSame('Hallo {name}', Translation::where('locale', 'de')->where('key', 'Hi {name}')->value('text'));
        $this->assertSame('Danke (admin)', Translation::where('locale', 'de')->where('key', 'Thanks')->value('text'));
        Http::assertSent(fn ($request): bool => $request['target'] === 'de' && $request['q'] === ['Hi <x id="name"/>']);
    }

    public function test_syncing_website_texts_adds_missing_ones_only(): void
    {
        Translation::factory()->create(['locale' => 'nl', 'key' => 'Pay Tip', 'text' => 'Betaal (aangepast)']);

        $this->artisan('tiqs:sync-ui-texts')->assertSuccessful();

        $this->assertTrue(Translation::where('locale', 'en')->where('key', 'Pay Tip')->exists());
        $this->assertSame('Betaal (aangepast)', Translation::where('locale', 'nl')->where('key', 'Pay Tip')->value('text'));
        $this->assertSame('Kies je fooi', Translation::where('locale', 'nl')->where('key', 'Select your tip')->value('text'));
    }
}
