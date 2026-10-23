<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Enums\TipType;
use App\Models\NotificationPreference;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileReviewControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_saves_a_rating_and_review_without_payment(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);

        $this->postJson(route('api.v1.public.profiles.reviews.store', 'anna'), [
            'rating' => 5, 'review' => 'Lovely', 'name' => 'Guest',
        ])->assertCreated();

        $tip = Tip::sole();
        $this->assertSame(TipType::Rating, $tip->type);
        $this->assertSame($user->id, $tip->recipient_id);
        $this->assertSame('0.00', $tip->amount);
        $this->assertSame('Lovely', $tip->review);
    }

    public function test_notifies_the_user_about_ratings_and_reviews_by_default(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);

        $this->postJson(route('api.v1.public.profiles.reviews.store', 'anna'), ['rating' => 4, 'review' => 'Nice'])->assertCreated();

        $this->assertEqualsCanonicalizing(['service_rating', 'service_review'], UserNotification::whereBelongsTo($user)->pluck('type')->all());
    }

    public function test_respects_switched_off_notifications_and_star_filters(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        NotificationPreference::factory()->for($user)->create(['event' => 'service_review', 'is_enabled' => false]);
        NotificationPreference::factory()->for($user)->create(['event' => 'service_rating', 'channel' => '1,2']);

        $this->postJson(route('api.v1.public.profiles.reviews.store', 'anna'), ['rating' => 4, 'review' => 'Nice'])->assertCreated();
        $this->assertSame(0, UserNotification::whereBelongsTo($user)->count());

        $this->postJson(route('api.v1.public.profiles.reviews.store', 'anna'), ['rating' => 2])->assertCreated();
        $this->assertSame(['service_rating'], UserNotification::whereBelongsTo($user)->pluck('type')->all());
    }

    public function test_rating_must_be_one_to_five_stars(): void
    {
        User::factory()->create(['slug' => 'anna']);

        $this->postJson(route('api.v1.public.profiles.reviews.store', 'anna'), ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating' => 'The rating field must be between 1 and 5.']);
    }
}
