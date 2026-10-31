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

    #[Test]
    public function it_extracts_form_fields_from_the_legacy_notification(): void
    {
        $form = WebsiteFormEmail::parse('Nouveau message depuis le site', <<<'TEXT'
Nouveau message reçu depuis le site.

Nom : Camille Martin
Email : camille@example.test
Téléphone : 06 12 34 56 78
Sujet : Réparation d’un alto
Message :
Je souhaiterais prendre rendez-vous.
TEXT);

        $this->assertSame([
            'name' => 'Camille Martin',
            'email' => 'camille@example.test',
            'phone' => '06 12 34 56 78',
            'subject' => 'Réparation d’un alto',
            'message' => 'Je souhaiterais prendre rendez-vous.',
        ], $form);
    }
}
