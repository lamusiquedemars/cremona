<?php

namespace App\Support;

final class EmailReplyContent
{
    /** @return array{text: string, html: string} */
    public static function fromMarkdown(string $markdown): array
    {
        $markdown = trim($markdown);
        $html = (string) str($markdown)->markdown()->sanitizeHtml();

        return [
            'text' => EmailBodyText::forDisplay($html),
            'html' => $html,
        ];
    }
}
