<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Quote;
use App\Models\QuoteLineTemplate;
use App\Services\QuoteLineTemplateManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class QuoteLineTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_template_creates_a_snapshot_line_and_refreshes_the_quote_total(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $template = QuoteLineTemplate::query()->create([
                'code' => 'REMECHAGE',
                'label' => 'Reméchage d’archet',
                'kind' => 'prestation',
                'description' => 'Reméchage blanc, fourniture comprise.',
                'default_quantity' => 1,
                'default_unit_amount' => 85,
            ]);
            $quote = Quote::query()->create(['title' => 'Entretien de l’archet']);

            $line = app(QuoteLineTemplateManager::class)->addToQuote($quote, $template, 2);
            $template->update(['label' => 'Reméchage modifié', 'default_unit_amount' => 95]);

            $this->assertSame($template->id, $line->quote_line_template_id);
            $this->assertSame('Reméchage d’archet', $line->template_label_snapshot);
            $this->assertSame('85.00', $line->unit_amount);
            $this->assertSame('170.00', $line->total_amount);
            $this->assertSame('170.00', $quote->refresh()->total_amount);
        });
    }

    public function test_a_template_from_another_organization_cannot_be_added_to_a_quote(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $template = app(OrganizationContext::class)->run($first, fn (): QuoteLineTemplate => QuoteLineTemplate::query()->create([
            'label' => 'Ajustement',
            'description' => 'Ajustement atelier.',
        ]));

        app(OrganizationContext::class)->run($second, function () use ($template): void {
            $quote = Quote::query()->create(['title' => 'Devis second atelier']);

            $this->expectException(LogicException::class);
            app(QuoteLineTemplateManager::class)->addToQuote($quote, $template);
        });
    }

    public function test_pennylane_reference_is_scoped_to_one_organization(): void
    {
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();

        app(OrganizationContext::class)->run($first, fn (): Quote => Quote::query()->create([
            'title' => 'Premier devis',
            'pennylane_quote_id' => 'pen-quote-42',
            'pennylane_status' => 'sent',
        ]));
        $other = app(OrganizationContext::class)->run($second, fn (): Quote => Quote::query()->create([
            'title' => 'Second devis',
            'pennylane_quote_id' => 'pen-quote-42',
            'pennylane_status' => 'sent',
        ]));

        $this->assertSame('pen-quote-42', $other->pennylane_quote_id);
    }
}
