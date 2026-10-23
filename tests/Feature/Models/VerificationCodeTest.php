<?php

namespace Tests\Feature\Models;

use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fresh_code_is_usable(): void
    {
        $code = VerificationCode::factory()->make();

        $this->assertTrue($code->isUsable());
    }

    public function test_expired_code_is_not_usable(): void
    {
        $code = VerificationCode::factory()->make(['expires_at' => now()->subSecond()]);

        $this->assertFalse($code->isUsable());
    }

    public function test_code_is_not_usable_after_maximum_attempts(): void
    {
        $code = VerificationCode::factory()->make(['attempts' => VerificationCode::MAX_ATTEMPTS]);

        $this->assertFalse($code->isUsable());
    }

    public function test_serialized_code_does_not_expose_hash(): void
    {
        $code = VerificationCode::factory()->create();

        $this->assertArrayNotHasKey('code_hash', $code->toArray());
    }
}
