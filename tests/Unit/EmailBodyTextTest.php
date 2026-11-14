<?php

namespace Tests\Unit;

use App\Support\EmailBodyText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailBodyTextTest extends TestCase
{
    #[Test]
    public function it_decodes_quoted_printable_html_into_readable_text(): void
    {
        $body = EmailBodyText::forDisplay('<p>Aides de l&rsquo;État&nbsp;: financez votre pompe =C3=A0 chaleur jusqu&rsquo;à 100%.</p><p>En savoir plus</p>');

        $this->assertSame("Aides de l’État : financez votre pompe à chaleur jusqu’à 100%.\nEn savoir plus", $body);
    }

    #[Test]
    public function it_removes_html_that_was_escaped_inside_a_text_part(): void
    {
        $body = EmailBodyText::forDisplay('&lt;p&gt;Bonjour&nbsp;!&lt;/p&gt;&lt;p&gt;Deuxième ligne&lt;/p&gt;');

        $this->assertSame("Bonjour !\nDeuxième ligne", $body);
    }

    #[Test]
    public function it_removes_css_leaked_into_a_plain_text_part(): void
    {
        $body = EmailBodyText::forDisplay(<<<'TEXT'
Votre pompe à chaleur
body, table, td, a { -webkit-text-size-adjust: 100%; }
@media only screen and (max-width: 650px) {
  .container { width: 100% !important; }
}
VOTRE FACTURE DE CHAUFFAGE VA EXPLOSER
L’État finance jusqu’à 100 % de l’installation.
TEXT);

        $this->assertSame("Votre pompe à chaleur\nVOTRE FACTURE DE CHAUFFAGE VA EXPLOSER\nL’État finance jusqu’à 100 % de l’installation.", $body);
    }

    #[Test]
    public function it_compacts_blank_lines_left_by_table_based_email_layouts(): void
    {
        $body = EmailBodyText::forDisplay("Titre\n \n\t\n\n\nPremier paragraphe\n\n  \n\nSecond paragraphe");

        $this->assertSame("Titre\nPremier paragraphe\nSecond paragraphe", $body);
    }
}
