<?php

namespace App\Filament\Resources\EmailMailboxes\Pages;

use App\Filament\Pages\BusinessEditRecord;
use App\Filament\Resources\EmailMailboxes\EmailMailboxResource;
use App\Services\OrganizationIntegrationManager;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EditEmailMailbox extends BusinessEditRecord
{
    protected static string $resource = EmailMailboxResource::class;

    /** @var array<string, mixed>|null */
    private ?array $credentialsToPersist = null;

    public function getTitle(): string|Htmlable
    {
        return 'Boîte email '.$this->record->address;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            $credentials = $this->record->integration->credentials;
        } catch (DecryptException) {
            // An encrypted secret made with an older application key cannot be
            // recovered. Keep every credential empty so the administrator can
            // replace it explicitly, without ever exposing ciphertext.
            $credentials = [];
        }

        foreach (['imap_host', 'imap_port', 'imap_username', 'smtp_host', 'smtp_port', 'smtp_username'] as $key) {
            $data[$key] = $credentials[$key] ?? null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $submitted = Arr::only($data, [
            'imap_host', 'imap_port', 'imap_username', 'imap_password',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
        ]);

        try {
            $credentials = $this->record->integration->credentials;
        } catch (DecryptException) {
            $credentials = [];
        }

        foreach ($submitted as $key => $value) {
            if (filled($value)) {
                $credentials[$key] = $value;
            }
        }

        $missing = collect([
            'imap_host' => 'Serveur IMAP',
            'imap_port' => 'Port IMAP',
            'imap_username' => 'Identifiant IMAP',
            'imap_password' => 'Mot de passe IMAP',
            'smtp_host' => 'Serveur SMTP',
            'smtp_port' => 'Port SMTP',
            'smtp_username' => 'Identifiant SMTP',
            'smtp_password' => 'Mot de passe SMTP',
        ])->filter(fn (string $label, string $key): bool => blank($credentials[$key] ?? null));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(
                $missing->mapWithKeys(fn (string $label, string $key): array => [
                    $key => "{$label} doit être renseigné pour remplacer la configuration existante.",
                ])->all(),
            );
        }

        $this->credentialsToPersist = $credentials;

        return Arr::except($data, array_keys($submitted));
    }

    protected function afterSave(): void
    {
        if ($this->credentialsToPersist === null) {
            return;
        }

        app(OrganizationIntegrationManager::class)->replaceCredentials(
            $this->record->integration,
            $this->credentialsToPersist,
            auth()->user(),
        );
    }
}
