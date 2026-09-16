<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\OrganizationQuoteSettings;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuotePdfRenderer;
use App\Services\QuoteWorkflowManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use LogicException;
use Tests\TestCase;

class QuoteDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_issuer_profile_is_required_and_snapshotted_when_a_quote_is_sent(): void
    {
        $organization = Organization::factory()->create(['name' => 'Atelier Exemple']);

        app(OrganizationContext::class)->run($organization, function (): void {
            $person = Person::query()->create(['display_name' => 'Camille Martin', 'address_line_1' => '2 rue du Client', 'postal_code' => '75001', 'city' => 'Paris', 'country_code' => 'FR']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $quote = Quote::query()->create(['title' => 'Reméchage', 'person_id' => $person->id, 'introduction' => 'Travaux proposés', 'discount_amount' => 5, 'notes' => 'CONFIDENTIEL INTERNE', 'currency' => 'CHF']);
            $quote->lines()->create(['kind' => 'service', 'description' => 'Reméchage complet', 'quantity' => 1, 'unit_amount' => 70]);

            try {
                app(QuoteWorkflowManager::class)->markSent($quote, 'email');
                $this->fail('Un profil émetteur incomplet doit bloquer l’envoi.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('Coordonnées et mentions légales', $exception->getMessage());
            }
        });

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationLegalProfile::query()->create([
                'display_name' => 'Atelier Exemple', 'legal_name' => 'Atelier Exemple', 'email' => 'bonjour@example.test',
                'address_line_1' => '1 rue des Cordes', 'postal_code' => '69000', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
                'phone' => '0123456789', 'website' => 'https://atelier.example', 'vat_number' => 'FR123456789',
                'legal_notice' => "Mention légale exemple\nDeuxième mention",
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
                'phone' => '0123456789', 'website' => 'https://atelier.example', 'vat_number' => 'FR123456789',
                'legal_notice' => "Mention légale exemple\nDeuxième mention",
            ]);
            OrganizationQuoteSettings::query()->create(['default_validity_days' => 30, 'default_payment_terms' => "Acompte de 30 %.\nSolde à réception.", 'default_tax_note' => 'Note fiscale exemple', 'terms_url' => 'https://atelier.example/cgv']);
            $person = Person::query()->create(['display_name' => 'Camille Martin', 'address_line_1' => '2 rue du Client', 'postal_code' => '75001', 'city' => 'Paris', 'country_code' => 'FR']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $quote = Quote::query()->create(['title' => 'Reméchage', 'person_id' => $person->id, 'introduction' => 'Travaux proposés', 'discount_amount' => 5, 'notes' => 'CONFIDENTIEL INTERNE', 'currency' => 'CHF']);
            $quote->lines()->create(['kind' => 'service', 'description' => 'Reméchage complet', 'quantity' => 1, 'unit_amount' => 70]);

            return $quote;
        });

        $html = null;
        View::composer('quotes.pdf', function ($view) use (&$html): void {
            $html = $view->getData();
        });
        $response = $this->actingAs($user)->get(route('quotes.pdf', ['publicId' => $quote->public_id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="Devis-'.$quote->reference.'.pdf"');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertCount(1, $html['quote']->lines);
        $rendered = view('quotes.pdf', $html)->render();
        foreach (['Reméchage complet', 'Camille Martin', '2 rue du Client', 'Travaux proposés', 'Acompte de 30 %.', 'Solde à réception.', 'Note fiscale exemple', 'https://atelier.example/cgv', '0123456789', 'FR123456789', 'Deuxième mention', 'Bon pour accord', '65,00', 'CHF', $quote->reference] as $expected) {
            $this->assertStringContainsString($expected, $rendered);
        }
        $this->assertStringNotContainsString('CONFIDENTIEL INTERNE', $rendered);
        $this->assertStringNotContainsString(' €', $rendered);
        $this->assertNull(app(OrganizationContext::class)->id());
        if ($path = getenv('QUOTE_PDF_REVIEW_PATH')) {
            file_put_contents($path, $response->getContent());
        }
        $other = Organization::factory()->create();
        app(OrganizationContext::class)->run($other, function () use ($quote, $other, &$html): void {
            app(QuotePdfRenderer::class)->render($quote);
            $this->assertCount(1, $html['quote']->lines);
            $this->assertSame($other->id, app(OrganizationContext::class)->id());
        });
        $quote->payment_terms = null;
        $quote->tax_note = null;
        $quote->valid_until = null;
        app(QuotePdfRenderer::class)->render($quote);
        $incomplete = view('quotes.pdf', $html)->render();
        $this->assertStringContainsString('Validité à préciser', $incomplete);
        $this->assertStringContainsString('Conditions fiscales à préciser.', $incomplete);
        $this->assertStringContainsString('À préciser.', $incomplete);

        app(OrganizationContext::class)->run($organization, function () use ($quote): void {
            for ($i = 2; $i <= 30; $i++) {
                $quote->lines()->create(['kind' => 'service', 'description' => "Prestation numéro {$i}\nDescription complémentaire", 'quantity' => 1, 'unit_amount' => 10]);
            }
        });
        $longPdf = app(QuotePdfRenderer::class)->render($quote->fresh());
        $this->assertCount(30, $html['quote']->lines);
        if ($path = getenv('QUOTE_PDF_REVIEW_PATH')) {
            file_put_contents($path.'.long.pdf', $longPdf);
        }
        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized)->get(route('quotes.pdf', ['publicId' => $quote->public_id]))->assertForbidden();
    }
}
