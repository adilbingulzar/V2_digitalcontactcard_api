<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrSettingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_qr_code_opens_the_review_page_like_the_printed_codes(): void
    {
        Sanctum::actingAs(User::factory()->create(['slug' => 'anna', 'name' => 'Anna']));

        $this->getJson(route('api.v1.me.qr.show'))
            ->assertOk()
            ->assertJsonPath('data.url', config('tiqs.frontend_url').'/review/anna')
            ->assertJsonPath('data.title', 'Anna');
    }

    public function test_saves_the_card_texts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(route('api.v1.me.qr.update'), ['header' => 'Tip me', 'title' => 'Thanks!'])
            ->assertOk()
            ->assertJsonPath('data.header', 'Tip me');

        $this->getJson(route('api.v1.me.qr.show'))->assertJsonPath('data.title', 'Thanks!');
    }
}
