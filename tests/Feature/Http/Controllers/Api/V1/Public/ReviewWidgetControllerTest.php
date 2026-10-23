<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Models\Business;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewWidgetControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shows_the_business_rating_and_reviews_pinned_first(): void
    {
        $business = Business::factory()->create(['name' => 'Cafe Anna']);
        $newer = Tip::factory()->create(['business_id' => $business->id, 'type' => TipType::Rating, 'rating' => 4, 'review' => 'Nice']);
        $pinned = Tip::factory()->create(['business_id' => $business->id, 'type' => TipType::Rating, 'rating' => 2, 'review' => 'Old but pinned', 'is_review_pinned' => true]);
        Tip::factory()->create(['business_id' => $business->id, 'review' => 'Never paid', 'payment_status' => PaymentStatus::Cancelled]);
        Tip::factory()->create(['type' => TipType::Rating, 'review' => 'Other business']);

        $this->getJson(route('api.v1.public.widgets.review', $business))
            ->assertOk()
            ->assertJsonPath('data.business.name', 'Cafe Anna')
            ->assertJsonPath('data.rating.average', 3)
            ->assertJsonPath('data.theme', 'light')
            ->assertJsonPath('data.reviews.*.id', [$pinned->id, $newer->id]);
    }

    public function test_closed_businesses_have_no_widget(): void
    {
        $this->getJson(route('api.v1.public.widgets.review', Business::factory()->create(['is_active' => false])))->assertNotFound();
    }

    public function test_owner_saves_widget_settings(): void
    {
        $owner = User::factory()->business()->create();
        $business = Business::factory()->for($owner, 'owner')->create();
        Sanctum::actingAs($owner);

        $this->putJson(route('api.v1.me.business.widget.update'), ['frame' => 'full', 'width' => 400, 'height' => 600, 'dark' => true, 'scrollable' => false])
            ->assertOk()
            ->assertJsonPath('data.width', 100)
            ->assertJsonPath('data.dark', true)
            ->assertJsonPath('data.business_id', $business->id);

        $this->getJson(route('api.v1.public.widgets.review', $business))->assertJsonPath('data.theme', 'dark');
    }
}
