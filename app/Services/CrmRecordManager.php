<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Person;
use App\Tenancy\OrganizationContext;
use LogicException;

class CrmRecordManager
{
    public function __construct(private readonly OrganizationContext $context) {}

    public function archive(Person|Company $record): void
    {
        $this->assertOwned($record);

        if ($record->status === 'archived') {
            return;
        }

        $record->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);
    }

    public function reactivate(Person|Company $record): void
    {
        $this->assertOwned($record);

        if ($record->status === 'active') {
            return;
        }

        $record->update([
            'status' => 'active',
            'archived_at' => null,
        ]);
    }

    private function assertOwned(Person|Company $record): void
    {
        if ((int) $record->organization_id !== $this->context->require()->getKey()) {
            throw new LogicException('The CRM record does not belong to the active organization.');
        }
    }
}
