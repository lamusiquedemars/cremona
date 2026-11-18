<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\ClientSearch;
use App\Models\Organization;
use App\Models\Person;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSearchMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_match_only_when_type_size_and_budget_match(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $search = ClientSearch::query()->create([
                'person_id' => $person->id, 'object_type' => 'violon', 'size' => '3/4', 'maximum_budget' => 600,
            ]);

            $match = Availability::query()->create(['title' => 'Violon d’essai', 'object_type' => 'violon', 'size' => '3/4', 'amount' => 550]);
            Availability::query()->create(['title' => 'Violon hors budget', 'object_type' => 'violon', 'size' => '3/4', 'amount' => 650]);
            Availability::query()->create(['title' => 'Violon mauvaise taille', 'object_type' => 'violon', 'size' => '4/4', 'amount' => 550]);

            $this->assertCount(1, $search->fresh()->matches);
            $this->assertSame('match', $search->fresh()->matches->sole()->match_kind);
            $this->assertSame($match->id, $search->fresh()->matches->sole()->availability_id);
        });
    }

    public function test_it_marks_an_incomplete_criterion_for_review(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $person = Person::query()->create(['display_name' => 'Alex Durand']);
            $search = ClientSearch::query()->create([
                'person_id' => $person->id, 'object_type' => 'violon', 'size' => '3/4', 'maximum_budget' => 600,
            ]);
            Availability::query()->create(['title' => 'Violon chez un confrère', 'object_type' => 'violon', 'source' => 'partner']);

            $this->assertSame('review', $search->fresh()->matches->sole()->match_kind);
        });
    }

    public function test_closing_a_search_removes_pending_alerts(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $person = Person::query()->create(['display_name' => 'Lou Bernard']);
            $search = ClientSearch::query()->create(['person_id' => $person->id, 'object_type' => 'colophane']);
            Availability::query()->create(['title' => 'Colophane neuve', 'object_type' => 'colophane']);
            $search->update(['status' => 'closed']);

            $this->assertCount(0, $search->fresh()->matches);
        });
    }
}
