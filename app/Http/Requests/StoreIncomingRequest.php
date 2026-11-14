<?php

namespace App\Http\Requests;

use App\Enums\ConsentStatus;
use App\Enums\IncomingRequestUrgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('organization_integration');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:255'],
            'source' => ['required', 'array'],
            'source.channel' => ['required', 'string', 'max:32'],
            'source.name' => ['nullable', 'string', 'max:64'],
            'source.site_reference' => ['nullable', 'string', 'max:255'],
            'source.form_reference' => ['nullable', 'string', 'max:255'],
            'attribution' => ['sometimes', 'array'],
            'attribution.source' => ['nullable', 'string', 'max:255'],
            'attribution.medium' => ['nullable', 'string', 'max:255'],
            'attribution.campaign' => ['nullable', 'string', 'max:255'],
            'attribution.method' => ['nullable', 'string', 'max:32'],
            'attribution.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'attribution.first_touch' => ['nullable', 'array'],
            'attribution.last_touch' => ['nullable', 'array'],
            ...$this->touchRules('attribution.first_touch'),
            ...$this->touchRules('attribution.last_touch'),
            'contact' => ['sometimes', 'array'],
            'contact.name' => ['nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email:rfc', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:255'],
            'request' => ['required', 'array'],
            'request.subject' => ['nullable', 'string', 'max:255'],
            'request.message' => ['required', 'string', 'max:100000'],
            'request.category' => ['nullable', 'string', 'max:255'],
            'request.urgency' => ['sometimes', Rule::enum(IncomingRequestUrgency::class)],
            'request.important_date' => ['nullable', 'date_format:Y-m-d'],
            'answers' => ['sometimes', 'array', 'max:100'],
            'answers.*.field_key' => ['required', 'string', 'max:255'],
            'answers.*.label' => ['required', 'string', 'max:255'],
            'answers.*.value' => ['nullable', 'string', 'max:100000'],
            'answers.*.value_type' => ['sometimes', 'string', 'max:24'],
            'answers.*.position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'consent' => ['sometimes', 'array'],
            'consent.purpose' => ['required_with:consent', 'string', 'max:64'],
            'consent.channel' => ['nullable', 'string', 'max:32'],
            'consent.status' => ['required_with:consent', Rule::enum(ConsentStatus::class)],
            'consent.statement' => ['required_with:consent', 'string', 'max:100000'],
            'consent.statement_version' => ['nullable', 'string', 'max:255'],
            'consent.source' => ['nullable', 'string', 'max:64'],
            'consent.granted_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function touchRules(string $prefix): array
    {
        return [
            "{$prefix}.utm_source" => ['nullable', 'string', 'max:255'],
            "{$prefix}.utm_medium" => ['nullable', 'string', 'max:255'],
            "{$prefix}.utm_campaign" => ['nullable', 'string', 'max:255'],
            "{$prefix}.utm_term" => ['nullable', 'string', 'max:255'],
            "{$prefix}.utm_content" => ['nullable', 'string', 'max:255'],
            "{$prefix}.gclid" => ['nullable', 'string', 'max:255'],
            "{$prefix}.gbraid" => ['nullable', 'string', 'max:255'],
            "{$prefix}.wbraid" => ['nullable', 'string', 'max:255'],
            "{$prefix}.landing_page" => ['nullable', 'string', 'max:2048'],
            "{$prefix}.referrer" => ['nullable', 'string', 'max:2048'],
            "{$prefix}.captured_at" => ['nullable', 'date'],
        ];
    }
}
