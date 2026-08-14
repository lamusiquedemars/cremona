<?php

namespace App\Services;

use App\Enums\ConsentStatus;
use App\Enums\ContactMethodType;
use App\Enums\IncomingRequestOutcome;
use App\Enums\IncomingRequestStatus;
use App\Exceptions\IdempotencyConflictException;
use App\Models\Company;
use App\Models\IncomingRequest;
use App\Models\Person;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class IncomingRequestManager
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * @param  array{
     *     idempotency_key?: ?string,
     *     source_channel?: string,
     *     source?: ?string,
     *     source_site_reference?: ?string,
     *     source_form_reference?: ?string,
     *     attribution_source?: ?string,
     *     attribution_medium?: ?string,
     *     attribution_campaign?: ?string,
     *     name_snapshot?: ?string,
     *     email_snapshot?: ?string,
     *     phone_snapshot?: ?string,
     *     subject?: ?string,
     *     message: string,
     *     category?: ?string,
     *     urgency?: string,
     *     important_date?: ?string,
     *     answers?: array<int, array{field_key: string, label_snapshot: string, value?: ?string, value_type?: string, position?: int}>,
     *     consent?: array{purpose: string, channel?: ?string, status: string, statement_snapshot: string, statement_version?: ?string, source?: ?string, granted_at?: mixed}
     * }  $attributes
     */
    public function receive(array $attributes): IncomingRequest
    {
        return DB::transaction(function () use ($attributes): IncomingRequest {
            $idempotencyKey = $this->clean($attributes['idempotency_key'] ?? null);
            $values = [
                'source_channel' => $attributes['source_channel'] ?? 'website',
                'source' => $this->clean($attributes['source'] ?? null),
                'source_site_reference' => $this->clean($attributes['source_site_reference'] ?? null),
                'source_form_reference' => $this->clean($attributes['source_form_reference'] ?? null),
                'attribution_source' => $this->clean($attributes['attribution_source'] ?? null),
                'attribution_medium' => $this->clean($attributes['attribution_medium'] ?? null),
                'attribution_campaign' => $this->clean($attributes['attribution_campaign'] ?? null),
                'name_snapshot' => $this->clean($attributes['name_snapshot'] ?? null),
                'email_snapshot' => $this->clean($attributes['email_snapshot'] ?? null),
                'phone_snapshot' => $this->clean($attributes['phone_snapshot'] ?? null),
                'subject' => $this->clean($attributes['subject'] ?? null),
                'message' => trim($attributes['message']),
                'category' => $this->clean($attributes['category'] ?? null),
                'urgency' => $attributes['urgency'] ?? 'unknown',
                'important_date' => $attributes['important_date'] ?? null,
                'status' => IncomingRequestStatus::New,
            ];

            if ($values['message'] === '') {
                throw new LogicException('An incoming request requires a message.');
            }

            $values['payload_fingerprint'] = hash('sha256', json_encode([
                'request' => $values,
                'answers' => collect($attributes['answers'] ?? [])
                    ->sortBy([
                        ['field_key', 'asc'],
                        ['position', 'asc'],
                    ])
                    ->values()
                    ->all(),
                'consent' => $attributes['consent'] ?? null,
            ], JSON_THROW_ON_ERROR));
            $values['received_at'] = now();

            $request = $idempotencyKey === null
                ? IncomingRequest::query()->create($values)
                : IncomingRequest::query()->firstOrCreate(
                    ['idempotency_key' => $idempotencyKey],
                    $values,
                );

            if (! $request->wasRecentlyCreated) {
                if (! hash_equals($request->payload_fingerprint, $values['payload_fingerprint'])) {
                    throw new IdempotencyConflictException(
                        'The idempotency key was already used for a different request payload.',
                    );
                }

                return $request;
            }

            foreach ($attributes['answers'] ?? [] as $answer) {
                $request->answers()->create([
                    'field_key' => $answer['field_key'],
                    'label_snapshot' => $answer['label_snapshot'],
                    'value_type' => $answer['value_type'] ?? 'text',
                    'value' => $answer['value'] ?? null,
                    'position' => $answer['position'] ?? 0,
                ]);
            }

            if (isset($attributes['consent'])) {
                $consent = $attributes['consent'];
                $status = ConsentStatus::from($consent['status']);
                $request->consents()->create([
                    'purpose' => $consent['purpose'],
                    'channel' => $consent['channel'] ?? 'unspecified',
                    'status' => $status,
                    'statement_snapshot' => $consent['statement_snapshot'],
                    'statement_version' => $consent['statement_version'] ?? null,
                    'source' => $consent['source'] ?? null,
                    'granted_at' => $status === ConsentStatus::Granted
                        ? ($consent['granted_at'] ?? now())
                        : null,
                ]);
            }

            $this->activity($request, 'received');

            return $request->load(['answers', 'consents', 'activities']);
        });
    }

    public function markRead(IncomingRequest $request, ?User $actor = null): void
    {
        $this->assertOwned($request);

        if ($request->read_at !== null) {
            return;
        }

        $request->update(['read_at' => now()]);
        $this->activity($request, 'read', actor: $actor);
    }

    public function transition(
        IncomingRequest $request,
        IncomingRequestStatus $status,
        ?IncomingRequestOutcome $outcome = null,
        ?User $actor = null,
    ): void {
        $this->assertOwned($request);
        $from = $request->status;

        if (! $from->canTransitionTo($status)) {
            throw new LogicException("Cannot transition an incoming request from {$from->value} to {$status->value}.");
        }

        if ($status === IncomingRequestStatus::Closed && $outcome === null) {
            throw new LogicException('Closing an incoming request requires an outcome.');
        }

        if ($status !== IncomingRequestStatus::Closed && $outcome !== null) {
            throw new LogicException('An outcome can only be set when closing an incoming request.');
        }

        $updates = ['status' => $status, 'outcome' => $outcome];

        if ($status === IncomingRequestStatus::InProgress && $request->started_at === null) {
            $updates['started_at'] = now();
        }

        if ($status === IncomingRequestStatus::Qualified && $request->qualified_at === null) {
            $updates['qualified_at'] = now();
        }

        if ($status === IncomingRequestStatus::Closed) {
            $updates['closed_at'] = now();
        }

        DB::transaction(function () use ($request, $updates, $from, $status, $actor): void {
            $request->update($updates);
            $this->activity($request, 'status_changed', $actor, from: $from, to: $status);
        });
    }

    public function assign(IncomingRequest $request, User $user, ?User $actor = null): void
    {
        $this->assertOwned($request);
        $organization = $this->context->require();

        if (! $user->is_platform_admin && ! $user->organizations()->whereKey($organization)->exists()) {
            throw new LogicException('The assignee is not a member of the active organization.');
        }

        $request->update(['assigned_user_id' => $user->getKey()]);
        $this->activity($request, 'assigned', $actor, relatedUser: $user);
    }

    public function linkPerson(IncomingRequest $request, Person $person, ?User $actor = null): void
    {
        $this->assertOwned($request);
        $this->assertRelatedOrganization($person->organization_id);

        if ($request->person_id === $person->getKey()) {
            return;
        }

        if ($request->person_id !== null) {
            throw new LogicException('The incoming request is already linked to a person.');
        }

        $request->update(['person_id' => $person->getKey()]);
        $this->activity($request, 'person_linked', $actor, relatedPerson: $person);
    }

    /**
     * @param  array{display_name: string, first_name?: ?string, last_name?: ?string, email?: ?string, phone?: ?string}  $attributes
     */
    public function createPersonFromRequest(
        IncomingRequest $request,
        array $attributes,
        ?User $actor = null,
    ): Person {
        $this->assertOwned($request);

        return DB::transaction(function () use ($request, $attributes, $actor): Person {
            $lockedRequest = IncomingRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->person_id !== null) {
                throw new LogicException('The incoming request is already linked to a person.');
            }

            $person = Person::query()->create([
                'display_name' => $this->clean($attributes['display_name'])
                    ?? throw new LogicException('A person requires a display name.'),
                'first_name' => $this->clean($attributes['first_name'] ?? null),
                'last_name' => $this->clean($attributes['last_name'] ?? null),
                'source' => str($lockedRequest->source ?? $lockedRequest->source_channel)
                    ->limit(40, '')
                    ->toString(),
            ]);

            foreach ([
                ContactMethodType::Email->value => $this->clean($attributes['email'] ?? null),
                ContactMethodType::Phone->value => $this->clean($attributes['phone'] ?? null),
            ] as $type => $value) {
                if ($value === null) {
                    continue;
                }

                $person->contactMethods()->create([
                    'type' => $type,
                    'label' => 'Demande initiale',
                    'value' => $value,
                    'is_primary' => true,
                ]);
            }

            $lockedRequest->update(['person_id' => $person->getKey()]);
            $this->activity($lockedRequest, 'person_created_and_linked', $actor, relatedPerson: $person);
            $request->setRawAttributes($lockedRequest->getAttributes(), true);

            return $person->load('contactMethods');
        });
    }

    public function linkCompany(IncomingRequest $request, Company $company, ?User $actor = null): void
    {
        $this->assertOwned($request);
        $this->assertRelatedOrganization($company->organization_id);

        if ($request->company_id === $company->getKey()) {
            return;
        }

        if ($request->company_id !== null) {
            throw new LogicException('The incoming request is already linked to a company.');
        }

        $request->update(['company_id' => $company->getKey()]);
        $this->activity($request, 'company_linked', $actor, relatedCompany: $company);
    }

    public function addNote(IncomingRequest $request, string $body, User $actor): void
    {
        $this->assertOwned($request);
        $body = trim($body);

        if ($body === '') {
            throw new LogicException('A note cannot be empty.');
        }

        $this->activity($request, 'note_added', $actor, body: $body);
    }

    private function activity(
        IncomingRequest $request,
        string $event,
        ?User $actor = null,
        ?string $body = null,
        ?IncomingRequestStatus $from = null,
        ?IncomingRequestStatus $to = null,
        ?User $relatedUser = null,
        ?Person $relatedPerson = null,
        ?Company $relatedCompany = null,
    ): void {
        if ($actor !== null) {
            $organization = $this->context->require();

            if (! $actor->is_platform_admin && ! $actor->organizations()->whereKey($organization)->exists()) {
                throw new LogicException('The actor is not a member of the active organization.');
            }
        }

        $recordedAt = now();

        $request->activities()->create([
            'actor_user_id' => $actor?->getKey(),
            'related_user_id' => $relatedUser?->getKey(),
            'related_person_id' => $relatedPerson?->getKey(),
            'related_company_id' => $relatedCompany?->getKey(),
            'event' => $event,
            'from_status' => $from,
            'to_status' => $to,
            'body' => $body,
            'recorded_at' => $recordedAt,
        ]);

        if ($request->person_id !== null) {
            Person::query()
                ->whereKey($request->person_id)
                ->update(['last_activity_at' => $recordedAt]);
        }

        if ($request->company_id !== null) {
            Company::query()
                ->whereKey($request->company_id)
                ->update(['last_activity_at' => $recordedAt]);
        }
    }

    private function assertOwned(IncomingRequest $request): void
    {
        $this->assertRelatedOrganization($request->organization_id);
    }

    private function assertRelatedOrganization(int $organizationId): void
    {
        if ($organizationId !== $this->context->require()->getKey()) {
            throw new LogicException('The record does not belong to the active organization.');
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
