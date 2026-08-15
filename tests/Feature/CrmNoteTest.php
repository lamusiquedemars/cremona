<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CrmNote;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class CrmNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_are_scoped_to_their_organization_and_update_last_activity(): void
    {
        $firstOrganization = Organization::factory()->create();
        $secondOrganization = Organization::factory()->create();
        $author = User::factory()->create();
        $author->organizations()->attach([$firstOrganization->id, $secondOrganization->id]);

        [$person, $firstNote] = app(OrganizationContext::class)->run(
            $firstOrganization,
            function () use ($author): array {
                $person = Person::query()->create(['display_name' => 'Premier contact']);
                $note = $person->notes()->create([
                    'author_user_id' => $author->id,
                    'body' => '  Préfère être contacté par e-mail.  ',
                ]);

                return [$person, $note];
            },
        );

        app(OrganizationContext::class)->run($secondOrganization, function () use ($author): void {
            $company = Company::query()->create(['name' => 'Seconde entreprise']);
            $company->notes()->create([
                'author_user_id' => $author->id,
                'body' => 'Deuxième note.',
            ]);

            $this->assertSame(1, CrmNote::query()->count());
            $this->assertSame('Deuxième note.', CrmNote::query()->sole()->body);
        });

        app(OrganizationContext::class)->run($firstOrganization, function () use ($person, $firstNote): void {
            $this->assertSame(1, CrmNote::query()->count());
            $this->assertSame('Préfère être contacté par e-mail.', $firstNote->body);
            $this->assertNotNull($person->refresh()->last_activity_at);
        });

        $this->assertSame(0, CrmNote::query()->count());
    }

    public function test_notes_reject_outside_authors_and_cannot_be_changed(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $member->organizations()->attach($organization);

        app(OrganizationContext::class)->run($organization, function () use ($member, $outsider): void {
            $person = Person::query()->create(['display_name' => 'Contact protégé']);

            try {
                $person->notes()->create([
                    'author_user_id' => $outsider->id,
                    'body' => 'Note interdite.',
                ]);
                $this->fail('An outsider should not be able to author a CRM note.');
            } catch (LogicException) {
                $this->assertSame(0, CrmNote::query()->count());
            }

            $note = $person->notes()->create([
                'author_user_id' => $member->id,
                'body' => 'Note conservée.',
            ]);

            $this->expectException(LogicException::class);
            $note->update(['body' => 'Note réécrite.']);
        });
    }
}
