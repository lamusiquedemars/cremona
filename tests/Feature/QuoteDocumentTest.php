<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\OrganizationQuoteSettings;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteWorkflowManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class QuoteDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_issuer_profile_is_required_and_snapshotted_when_a_quote_is_sent(): void
    {
        $organization = Organization::factory()->create(['name' => 'Atelier Exemple']);

        app(OrganizationContext::class)->run($organization, function () use ($organization): void {
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $quote = Quote::query()->create(['title' => 'Reméchage', 'person_id' => $person->id]);
            $quote->lines()->create(['description' => 'Reméchage complet', 'quantity' => 1, 'unit_amount' => 70]);

            $this->expectException(LogicException::class);
            app(QuoteWorkflowManager::class)->markSent($quote, 'email');
        });

        app(OrganizationContext::class)->run($organization, function () use ($organization): void {
            OrganizationLegalProfile::query()->create([
                'display_name' => 'Atelier Exemple', 'legal_name' => 'Atelier Exemple', 'email' => 'bonjour@example.test',
                'address_line_1' => '1 rue des Cordes', 'postal_code' => '69000', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
            ]);
            $quote = Quote::query()->first();
            app(QuoteWorkflowManager::class)->markSent($quote, 'email');
            $quote->refresh();
            $this->assertSame('Atelier Exemple', $quote->issuer_snapshot['legal_name']);
            $this->assertSame('Camille Martin', $quote->recipient_snapshot['name']);
        });
    }

    public function test_an_authorized_user_can_download_a_real_quote_pdf(): void
    {
        $organization = Organization::factory()->create(['name' => 'Atelier Exemple']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);
        $quote = app(OrganizationContext::class)->run($organization, function (): Quote {
            OrganizationLegalProfile::query()->create([
                'display_name' => 'Atelier Exemple', 'legal_name' => 'Atelier Exemple', 'email' => 'bonjour@example.test',
                'address_line_1' => '1 rue des Cordes', 'postal_code' => '69000', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
            ]);
            OrganizationQuoteSettings::query()->create(['default_validity_days' => 30, 'default_payment_terms' => 'Paiement à réception.']);
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $quote = Quote::query()->create(['title' => 'Reméchage', 'person_id' => $person->id]);
            $quote->lines()->create(['description' => 'Reméchage complet', 'quantity' => 1, 'unit_amount' => 70]);

            return $quote;
        });

        $this->actingAs($user)->get(route('quotes.pdf', ['publicId' => $quote->public_id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="Devis-'.$quote->reference.'.pdf"');
    }
}
