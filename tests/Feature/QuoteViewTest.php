<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Services\OrganizationModuleRegistry;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_quote_view_is_a_full_business_document_with_its_reference_and_totals(): void
    {
        $organization = Organization::factory()->create(['name' => 'Contempo Luthiers']);
        app(OrganizationModuleRegistry::class)->sync($organization, ['quotes']);
        $user = User::factory()->create();
        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        $quote = app(OrganizationContext::class)->run($organization, function (): Quote {
            $person = Person::query()->create(['display_name' => 'Giovanni Contempo']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'giovanni@example.test', 'is_primary' => true]);
            $quote = Quote::query()->create([
                'reference' => 'D26001',
                'title' => 'Révision d’un violon',
                'person_id' => $person->id,
                'issued_on' => '2026-09-14',
                'valid_until' => '2026-10-14',
                'introduction' => 'Merci pour votre confiance.',
                'discount_amount' => 10,
                'tax_note' => 'TVA non applicable.',
                'payment_terms' => 'Paiement à réception.',
            ]);
            $quote->lines()->create([
                'kind' => 'service',
                'description' => 'Reméchage complet',
                'quantity' => 1,
                'unit_amount' => 70,
            ]);

            return $quote->fresh();
        });

        $this->actingAs($user)
            ->get(QuoteResource::getUrl('view', ['record' => $quote], tenant: $organization))
            ->assertOk()
            ->assertSee('Devis D26001')
            ->assertSee('Giovanni Contempo')
            ->assertSee('Reméchage complet')
            ->assertSee('Bon pour accord')
            ->assertSee('Total final')
            ->assertSee('quote-document')
            ->assertSee('quote-document-frame')
            ->assertSee('@media print')
            ->assertSee('Modifier le devis')
            ->assertSee('Supprimer le brouillon');
    }
}
