<?php

namespace Tests\Feature\Services\Ai;

use App\Services\Ai\WebsiteReader;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class WebsiteReaderTest extends TestCase
{
    /**
     * @param  array<string, list<string>>  $addresses  IPs per host name
     */
    private function reader(array $addresses): WebsiteReader
    {
        return $this->partialMock(WebsiteReader::class, fn (MockInterface $mock) => $mock->shouldReceive('addresses')->andReturnUsing(fn (string $host): array => $addresses[$host] ?? []));
    }

    public function test_finds_the_first_web_address_but_not_an_email_address(): void
    {
        $this->assertSame('cafeanna.nl', WebsiteReader::firstUrl('Please use cafeanna.nl for my card'));
        $this->assertSame('https://cafeanna.nl/about', WebsiteReader::firstUrl('See (https://cafeanna.nl/about).'));
        $this->assertNull(WebsiteReader::firstUrl('Mail me at anna@cafeanna.nl'));
        $this->assertNull(WebsiteReader::firstUrl('Make it green'));
    }

    public function test_reads_title_links_and_text_of_a_public_page(): void
    {
        Http::fake(['https://cafeanna.nl*' => Http::response(
            '<html><head><title>Cafe Anna</title><meta name="description" content="Coffee &amp; cake"><script>var secret = 1;</script></head>'
            .'<body><p>Best coffee in Amsterdam</p><a href="https://www.instagram.com/cafeanna">Insta</a><a href="tel:+31201234567">Call</a></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8'],
        )]);

        $text = $this->reader(['cafeanna.nl' => ['93.184.216.34']])->read('cafeanna.nl');

        $this->assertStringContainsString('Title: Cafe Anna', $text);
        $this->assertStringContainsString('Description: Coffee & cake', $text);
        $this->assertStringContainsString('https://www.instagram.com/cafeanna, tel:+31201234567', $text);
        $this->assertStringContainsString('Best coffee in Amsterdam', $text);
        $this->assertStringNotContainsString('secret', $text);
    }

    public function test_never_opens_private_addresses(): void
    {
        Http::fake();

        $reader = $this->reader(['intranet.test' => ['10.0.0.5'], 'mixed.test' => ['93.184.216.34', '127.0.0.1']]);

        $this->assertNull($reader->read('http://intranet.test/admin'));
        $this->assertNull($reader->read('mixed.test'));
        $this->assertNull($reader->read('http://169.254.169.254/latest/meta-data'));
        $this->assertNull($reader->read('https://cafeanna.nl:8080/'));
        Http::assertNothingSent();
    }

    public function test_checks_every_redirect_again(): void
    {
        Http::fake(['https://cafeanna.nl*' => Http::response('', 302, ['Location' => 'http://intranet.test/'])]);

        $this->assertNull($this->reader(['cafeanna.nl' => ['93.184.216.34'], 'intranet.test' => ['192.168.1.10']])->read('cafeanna.nl'));
        Http::assertSentCount(1);
    }

    public function test_says_when_a_page_has_no_logo_picture_and_reads_a_written_logo(): void
    {
        Http::fake(['https://tiqs.test*' => Http::response(
            '<html><header><a href="/" aria-label="Home"><span class="font-brand">tiqs<span>&copy;</span></span></a></header><img src="/team.jpg" alt="Our team"></html>',
            200,
            ['Content-Type' => 'text/html'],
        )]);

        $text = $this->reader(['tiqs.test' => ['93.184.216.34']])->read('tiqs.test');

        $this->assertStringContainsString('Logo candidates: none (no logo picture on this page', $text);
        $this->assertStringContainsString('Brand name as shown in the page header: tiqs©', $text);
        $this->assertStringContainsString('Other pictures: https://tiqs.test/team.jpg', $text);
    }
}
