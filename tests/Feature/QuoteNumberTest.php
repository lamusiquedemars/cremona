<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Quote;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class QuoteNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbers_increment_per_organization_and_year_without_reusing_deleted_numbers(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 16));
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $context = app(OrganizationContext::class);
        $context->run($organization, function (): void {
            $first = Quote::create(['title' => 'Premier']);
            $this->assertSame('D260001', $first->reference);
            $second = Quote::create(['title' => 'Second']);
            $this->assertSame('D260002', $second->reference);
            $second->delete();
            $this->assertSame('D260003', Quote::create(['title' => 'Troisième'])->reference);
            $first->update(['issued_on' => '2025-01-01']);
            $this->assertSame('D260001', $first->fresh()->reference);
        });
        $context->run($other, fn () => $this->assertSame('D260001', Quote::create(['title' => 'Autre'])->reference));
        $this->travelTo(now()->setDate(2027, 1, 1));
        $context->run($organization, fn () => $this->assertSame('D270001', Quote::create(['title' => 'Nouvelle année'])->reference));
    }

    public function test_existing_references_are_preserved_and_matching_numbers_are_skipped(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 16));
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $legacy = Quote::create(['title' => 'Ancien', 'reference' => 'Q-ANCIEN']);
            Quote::create(['title' => 'Import', 'reference' => 'D260042']);
            $this->assertSame('D260043', Quote::create(['title' => 'Nouveau'])->reference);
            $this->assertSame('Q-ANCIEN', $legacy->fresh()->reference);
        });
    }

    public function test_the_counter_refuses_to_exceed_four_digits(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 16));
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            Quote::create(['title' => 'Dernier', 'reference' => 'D269999']);
            $this->expectException(LogicException::class);
            Quote::create(['title' => 'Limite']);
        });
    }
}
