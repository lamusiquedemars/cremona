<?php

namespace App\Services;

use App\Models\EmailMailbox;
use LogicException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
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

    public function testSmtp(EmailMailbox $mailbox): void
    {
        $credentials = $mailbox->integration->credentials;
        foreach (['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'] as $key) {
            if (blank($credentials[$key] ?? null)) {
                throw new LogicException('La configuration SMTP de cette boîte est incomplète.');
            }
        }

        try {
            $transport = new EsmtpTransport(
                $credentials['smtp_host'],
                (int) $credentials['smtp_port'],
                (int) $credentials['smtp_port'] === 465,
            );
            $transport->setUsername($credentials['smtp_username']);
            $transport->setPassword($credentials['smtp_password']);
            $transport->start();
            $transport->stop();
        } catch (Throwable) {
            $this->fail($mailbox, 'Connexion SMTP refusée. Vérifie le serveur, le port et les identifiants.');
            throw new LogicException('Connexion SMTP refusée. Vérifie le serveur, le port et les identifiants.');
        }

        $mailbox->update(['status' => 'active', 'last_error_at' => null, 'last_error' => null]);
    }

    private function fail(EmailMailbox $mailbox, string $message): void
    {
        $mailbox->update(['status' => 'degraded', 'last_error_at' => now(), 'last_error' => $message]);
    }
}
