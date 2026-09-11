<?php

namespace Tests\Feature;

use App\Enums\InstrumentAssetStatus;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Services\ContempoInstrumentPublisher;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContempoInstrumentPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublished_instrument_is_sent_as_archived_to_remove_it_from_the_public_catalogue(): void
    {
        Http::fake(['https://contempo.test/api/instruments' => Http::response([], 201)]);

        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationIntegration::query()->create([
                'provider' => 'contempo_cms',
                'name' => 'instrument_projection',
                'credentials' => ['endpoint' => 'https://contempo.test/api/instruments', 'token' => 'test-token'],
                'status' => 'active',
            ]);
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Violon d’essai',
                'reference' => 'V-001',
                'status' => InstrumentAssetStatus::Available,
                'is_site_published' => false,
            ]);

            app(ContempoInstrumentPublisher::class)->publish($instrument);
        });

        Http::assertSent(fn ($request): bool => $request['availability'] === 'archived');
    }

    public function test_public_uploaded_photos_are_sent_as_public_urls(): void
    {
        Storage::fake('public');
        Http::fake(['https://contempo.test/api/instruments' => Http::response([], 201)]);

        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationIntegration::query()->create([
                'provider' => 'contempo_cms',
                'name' => 'instrument_projection',
                'credentials' => ['endpoint' => 'https://contempo.test/api/instruments', 'token' => 'test-token'],
                'status' => 'active',
            ]);
            $instrument = InstrumentAsset::query()->create([
                'name' => 'Violon d’essai',
                'reference' => 'V-001',
                'status' => InstrumentAssetStatus::Available,
                'is_site_published' => true,
                'media' => [
                    ['path' => 'instruments/violon.jpg', 'caption' => 'Table du violon', 'is_public' => true],
                    ['path' => 'instruments/interne.jpg', 'is_public' => false],
                ],
            ]);

            app(ContempoInstrumentPublisher::class)->publish($instrument);
        });

        Http::assertSent(function ($request): bool {
            $media = $request['media'];

            return count($media) === 1
                && $media[0]['caption'] === 'Table du violon'
                && str_ends_with($media[0]['url'], '/storage/instruments/violon.jpg');
        });
    }
}
