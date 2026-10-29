<?php

namespace Tests\Feature;

use App\Filament\Resources\InstrumentAssets\Pages\EditInstrumentAsset;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use ReflectionClass;
use Tests\TestCase;

class InstrumentAssetFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_are_stored_as_a_single_json_document(): void
    {
        $organization = Organization::factory()->create();

        $instrument = app(OrganizationContext::class)->run($organization, fn (): InstrumentAsset => InstrumentAsset::query()->create([
            'name' => 'Violon d’essai',
            'media' => [[
                'path' => 'instruments/violon.jpg',
                'caption' => 'Table',
                'is_public' => true,
            ]],
        ]));

        $rawMedia = DB::table('instrument_assets')->where('id', $instrument->id)->value('media');

        $this->assertSame([[
            'path' => 'instruments/violon.jpg',
            'caption' => 'Table',
            'is_public' => true,
        ]], json_decode($rawMedia, true));

        $saveHook = new ReflectionMethod(EditInstrumentAsset::class, 'mutateFormDataBeforeSave');
        $this->assertNotSame(EditInstrumentAsset::class, $saveHook->getDeclaringClass()->getName());
    }

    public function test_edit_form_recovers_legacy_double_encoded_photos_for_the_preview(): void
    {
        $page = (new ReflectionClass(EditInstrumentAsset::class))->newInstanceWithoutConstructor();
        $hydrate = new ReflectionMethod(EditInstrumentAsset::class, 'mutateFormDataBeforeFill');
        $photos = [['path' => 'instruments/violon.jpg', 'is_public' => true]];

        $data = $hydrate->invoke($page, [
            'media' => json_encode(json_encode($photos)),
            'attributes' => json_encode(json_encode([])),
        ]);

        $this->assertSame($photos, $data['media']);
        $this->assertSame([], $data['attributes']);
    }
}
