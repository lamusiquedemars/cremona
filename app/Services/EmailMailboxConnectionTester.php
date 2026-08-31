<?php

namespace App\Services;

use App\Models\EmailMailbox;
use LogicException;
use Throwable;

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

        $target = sprintf('{%s:%d/imap/ssl/novalidate-cert}%s', $credentials['imap_host'], $credentials['imap_port'], $mailbox->inbox_folder);
        $previous = set_error_handler(static fn (): bool => true);
        try {
            $connection = imap_open($target, $credentials['imap_username'], $credentials['imap_password'], OP_HALFOPEN, 1);
        } finally {
            restore_error_handler();
        }

        if ($connection === false) {
            $this->fail($mailbox, 'Connexion IMAP refusée. Vérifie le serveur, le port et les identifiants.');
            throw new LogicException('Connexion IMAP refusée. Vérifie le serveur, le port et les identifiants.');
        }

        imap_close($connection);
        $mailbox->update(['status' => 'active', 'last_error_at' => null, 'last_error' => null]);
    }

    private function fail(EmailMailbox $mailbox, string $message): void
    {
        $mailbox->update(['status' => 'degraded', 'last_error_at' => now(), 'last_error' => $message]);
    }
}
