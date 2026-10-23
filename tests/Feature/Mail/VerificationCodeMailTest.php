<?php

namespace Tests\Feature\Mail;

use App\Mail\VerificationCodeMail;
use Tests\TestCase;

class VerificationCodeMailTest extends TestCase
{
    public function test_shows_the_code_and_its_lifetime(): void
    {
        $mail = new VerificationCodeMail('Anna', '042917', 15);

        $mail->assertSeeInHtml('042917');
        $mail->assertSeeInHtml('15 minutes');
    }

    public function test_escapes_html_in_the_user_name(): void
    {
        $html = (new VerificationCodeMail("O'Reilly <script>alert('xss')</script>", '123456', 15))->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }
}
