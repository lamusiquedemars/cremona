<?php

namespace App\Console\Commands;

use App\Models\InvoiceTransmission;
use App\Services\SuperPdpTransmissionManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

class SynchronizeElectronicInvoiceStatuses extends Command
{
    protected $signature = 'cremona:sync-electronic-invoice-statuses';

    protected $description = 'Synchronise les accusés de traitement des factures déjà transmises.';

    public function handle(SuperPdpTransmissionManager $manager, OrganizationContext $context): int
    {
        $transmissions = InvoiceTransmission::withoutGlobalScopes()
            ->with(['invoice', 'organization'])
            ->where('provider', 'super_pdp')->where('direction', 'outbound')->where('status', 'submitted')->get();
        $failures = 0;

        foreach ($transmissions as $transmission) {
            if ($transmission->organization === null || $transmission->organization->status !== 'active' || $transmission->invoice === null) {
                continue;
            }
            try {
                $updated = $context->run($transmission->organization, fn () => $manager->synchronize($transmission->invoice));
                $this->line("{$transmission->organization->name}: {$updated->status}");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$transmission->organization->name}: synchronisation impossible.");
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
