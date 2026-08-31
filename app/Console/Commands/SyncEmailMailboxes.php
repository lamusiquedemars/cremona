<?php

namespace App\Console\Commands;

use App\Models\EmailMailbox;
use App\Services\EmailMailboxSynchronizer;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

class SyncEmailMailboxes extends Command
{
    protected $signature = 'cremona:sync-email-mailboxes';

    protected $description = 'Relève les boîtes email actives, en lecture seule et sans doublon.';

    public function handle(EmailMailboxSynchronizer $synchronizer, OrganizationContext $context): int
    {
        $mailboxes = EmailMailbox::withoutGlobalScopes()->with('organization')->where('status', 'active')->get();
        $failures = 0;

        foreach ($mailboxes as $mailbox) {
            if ($mailbox->organization === null || $mailbox->organization->status !== 'active') {
                continue;
            }

            try {
                $result = $context->run(
                    $mailbox->organization,
                    fn (): array => $synchronizer->sync($mailbox),
                );
                $this->line("{$mailbox->organization->name}: {$result['imported']} message(s) importé(s), {$result['skipped']} déjà connu(s).");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$mailbox->organization->name}: relève impossible. Consulte Boîtes email pour le détail.");
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
