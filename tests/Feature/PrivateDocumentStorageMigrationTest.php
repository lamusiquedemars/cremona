<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PrivateDocument;
use App\Services\PrivateDocumentStorageMigrator;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateDocumentStorageMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('private-documents.allowed_disks', ['local', 'scaleway-private']);
        Storage::fake('local');
        Storage::fake('scaleway-private');
    }

    public function test_it_inventories_then_migrates_a_private_document_only_after_checksum_verification(): void
    {
        $document = $this->documentWithContent('contenu privé à conserver');
        $migrator = app(PrivateDocumentStorageMigrator::class);

        $this->assertSame('pending', $migrator->migrate($document));
        $this->assertSame('local', $document->fresh()->disk);
        Storage::disk('scaleway-private')->assertMissing($document->path);
        $this->assertDatabaseHas('private_document_storage_migrations', [
            'private_document_id' => $document->id,
            'source_disk' => 'local',
            'target_disk' => 'scaleway-private',
            'status' => 'pending',
        ]);

        $this->assertSame('verified', $migrator->migrate($document->fresh(), execute: true));
        $migrated = $document->fresh();

        $this->assertSame('scaleway-private', $migrated->disk);
        Storage::disk('scaleway-private')->assertExists($migrated->path);
        Storage::disk('local')->assertExists($migrated->path);
        $this->assertDatabaseHas('private_document_storage_migrations', [
            'private_document_id' => $document->id,
            'status' => 'verified',
            'sha256' => $document->sha256,
        ]);
    }

    public function test_it_refuses_to_copy_when_the_source_no_longer_matches_the_registered_checksum(): void
    {
        $document = $this->documentWithContent('contenu initial');
        Storage::disk('local')->put($document->path, 'contenu altéré');

        try {
            app(PrivateDocumentStorageMigrator::class)->migrate($document, execute: true);
            $this->fail('La migration aurait dû refuser le fichier altéré.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('empreinte SHA-256', $exception->getMessage());
        }

        Storage::disk('scaleway-private')->assertMissing($document->path);
        $this->assertDatabaseHas('private_document_storage_migrations', [
            'private_document_id' => $document->id,
            'status' => 'failed',
        ]);
    }

    private function documentWithContent(string $content): PrivateDocument
    {
        $organization = Organization::factory()->create();
        $path = 'documents/'.$organization->id.'/test/contrat.pdf';
        Storage::disk('local')->put($path, $content);

        return app(OrganizationContext::class)->run($organization, fn (): PrivateDocument => PrivateDocument::query()->create([
            'title' => 'Contrat de test',
            'category' => 'Location',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'contrat.pdf',
            'declared_mime_type' => 'application/pdf',
            'detected_mime_type' => 'application/pdf',
            'size' => strlen($content),
            'sha256' => hash('sha256', $content),
        ]));
    }
}
