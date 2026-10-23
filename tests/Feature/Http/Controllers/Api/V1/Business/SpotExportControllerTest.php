<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Business;

use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Spot;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpotExportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->business()->create();
        $this->business = Business::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);
    }

    public function test_lists_spots_with_their_qr_addresses(): void
    {
        Spot::factory()->for($this->business)->create(['name' => 'Terrace', 'slug' => 'terrace']);
        Spot::factory()->create(['name' => 'Someone else']);

        $csv = $this->get(route('api.v1.me.business.spots.export'))->assertOk()->assertDownload()->streamedContent();

        $this->assertStringContainsString('Terrace,'.config('tiqs.frontend_url').'/paytips/spot/'.$this->business->id.'/terrace', $csv);
        $this->assertStringNotContainsString('Someone else', $csv);
    }

    public function test_lists_paid_spot_tips_of_the_period(): void
    {
        $spot = Spot::factory()->for($this->business)->create(['name' => '=Bar']);
        Payment::factory()->create(['spot_id' => $spot->id, 'status' => PaymentStatus::Paid, 'tip_amount' => '7.50']);
        Payment::factory()->create(['spot_id' => $spot->id, 'status' => PaymentStatus::Cancelled, 'tip_amount' => '99.00']);
        Payment::factory()->create(['spot_id' => Spot::factory()->create()->id, 'status' => PaymentStatus::Paid, 'tip_amount' => '42.00']);

        $csv = $this->get(route('api.v1.me.business.spot-tips.export', ['period' => 'this_month']))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=Bar,7.50", $csv);
        $this->assertStringNotContainsString('99.00', $csv);
        $this->assertStringNotContainsString('42.00', $csv);
    }
}
