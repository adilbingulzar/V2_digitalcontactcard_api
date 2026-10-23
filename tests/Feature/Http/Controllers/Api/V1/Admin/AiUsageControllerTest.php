<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiUsageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sums_the_usage_of_each_user_in_a_month(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $anna = User::factory()->create(['name' => 'Anna', 'email' => 'anna@example.com']);
        $bob = User::factory()->create(['name' => 'Bob']);
        AiUsageLog::factory()->for($anna)->create(['input_tokens' => 1000, 'output_tokens' => 200, 'cost' => '0.002000', 'created_at' => '2026-10-05 10:00:00']);
        AiUsageLog::factory()->for($anna)->create(['input_tokens' => 3000, 'output_tokens' => 100, 'cost' => '0.003500', 'succeeded' => false, 'created_at' => '2026-10-20 10:00:00']);
        AiUsageLog::factory()->for($bob)->create(['input_tokens' => 500, 'output_tokens' => 50, 'cost' => null, 'created_at' => '2026-10-10 10:00:00']);
        AiUsageLog::factory()->for($anna)->create(['input_tokens' => 9999, 'created_at' => '2026-09-30 21:00:00']);

        $this->getJson(route('api.v1.admin.ai-usage.index', ['month' => '2026-10']))
            ->assertOk()
            ->assertJsonPath('meta.month', '2026-10')
            ->assertJsonPath('data.0.email', 'anna@example.com')
            ->assertJsonPath('data.0.requests', 2)
            ->assertJsonPath('data.0.failed', 1)
            ->assertJsonPath('data.0.input_tokens', 4000)
            ->assertJsonPath('data.0.output_tokens', 300)
            ->assertJsonPath('data.0.cost', '0.0055')
            ->assertJsonPath('data.1.name', 'Bob')
            ->assertJsonPath('data.1.unpriced', 1)
            ->assertJsonPath('meta.totals.requests', 3)
            ->assertJsonPath('meta.totals.input_tokens', 4500)
            ->assertJsonPath('meta.totals.cost', '0.0055')
            ->assertJsonPath('meta.totals.unpriced', 1);
    }

    public function test_exports_every_request_of_the_month(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $anna = User::factory()->create(['name' => '=Anna', 'email' => 'anna@example.com']);
        AiUsageLog::factory()->for($anna)->create(['created_at' => '2026-10-05 10:00:00']);

        $response = $this->get(route('api.v1.admin.ai-usage.export', ['month' => '2026-10']))->assertOk();

        $this->assertStringContainsString('ai-usage-2026-10.csv', (string) $response->headers->get('content-disposition'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Date,"User ID",Name,Email,Feature,Provider,Model', $csv);
        $this->assertStringContainsString("\"2026-10-05 12:00:00\",{$anna->id},'=Anna,anna@example.com,card_assistant,openai,gpt-6.1-sol,2000,300,1.0000,5.0000,0.003500,yes", $csv);
    }

    public function test_only_admins_see_the_usage(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.v1.admin.ai-usage.index'))->assertForbidden();
    }
}
