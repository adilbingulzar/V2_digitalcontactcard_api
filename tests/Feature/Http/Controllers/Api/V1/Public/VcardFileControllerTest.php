<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\User;
use App\Models\Vcard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VcardFileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_downloads_the_card_as_a_contact_file(): void
    {
        $user = User::factory()->create(['slug' => 'anna', 'name' => 'Anna de Vries', 'country_code' => '31', 'phone' => '612345678']);
        Vcard::factory()->for($user)->create(['business_name' => 'Cafe; Anna, BV', 'business_title' => 'Owner', 'business_email' => 'hi@anna.test']);

        $response = $this->get(route('api.v1.public.vcards.vcf', 'anna'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/vcard; charset=utf-8')
            ->assertDownload('anna-de-vries.vcf');

        $vcf = $response->getContent();
        $this->assertStringContainsString("N:de Vries;Anna;;;\r\n", $vcf);
        $this->assertStringContainsString("ORG:Cafe\; Anna\\, BV\r\n", $vcf);
        $this->assertStringContainsString("TEL;TYPE=WORK,VOICE:+31 612345678\r\n", $vcf);
        $this->assertStringContainsString('URL:'.config('tiqs.frontend_url').'/vcards/anna', $vcf);
    }
}
