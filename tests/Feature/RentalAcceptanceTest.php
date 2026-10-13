<?php

namespace Tests\Feature;

use App\Enums\ContactMethodType;
use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalDocumentType;
use App\Mail\RentalAcceptanceInvitation;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\Person;
use App\Models\Rental;
use App\Services\RentalAcceptanceManager;
use App\Services\RentalContractGenerator;
use App\Tenancy\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_can_accept_a_private_rental_contract_once_through_a_personal_link(): void
    {
        Storage::fake('local');
        Mail::fake();
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $rental = $this->rental();
            app(RentalContractGenerator::class)->generate($rental, RentalDocumentType::RentalContract);
            $acceptance = app(RentalAcceptanceManager::class)->issue($rental);

            $this->assertSame(RentalAcceptanceStatus::Sent, $acceptance->status);
            $this->assertCount(1, $acceptance->documents);
            Mail::assertSent(RentalAcceptanceInvitation::class);

            $token = 'a-token-used-only-for-the-test';
            $acceptance->update(['token_hash' => hash('sha256', $token)]);
            $this->get(route('rental-acceptance.show', ['token' => $token]))->assertOk()->assertSee('Documents de location');
            $this->post(route('rental-acceptance.accept', ['token' => $token]), ['accepted_name' => 'Camille Durand', 'consent' => '1'])
                ->assertRedirect(route('rental-acceptance.show', ['token' => $token]));
            $this->get(route('rental-acceptance.show', ['token' => $token]))->assertOk()->assertSee('Acceptation enregistrée');

            $this->assertSame(RentalAcceptanceStatus::Accepted, $acceptance->fresh()->status);
            $this->assertSame('Camille Durand', $acceptance->fresh()->accepted_name);
            $this->assertDatabaseHas('rental_acceptance_events', ['request_id' => $acceptance->id, 'event' => 'accepted']);
        });
    }

    public function test_a_personal_acceptance_link_works_without_an_active_organization_context(): void
    {
        Storage::fake('local');
        Mail::fake();
        $organization = Organization::factory()->create();

        $token = app(OrganizationContext::class)->run($organization, function (): string {
            $rental = $this->rental();
            app(RentalContractGenerator::class)->generate($rental, RentalDocumentType::RentalContract);
            $acceptance = app(RentalAcceptanceManager::class)->issue($rental);
            $token = 'a-public-acceptance-token';
            $acceptance->update(['token_hash' => hash('sha256', $token)]);

            return $token;
        });

        $this->get(route('rental-acceptance.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Documents de location');
    }

    public function test_a_client_can_download_a_document_from_a_personal_link_without_an_active_organization_context(): void
    {
        Storage::fake('local');
        Mail::fake();
        $organization = Organization::factory()->create();

        [$token, $documentId] = app(OrganizationContext::class)->run($organization, function (): array {
            $rental = $this->rental();
            $document = app(RentalContractGenerator::class)->generate($rental, RentalDocumentType::RentalContract);
            $acceptance = app(RentalAcceptanceManager::class)->issue($rental);
            $token = 'a-public-document-token';
            $acceptance->update(['token_hash' => hash('sha256', $token)]);

            return [$token, $document->id];
        });

        $this->get(route('rental-acceptance.document', ['token' => $token, 'document' => $documentId]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_an_acceptance_time_is_displayed_in_the_organization_timezone(): void
    {
        Storage::fake('local');
        Mail::fake();
        $organization = Organization::factory()->create(['settings' => ['timezone' => 'Europe/Paris']]);

        $token = app(OrganizationContext::class)->run($organization, function (): string {
            $rental = $this->rental();
            app(RentalContractGenerator::class)->generate($rental, RentalDocumentType::RentalContract);
            $acceptance = app(RentalAcceptanceManager::class)->issue($rental);
            $token = 'a-timezone-token';
            $acceptance->update([
                'token_hash' => hash('sha256', $token),
                'status' => RentalAcceptanceStatus::Accepted,
                'accepted_at' => CarbonImmutable::parse('2026-10-13 06:50:00', 'UTC'),
                'accepted_name' => 'Camille Durand',
            ]);

            return $token;
        });

        $this->get(route('rental-acceptance.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('13/10/2026 à 08:50');
    }

    public function test_an_unsolicited_acceptance_request_can_be_cancelled(): void
    {
        Storage::fake('local');
        Mail::fake();
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $rental = $this->rental();
            app(RentalContractGenerator::class)->generate($rental, RentalDocumentType::RentalContract);
            $acceptance = app(RentalAcceptanceManager::class)->issue($rental);

            app(RentalAcceptanceManager::class)->cancel($acceptance);

            $this->assertSame(RentalAcceptanceStatus::Cancelled, $acceptance->fresh()->status);
            $this->assertDatabaseHas('rental_acceptance_events', ['request_id' => $acceptance->id, 'event' => 'cancelled']);
        });
    }

    private function rental(): Rental
    {
        OrganizationLegalProfile::query()->create([
            'display_name' => 'Contempo Luthiers', 'legal_name' => 'Contempo Luthiers EURL',
            'email' => 'atelier@example.test', 'address_line_1' => '9 quai Arloing',
            'postal_code' => '69009', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '92753315800022',
        ]);
        $person = Person::query()->create(['first_name' => 'Camille', 'last_name' => 'Durand', 'address_line_1' => '1 rue des Lilas', 'postal_code' => '69001', 'city' => 'Lyon']);
        $person->contactMethods()->create(['type' => ContactMethodType::Email, 'value' => 'camille@example.test', 'is_primary' => true]);
        $person->contactMethods()->create(['type' => ContactMethodType::Phone, 'value' => '0600000000', 'is_primary' => true]);
        $instrument = InstrumentAsset::query()->create(['reference' => 'V-001', 'name' => 'Violon d’étude']);

        return Rental::query()->create(['reference' => 'LOC-TEST', 'instrument_asset_id' => $instrument->id, 'person_id' => $person->id, 'starts_on' => '2026-10-08', 'unit_amount' => 42]);
    }
}
