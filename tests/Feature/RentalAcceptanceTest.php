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
