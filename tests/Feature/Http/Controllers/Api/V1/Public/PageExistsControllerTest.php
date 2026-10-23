<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Spot;
use App\Models\User;
use App\Models\Vcard;
use App\Models\VisitorLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PageExistsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_answers_whether_public_pages_exist_without_logging_a_visit(): void
    {
        $user = User::factory()->create(['slug' => 'anna']);
        Vcard::factory()->for($user)->create(['is_active' => false]);
        $spot = Spot::factory()->create(['slug' => 'bar']);
        $payment = Payment::factory()->create();
        $closed = Business::factory()->create(['is_active' => false]);
        $exists = fn (array $query) => $this->get(route('api.v1.public.page-exists', $query))->status();

        $this->assertSame(204, $exists(['type' => 'profile', 'key' => 'anna']));
        $this->assertSame(404, $exists(['type' => 'profile', 'key' => 'nobody']));
        $this->assertSame(404, $exists(['type' => 'card', 'key' => 'anna']));
        $this->assertSame(204, $exists(['type' => 'spot', 'key' => 'bar', 'business' => (string) $spot->business_id]));
        $this->assertSame(404, $exists(['type' => 'spot', 'key' => 'bar', 'business' => 'x']));
        $this->assertSame(204, $exists(['type' => 'payment', 'key' => $payment->uuid]));
        $this->assertSame(204, $exists(['type' => 'widget', 'key' => (string) $spot->business_id]));
        $this->assertSame(404, $exists(['type' => 'widget', 'key' => (string) $closed->id]));
        $this->assertSame(0, VisitorLog::count());
    }
}
