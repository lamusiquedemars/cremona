<?php

namespace App\Filament\Resources\EmailMailboxes\Pages;

use App\Filament\Resources\EmailMailboxes\EmailMailboxResource;
use App\Models\EmailMailbox;
use App\Services\OrganizationIntegrationManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmailMailbox extends CreateRecord
{
    protected static string $resource = EmailMailboxResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $credentials = collect($data)->only(['imap_host', 'imap_port', 'imap_username', 'imap_password', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'])->all();
        $integration = app(OrganizationIntegrationManager::class)->configure('email', 'mailbox', $credentials, auth()->user());

        return EmailMailbox::query()->create([
            'organization_integration_id' => $integration->getKey(),
            'address' => $data['address'], 'display_name' => $data['display_name'] ?? null,
            'inbox_folder' => $data['inbox_folder'], 'sent_folder' => $data['sent_folder'] ?? null,
        ]);
    }
}
