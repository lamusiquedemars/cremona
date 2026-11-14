<?php

namespace App\Services;

use App\Enums\AppointmentModality;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\OrganizationIntegration;
use App\Models\User;
use Carbon\CarbonImmutable;

class BrevoMeetingSynchronizer
{
    public function __construct(private readonly ContactMatcher $contactMatcher) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function synchronize(
        string $event,
        array $payload,
        OrganizationIntegration $integration,
    ): Appointment {
        $participantEmails = collect($payload['event_participants'])
            ->pluck('EMAIL')
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->sort()
            ->values();
        $externalReference = hash('sha256', json_encode([
            'account_email' => mb_strtolower(trim($payload['account_email'])),
            'participants' => $participantEmails->all(),
            'name' => trim($payload['meeting_name']),
            'starts_at' => CarbonImmutable::parse($payload['meeting_start_timestamp'])->utc()->toIso8601String(),
            'ends_at' => CarbonImmutable::parse($payload['meeting_end_timestamp'])->utc()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        $personMatches = $this->contactMatcher->suggestPeople($participantEmails->first(), null);
        $person = $personMatches->count() === 1 ? $personMatches->first() : null;
        $assignedUser = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($payload['account_email']))])
            ->whereHas('organizations', fn ($query) => $query->whereKey($integration->organization_id))
            ->first();
        $assignedUser ??= filled($integration->credentials['default_assigned_user_id'] ?? null)
            ? User::query()->whereKey($integration->credentials['default_assigned_user_id'])->whereHas('organizations', fn ($query) => $query->whereKey($integration->organization_id))->first()
            : null;
        $address = trim((string) ($payload['meeting_address'] ?? '')) ?: null;
        $location = trim((string) ($payload['meeting_location'] ?? '')) ?: null;
        $meetingName = trim($payload['meeting_name']);
        $appointmentType = AppointmentType::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('brevo_meeting_name', $meetingName)
                ->orWhere(fn ($query) => $query->whereNull('brevo_meeting_name')->where('label', $meetingName)))
            ->first();

        $appointment = Appointment::query()->firstOrNew([
            'provider' => 'brevo',
            'external_reference' => $externalReference,
        ]);
        $status = $appointment->exists && $appointment->status === AppointmentStatus::Cancelled
            ? AppointmentStatus::Cancelled
            : ($event === 'cancelled' ? AppointmentStatus::Cancelled : AppointmentStatus::Scheduled);

        $appointment->fill([
            'assigned_user_id' => $assignedUser?->id,
            'person_id' => $person?->id,
            'appointment_type_id' => $appointmentType?->id,
            'title' => $meetingName,
            'status' => $status,
            'starts_at' => CarbonImmutable::parse($payload['meeting_start_timestamp'])->utc(),
            'ends_at' => CarbonImmutable::parse($payload['meeting_end_timestamp'])->utc(),
            'timezone' => $integration->credentials['timezone'] ?? 'UTC',
            'modality' => $this->modality($address, $location),
            'location' => $address ?? $location,
            'description' => null,
        ])->save();

        return $appointment;
    }

    private function modality(?string $address, ?string $location): AppointmentModality
    {
        if ($address !== null) {
            return AppointmentModality::InPerson;
        }

        $normalized = mb_strtolower((string) $location);

        if (str_contains($normalized, 'phone') || str_contains($normalized, 'téléphone')) {
            return AppointmentModality::Phone;
        }

        if (str_contains($normalized, 'video') || str_contains($normalized, 'zoom')
            || str_contains($normalized, 'meet') || str_contains($normalized, 'brevo')) {
            return AppointmentModality::Video;
        }

        return AppointmentModality::Other;
    }
}
