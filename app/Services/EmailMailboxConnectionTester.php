<?php

namespace App\Services;

use App\Models\EmailMailbox;
use LogicException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

class EmailMailboxConnectionTester
{
    public function testImap(EmailMailbox $mailbox): void
    {
        $credentials = $mailbox->integration->credentials;
        foreach (['imap_host', 'imap_port', 'imap_username', 'imap_password'] as $key) {
            if (blank($credentials[$key] ?? null)) {
                throw new LogicException('La configuration IMAP de cette boîte est incomplète.');
            }
        }

        try {
            $client = (new ClientManager)->make([
                'host' => $credentials['imap_host'],
                'port' => (int) $credentials['imap_port'],
                'encryption' => 'ssl',
                'validate_cert' => true,
                'protocol' => 'imap',
                'username' => $credentials['imap_username'],
                'password' => $credentials['imap_password'],
                'authentication' => 'plain',
            ]);
            $client->connect();
            $client->disconnect();
        } catch (Throwable) {
            $this->fail($mailbox, 'Connexion IMAP refusée. Vérifie le serveur, le port et les identifiants.');
            throw new LogicException('Connexion IMAP refusée. Vérifie le serveur, le port et les identifiants.');
        }
        $mailbox->update(['status' => 'active', 'last_error_at' => null, 'last_error' => null]);
    }

    private function fail(EmailMailbox $mailbox, string $message): void
    {
        $mailbox->update(['status' => 'degraded', 'last_error_at' => now(), 'last_error' => $message]);
    }
}
