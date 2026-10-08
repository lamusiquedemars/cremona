<?php

namespace Tests\Feature;

use App\Enums\ContactMethodType;
use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Models\InstrumentAsset;
use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\Person;
use App\Models\Rental;
use App\Models\RentalInsurancePlan;
use App\Services\RentalContractGenerator;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class RentalContractGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_an_immutable_private_rental_contract_from_a_complete_rental(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $rental = $this->completeRental();

            $document = app(RentalContractGenerator::class)->generate($rental, RentalDocumentType::RentalContract);

            $this->assertSame(RentalDocumentStatus::Generated, $document->status);
            $this->assertSame(RentalDocumentType::RentalContract, $document->type);
            $this->assertSame('contempo-location-v1', $document->template_version);
            $this->assertSame('42,00', $document->snapshot['rental']['monthly_amount']);
            $this->assertSame('0,00', $document->snapshot['rental']['insurance_monthly_amount']);
            $this->assertTrue(Storage::disk('local')->exists($document->privateDocument->path));
            $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($document->privateDocument->path));
        });
    }

    public function test_it_generates_the_optional_insurance_contract_and_versions_the_previous_contract(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $plan = RentalInsurancePlan::query()->create(['name' => 'Assurance standard', 'monthly_amount' => 4.5]);
            $rental = $this->completeRental(['insurance_plan_id' => $plan->id]);
            $generator = app(RentalContractGenerator::class);

            $first = $generator->generate($rental, RentalDocumentType::RentalContract);
            $second = $generator->generate($rental, RentalDocumentType::RentalContract);
            $insurance = $generator->generate($rental, RentalDocumentType::InsuranceContract);

            $this->assertSame(RentalDocumentStatus::Superseded, $first->fresh()->status);
            $this->assertSame(2, $second->version_number);
            $this->assertSame($first->id, $second->version_of_id);
            $this->assertSame('contempo-assurance-v1', $insurance->template_version);
            $this->assertSame('4,50', $insurance->snapshot['rental']['insurance_monthly_amount']);
            $this->assertSame('Assurance standard', $insurance->snapshot['rental']['insurance_plan_name']);
        });
    }

    public function test_it_refuses_to_generate_an_insurance_contract_without_an_insurance_amount(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('formule d’assurance');

            app(RentalContractGenerator::class)->generate($this->completeRental(), RentalDocumentType::InsuranceContract);
        });
    }

    /** @param array<string, mixed> $overrides */
    private function completeRental(array $overrides = []): Rental
    {
        OrganizationLegalProfile::query()->create([
            'display_name' => 'Contempo Luthiers',
            'legal_name' => 'Contempo Luthiers EURL',
            'email' => 'atelier@example.test',
            'phone' => '0478240605',
            'address_line_1' => '9 quai Arloing',
            'postal_code' => '69009',
            'city' => 'Lyon',
            'country_code' => 'FR',
            'registration_number' => '92753315800022',
        ]);
        $person = Person::query()->create([
            'first_name' => 'Camille', 'last_name' => 'Durand',
            'address_line_1' => '1 rue des Lilas', 'postal_code' => '69001', 'city' => 'Lyon',
        ]);
        $person->contactMethods()->create(['type' => ContactMethodType::Email, 'value' => 'camille@example.test', 'is_primary' => true]);
        $person->contactMethods()->create(['type' => ContactMethodType::Phone, 'value' => '0600000000', 'is_primary' => true]);
        $instrument = InstrumentAsset::query()->create(['reference' => 'V-001', 'name' => 'Violon d’étude']);

        return Rental::query()->create(array_merge([
            'reference' => 'LOC-TEST', 'instrument_asset_id' => $instrument->id, 'person_id' => $person->id,
            'starts_on' => '2026-10-08', 'unit_amount' => 42, 'insurance_monthly_amount' => 0,
        ], $overrides));
    }
}
