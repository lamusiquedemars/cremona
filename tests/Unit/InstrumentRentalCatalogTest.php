<?php

namespace Tests\Unit;

use App\Support\InstrumentRentalCatalog;
use PHPUnit\Framework\TestCase;

class InstrumentRentalCatalogTest extends TestCase
{
    public function test_alto_sizes_cover_children_and_do_not_offer_half_centimeters(): void
    {
        $sizes = InstrumentRentalCatalog::sizesFor('alto');

        $this->assertSame([
            '28cm' => '28 cm',
            '31cm' => '31 cm',
            '33cm' => '33 cm',
            '36cm' => '36 cm',
            '37cm' => '37 cm',
            '38cm' => '38 cm',
            '39cm' => '39 cm',
            '40cm' => '40 cm',
            '41cm' => '41 cm',
            '42cm' => '42 cm',
            'other' => 'Autre taille',
        ], $sizes);
        $this->assertArrayNotHasKey('40_5cm', $sizes);
        $this->assertArrayNotHasKey('41_5cm', $sizes);
    }
}
