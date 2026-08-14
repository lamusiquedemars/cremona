<?php

namespace Tests\Feature;

use App\Enums\ContactMethodType;
use App\Enums\IncomingRequestOutcome;
use App\Enums\IncomingRequestStatus;
use App\Models\IncomingRequest;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use App\Services\ContactMatcher;
use App\Services\IncomingRequestManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class IncomingRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_request_is_idempotent_and_does_not_create_a_person(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $attributes = [
                'idempotency_key' => 'site-a:submission-123',
                'source_channel' => 'website',
                'source_site_reference' => 'site-a',
                'source_form_reference' => 'qualified-contact-v1',
                'name_snapshot' => 'Ada Lovelace',
                'email_snapshot' => 'ada@example.test',
                'message' => 'I would like to discuss a project.',
                'answers' => [[
                    'field_key' => 'preferred_modality',
                    'label_snapshot' => 'Preferred meeting format',
                    'value' => 'remote',
                ]],
                'consent' => [
                    'purpose' => 'respond_to_request',
                    'channel' => 'email',
                    'status' => 'granted',
                    'statement_snapshot' => 'I authorize a reply to this request.',
                    'statement_version' => 'v1',
                    'source' => 'qualified-contact-v1',
                ],
            ];

            $first = app(IncomingRequestManager::class)->receive($attributes);
            $second = app(IncomingRequestManager::class)->receive($attributes);

            $this->assertTrue($first->is($second));
            $this->assertSame(1, IncomingRequest::query()->count());
            $this->assertSame(0, Person::query()->count());
            $this->assertSame(1, $first->answers()->count());
            $this->assertSame(1, $first->consents()->count());
            $this->assertSame(1, $first->activities()->count());
        });
    }

    public function test_matching_only_suggests_people_and_never_merges_them(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $emailMatch = Person::query()->create(['display_name' => 'Email Match']);
            $phoneMatch = Person::query()->create(['display_name' => 'Phone Match']);
            $emailMatch->contactMethods()->create([
                'type' => ContactMethodType::Email,
                'value' => 'MATCH@example.test',
            ]);
            $phoneMatch->contactMethods()->create([
                'type' => ContactMethodType::Phone,
                'value' => '+33 6 12 34 56 78',
            ]);

            $matches = app(ContactMatcher::class)->suggestPeople(
                'match@example.test',
                '+33 (6) 12 34 56 78',
            );

            $this->assertEqualsCanonicalizing(
                [$emailMatch->id, $phoneMatch->id],
                $matches->pluck('id')->all(),
            );
            $this->assertSame(2, Person::query()->count());
        });
    }

    public function test_an_idempotency_key_cannot_be_reused_for_different_content(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $manager = app(IncomingRequestManager::class);
            $manager->receive([
                'idempotency_key' => 'site-a:submission-123',
                'message' => 'Original request',
            ]);

            $this->expectException(LogicException::class);

            $manager->receive([
                'idempotency_key' => 'site-a:submission-123',
                'message' => 'Different request',
            ]);
        });
    }

    public function test_a_request_follows_the_validated_workflow_and_records_history(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($organization);

        app(OrganizationContext::class)->run($organization, function () use ($member): void {
            $manager = app(IncomingRequestManager::class);
            $request = $manager->receive(['message' => 'Please contact me.']);

            $manager->markRead($request, $member);
            $manager->assign($request, $member, $member);
            $manager->transition($request, IncomingRequestStatus::InProgress, actor: $member);
            $manager->transition($request, IncomingRequestStatus::WaitingCustomer, actor: $member);
            $manager->transition($request, IncomingRequestStatus::Qualified, actor: $member);
            $manager->addNote($request, 'Need confirmed by the customer.', $member);
            $manager->transition(
                $request,
                IncomingRequestStatus::Closed,
                IncomingRequestOutcome::Converted,
                $member,
            );

            $request->refresh();
            $this->assertSame(IncomingRequestStatus::Closed, $request->status);
            $this->assertSame(IncomingRequestOutcome::Converted, $request->outcome);
            $this->assertNotNull($request->read_at);
            $this->assertNotNull($request->started_at);
            $this->assertNotNull($request->qualified_at);
            $this->assertNotNull($request->closed_at);
            $this->assertSame(
                $member->id,
                $request->activities()->where('event', 'assigned')->value('related_user_id'),
            );
            $this->assertSame(
                ['received', 'read', 'assigned', 'status_changed', 'status_changed', 'status_changed', 'note_added', 'status_changed'],
                $request->activities()->orderBy('id')->pluck('event')->all(),
            );
        });
    }

    public function test_closing_requires_an_outcome_and_closed_requests_cannot_transition(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $manager = app(IncomingRequestManager::class);
            $request = $manager->receive(['message' => 'A request']);

            try {
                $manager->transition($request, IncomingRequestStatus::Closed);
                $this->fail('Closing without an outcome should fail.');
            } catch (LogicException) {
                $this->assertSame(IncomingRequestStatus::New, $request->refresh()->status);
            }

            $manager->transition(
                $request,
                IncomingRequestStatus::Closed,
                IncomingRequestOutcome::Answered,
            );

            $this->expectException(LogicException::class);
            $manager->transition($request, IncomingRequestStatus::InProgress);
        });
    }

    public function test_linking_a_person_preserves_the_original_submission_snapshot(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $manager = app(IncomingRequestManager::class);
            $request = $manager->receive([
                'name_snapshot' => 'Original spelling',
                'email_snapshot' => 'original@example.test',
                'message' => 'A request',
            ]);
            $person = Person::query()->create(['display_name' => 'Corrected Name']);

            $manager->linkPerson($request, $person);
            $person->update(['display_name' => 'Updated Again']);

            $request->refresh();
            $this->assertSame($person->id, $request->person_id);
            $this->assertSame('Original spelling', $request->name_snapshot);
            $this->assertSame('original@example.test', $request->email_snapshot);
            $this->assertSame(
                $person->id,
                $request->activities()->where('event', 'person_linked')->value('related_person_id'),
            );
        });
    }

    public function test_an_incoming_request_cannot_be_deleted_directly(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            $request = app(IncomingRequestManager::class)->receive(['message' => 'A request']);

            $this->expectException(LogicException::class);

            $request->delete();
        });
    }
}
