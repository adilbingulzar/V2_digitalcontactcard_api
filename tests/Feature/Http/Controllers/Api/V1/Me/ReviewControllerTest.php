<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Me;

use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Models\Business;
use App\Models\Spot;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function review(array $attributes): Tip
    {
        return Tip::factory()->create([...$attributes, 'type' => TipType::Rating, 'review' => $attributes['review'] ?? 'Lovely service', 'amount' => 0]);
    }

    public function test_lists_own_written_reviews_pinned_first(): void
    {
        $user = User::factory()->create();
        $older = $this->review(['recipient_id' => $user->id, 'is_review_pinned' => true]);
        $newer = $this->review(['recipient_id' => $user->id]);
        $this->review(['recipient_id' => $user->id, 'review' => '']);
        Tip::factory()->create(['recipient_id' => $user->id, 'review' => 'Unpaid', 'payment_status' => PaymentStatus::Cancelled]);
        $this->review([]);
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.me.reviews.index'))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$older->id, $newer->id])
            ->assertJsonPath('meta.is_business', false);
    }

    public function test_business_owner_sees_and_filters_the_whole_business(): void
    {
        $owner = User::factory()->business()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        $spot = Spot::factory()->for($business)->create();
        $forMember = $this->review(['business_id' => $business->id, 'rating' => 5]);
        $forSpot = $this->review(['business_id' => $business->id, 'spot_id' => $spot->id, 'rating' => 2]);
        Sanctum::actingAs($owner);

        $this->getJson(route('api.v1.me.reviews.index'))->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(route('api.v1.me.reviews.index', ['source' => 'spot']))->assertJsonPath('data.*.id', [$forSpot->id]);
        $this->getJson(route('api.v1.me.reviews.index', ['rating' => 5]))->assertJsonPath('data.*.id', [$forMember->id]);
    }

    public function test_pins_only_reviews_the_user_may_see(): void
    {
        $user = User::factory()->create();
        $mine = $this->review(['recipient_id' => $user->id]);
        $someoneElses = $this->review([]);
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.me.reviews.pin', $mine), ['is_pinned' => true])->assertOk();
        $this->putJson(route('api.v1.me.reviews.pin', $someoneElses), ['is_pinned' => true])->assertNotFound();

        $this->assertTrue($mine->fresh()->is_review_pinned);
        $this->assertFalse($someoneElses->fresh()->is_review_pinned);
    }
}
