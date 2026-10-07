<?php

namespace Tests\Feature;

use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\PrivateDocument;
use App\Models\Rental;
use App\Services\RentalDocumentManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RentalDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_generated_rental_document_freezes_its_snapshot_and_private_pdf(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $rental = $this->rental();
            $privateDocument = $this->privateDocument('a');
            $document = app(RentalDocumentManager::class)->registerGenerated(
                $rental,
                $privateDocument,
                RentalDocumentType::RentalContract,
                'contempo-location-v1',
                ['rental_reference' => $rental->reference, 'monthly_amount' => '42.00'],
            );

            $this->assertSame(RentalDocumentStatus::Generated, $document->status);
            $this->assertSame($privateDocument->sha256, $document->content_sha256);
            $this->assertSame($privateDocument->id, $document->private_document_id);
            $this->assertDatabaseHas('private_document_links', [
                'private_document_id' => $privateDocument->id,
                'linkable_type' => 'rental',
                'linkable_id' => $rental->id,
            ]);

            $document->snapshot = ['monthly_amount' => '99.00'];
            $this->expectException(LogicException::class);
            $document->save();
        });
    }

    public function test_a_new_document_version_keeps_the_document_type_and_rental(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $rental = $this->rental();
            $first = app(RentalDocumentManager::class)->registerGenerated(
                $rental, $this->privateDocument('a'), RentalDocumentType::RentalContract, 'v1', ['amount' => '42.00'],
            );
            $second = app(RentalDocumentManager::class)->registerGenerated(
                $rental, $this->privateDocument('b'), RentalDocumentType::RentalContract, 'v2', ['amount' => '45.00'], previousVersion: $first,
            );

            $this->assertSame($first->id, $second->version_of_id);
            $this->assertSame(2, $second->version_number);
        });
    }

    private function rental(): Rental
    {
        $instrument = InstrumentAsset::query()->create(['name' => 'Violon de test']);

        return Rental::query()->create(['instrument_asset_id' => $instrument->id, 'unit_amount' => 42]);
    }

    private function privateDocument(string $suffix): PrivateDocument
    {
        return PrivateDocument::query()->create([
            'title' => 'Contrat '.$suffix,
            'disk' => 'local',
            'path' => 'documents/test/'.$suffix.'.pdf',
            'original_name' => 'contrat-'.$suffix.'.pdf',
            'declared_mime_type' => 'application/pdf',
            'detected_mime_type' => 'application/pdf',
            'size' => 42,
            'sha256' => str_repeat($suffix, 64),
        ]);
    }
}
