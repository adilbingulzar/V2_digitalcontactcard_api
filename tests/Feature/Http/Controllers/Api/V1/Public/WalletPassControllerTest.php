<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Public;

use App\Models\User;
use App\Models\Vcard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class WalletPassControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/wallet-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_google_link_carries_a_signed_pass_for_the_card(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $privatePem);
        file_put_contents($this->directory.'/google.json', json_encode(['client_email' => 'wallet@example.iam.gserviceaccount.com', 'private_key' => $privatePem]));
        config(['services.google_wallet.issuer_id' => '3388000000000000000', 'services.google_wallet.credentials' => $this->directory.'/google.json']);
        $user = User::factory()->create(['slug' => 'anna', 'name' => 'Anna']);
        Vcard::factory()->for($user)->create();

        $url = $this->getJson(route('api.v1.public.vcards.wallet.google', 'anna'))->assertOk()->json('data.url');

        $this->assertStringStartsWith('https://pay.google.com/gp/v/save/', $url);
        [$header, $claims, $signature] = explode('.', substr($url, strlen('https://pay.google.com/gp/v/save/')));
        $decode = fn (string $part): string => base64_decode(strtr($part, '-_', '+/'));
        $this->assertSame(1, openssl_verify($header.'.'.$claims, $decode($signature), openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256));
        $object = json_decode($decode($claims), true)['payload']['genericObjects'][0];
        $this->assertSame('3388000000000000000.vcard-'.$user->id, $object['id']);
        $this->assertSame('Anna', $object['header']['defaultValue']['value']);
        $this->assertSame(config('tiqs.frontend_url').'/vcards/anna', $object['barcode']['value']);
    }

    public function test_apple_pass_is_a_signed_zip_with_a_matching_manifest(): void
    {
        $this->configureAppleCertificates();
        $user = User::factory()->create(['slug' => 'anna', 'name' => 'Anna']);
        Vcard::factory()->for($user)->create(['business_title' => 'Owner']);

        $response = $this->get(route('api.v1.public.vcards.wallet.apple', 'anna'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.pkpass');

        file_put_contents($this->directory.'/card.pkpass', $response->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->directory.'/card.pkpass'));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        foreach ($manifest as $name => $hash) {
            $this->assertSame($hash, sha1($zip->getFromName($name)), "Hash of {$name}");
        }
        $pass = json_decode($zip->getFromName('pass.json'), true);
        $this->assertSame('pass.test.card', $pass['passTypeIdentifier']);
        $this->assertSame('vcard-'.$user->id, $pass['serialNumber']);
        $this->assertSame('Owner', $pass['generic']['secondaryFields'][0]['value']);
        $this->assertNotEmpty($zip->getFromName('signature'));
    }

    public function test_answers_unavailable_until_wallets_are_configured(): void
    {
        config(['services.google_wallet.issuer_id' => null, 'services.apple_wallet.certificate' => null]);
        Vcard::factory()->for(User::factory()->create(['slug' => 'anna']))->create();

        $this->getJson(route('api.v1.public.vcards.wallet.google', 'anna'))->assertServiceUnavailable();
        $this->getJson(route('api.v1.public.vcards.wallet.apple', 'anna'))->assertServiceUnavailable();
    }

    /**
     * A self-signed certificate stands in for both Apple's Pass Type ID certificate and the WWDR certificate.
     */
    private function configureAppleCertificates(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $certificate = openssl_csr_sign(openssl_csr_new(['commonName' => 'Pass Type ID: pass.test.card'], $key), null, $key, 1);
        openssl_pkcs12_export_to_file($certificate, $this->directory.'/pass.p12', $key, 'secret');
        openssl_x509_export_to_file($certificate, $this->directory.'/wwdr.pem');

        config([
            'services.apple_wallet.pass_type_id' => 'pass.test.card',
            'services.apple_wallet.team_id' => 'TEAM123456',
            'services.apple_wallet.certificate' => $this->directory.'/pass.p12',
            'services.apple_wallet.certificate_password' => 'secret',
            'services.apple_wallet.wwdr_certificate' => $this->directory.'/wwdr.pem',
        ]);
    }
}
