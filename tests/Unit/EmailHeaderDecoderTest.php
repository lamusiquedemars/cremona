<?php

namespace Tests\Unit;

use App\Support\EmailHeaderDecoder;
use PHPUnit\Framework\TestCase;

class EmailHeaderDecoderTest extends TestCase
{
    public function test_it_decodes_a_utf8_quoted_printable_email_subject(): void
    {
        $subject = '=?utf-8?q?Fermeture_estivale_et_nouvelle_adresse_=C3=A0_partir_du_24_ao?= =?utf-8?q?=C3=BBt?=';

        $this->assertSame(
            'Fermeture estivale et nouvelle adresse à partir du 24 août',
            EmailHeaderDecoder::text($subject),
        );
    }
}
