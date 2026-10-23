<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TermsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_the_terms_with_only_simple_formatting(): void
    {
        AppSetting::current()->forceFill([
            'terms_and_conditions' => '<p style="color:red" onclick="steal()">Read <strong>this</strong></p><script>alert(1)</script><ol><li><a href="javascript:alert(1)">bad</a> <a href="https://tiqs.com">site</a></li></ol>',
        ])->save();

        $this->getJson(route('api.v1.public.terms.show'))
            ->assertOk()
            ->assertJsonPath('data.html', '<p>Read <strong>this</strong></p><ol><li><a>bad</a> <a href="https://tiqs.com" rel="noopener noreferrer">site</a></li></ol>');
    }
}
