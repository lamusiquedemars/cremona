<?php

namespace Tests\Unit;

use App\Support\EmailReplyComposer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EmailReplyComposerTest extends TestCase
{
    public function test_it_creates_html_and_text_reply_content_with_the_original_message(): void
    {
        $content = (new EmailReplyComposer)->compose(
            "Bonjour Ivo,\nVoici ma réponse.",
            "Bonjour,\nPouvez-vous confirmer ?",
            'Ivo Correia',
            new DateTimeImmutable('2026-08-31 15:41:00+02:00'),
            'Europe/Paris',
        );

        $this->assertSame(
            "Bonjour Ivo,\nVoici ma réponse.\n\nLe 31/08/2026 à 15:41, Ivo Correia a écrit :\n> Bonjour,\n> Pouvez-vous confirmer ?",
            $content['text'],
        );
        $this->assertStringContainsString('font-family: Arial, Helvetica, sans-serif', $content['html']);
        $this->assertStringContainsString('<blockquote', $content['html']);
        $this->assertStringContainsString('Pouvez-vous confirmer ?', $content['html']);
    }
}
