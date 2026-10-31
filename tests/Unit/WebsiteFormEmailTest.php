<?php

namespace Tests\Unit;

use App\Support\WebsiteFormEmail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebsiteFormEmailTest extends TestCase
{
    #[Test]
    public function it_identifies_the_exact_legacy_site_form_notification(): void
    {
        $this->assertTrue(WebsiteFormEmail::isNotification(
            'Nouveau message depuis le site',
            "Nouveau message reçu depuis le site.\n\nNom : Jeanne Martin\nEmail : jeanne@example.test\nMessage : Bonjour",
        ));
    }

    #[Test]
    public function it_never_classifies_a_reply_or_a_partial_match_as_a_site_form(): void
    {
        $body = "Nouveau message reçu depuis le site.\n\nMessage : Bonjour";

        $this->assertFalse(WebsiteFormEmail::isNotification('Re: Nouveau message depuis le site', $body));
        $this->assertFalse(WebsiteFormEmail::isNotification('Nouveau message depuis le site', 'Bonjour'));
    }
}
