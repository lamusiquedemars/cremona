<?php

namespace App\Http\Requests;

use App\Models\OrganizationIntegration;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrevoMeetingWebhook extends FormRequest
{
    public function authorize(): bool
    {
        $integration = $this->attributes->get('organization_integration');

        return $integration instanceof OrganizationIntegration
            && $integration->provider === 'brevo'
            && $integration->name === 'meetings';
    }

    public function rules(): array
    {
        return [
            'account_email' => ['required', 'email:rfc', 'max:255'],
            'event_participants' => ['required', 'array', 'min:1', 'max:15'],
            'event_participants.*.EMAIL' => ['required', 'email:rfc', 'max:255'],
            'event_participants.*.FIRSTNAME' => ['nullable', 'string', 'max:255'],
            'event_participants.*.LASTNAME' => ['nullable', 'string', 'max:255'],
            'meeting_name' => ['required', 'string', 'max:255'],
            'meeting_start_timestamp' => ['required', 'date'],
            'meeting_end_timestamp' => ['required', 'date', 'after:meeting_start_timestamp'],
            'meeting_location' => ['nullable', 'string', 'max:255'],
            'meeting_address' => ['nullable', 'string', 'max:255'],
            'meeting_notes' => ['nullable', 'string', 'max:100000'],
            'questions_and_answers' => ['sometimes', 'array', 'max:100'],
        ];
    }
}
