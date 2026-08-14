<?php

namespace App\Services;

use App\Models\OrganizationAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(
        string $event,
        ?Model $subject = null,
        ?User $actor = null,
        array $metadata = [],
        ?Request $request = null,
    ): OrganizationAuditLog {
        return OrganizationAuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $this->sanitize($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (preg_match('/secret|token|password|credential|api_key/i', (string) $key)) {
                $metadata[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->sanitize($value);
            }
        }

        return $metadata;
    }
}
