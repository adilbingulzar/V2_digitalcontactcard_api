<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipExportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_downloads_own_tips_as_csv_without_other_users_tips(): void
    {
        $user = User::factory()->create();
        Tip::factory()->paid()->for($user, 'recipient')->create(['amount' => 4, 'review' => 'Lovely', 'reviewer_name' => 'Ana']);
        Tip::factory()->paid()->create(['reviewer_name' => 'Someone else']);
        Sanctum::actingAs($user);

        $response = $this->get(route('api.v1.me.tips.export', ['period' => 'all']));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString(',4.00,EUR,paid,no,', $csv);
        $this->assertStringContainsString(',Lovely,Ana,', $csv);
        $this->assertStringNotContainsString('Someone else', $csv);
    }

    public function test_neutralises_values_that_excel_would_run_as_formulas(): void
    {
        $user = User::factory()->create();
        Tip::factory()->rating()->for($user, 'recipient')->create(['review' => '=HYPERLINK("http://evil")']);
        Sanctum::actingAs($user);

        $csv = $this->get(route('api.v1.me.tips.export', ['period' => 'all']))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
