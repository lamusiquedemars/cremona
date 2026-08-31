<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class EmailReplyComposer
{
    /** @return array{text: string, html: string} */
    public function compose(
        string $reply,
        ?string $quotedBody = null,
        ?string $quotedAuthor = null,
        ?DateTimeInterface $quotedAt = null,
        string $timezone = 'UTC',
    ): array {
        $reply = trim($reply);

        if (blank($quotedBody) || $quotedAt === null) {
            return [
                'text' => $reply,
                'html' => $this->paragraphs($reply),
            ];
        }

        $date = CarbonImmutable::instance($quotedAt)->setTimezone($timezone);
        $author = filled($quotedAuthor) ? $quotedAuthor : 'votre correspondant';
        $introduction = sprintf('Le %s, %s a écrit :', $date->format('d/m/Y à H:i'), $author);
        $quotedBody = trim($quotedBody);

        return [
            'text' => $reply."\n\n{$introduction}\n".$this->quotedText($quotedBody),
            'html' => $this->paragraphs($reply)
                .'<p style="margin: 24px 0 8px;">'.e($introduction).'</p>'
                .'<blockquote style="margin: 0; padding-left: 16px; border-left: 2px solid #d1d5db; color: #4b5563;">'
                .$this->lineBreaks($quotedBody)
                .'</blockquote>',
        ];
    }

    private function paragraphs(string $text): string
    {
        return '<div style="font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.5; color: #111827;">'
            .$this->lineBreaks($text)
            .'</div>';
    }

    private function lineBreaks(string $text): string
    {
        return nl2br(e($text), false);
    }

    private function quotedText(string $text): string
    {
        return collect(preg_split('/\R/u', $text) ?: [$text])
            ->map(fn (string $line): string => '> '.$line)
            ->implode("\n");
    }
}
