<?php

namespace Tests\Unit;

use App\Support\EmailReplyContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailReplyContentTest extends TestCase
{
    #[Test]
    public function it_converts_markdown_to_safe_html_and_readable_archive_text(): void
    {
        $content = EmailReplyContent::fromMarkdown("Bonjour **Camille**.\n\n- Premier point\n- Deuxième point\n\n[Voir le site](https://example.test)");

        $this->assertStringContainsString('<strong>Camille</strong>', $content['html']);
        $this->assertStringContainsString('<ul>', $content['html']);
        $this->assertStringContainsString('Bonjour Camille.', $content['text']);
        $this->assertStringContainsString('Premier point', $content['text']);
        $this->assertStringContainsString('Voir le site', $content['text']);
    }
}
