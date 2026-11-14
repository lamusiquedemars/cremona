<?php

use App\Models\Organization;
use App\Models\Quote;
use App\Services\QuoteWorkshopSynchronizer;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Organization::query()->each(function (Organization $organization): void {
            app(OrganizationContext::class)->run($organization, function (): void {
                Quote::query()->whereNotNull('workshop_order_id')->each(function (Quote $quote): void {
                    app(QuoteWorkshopSynchronizer::class)->sync($quote);
                });
            });
        });
    }

    public function down(): void
    {
        // Customer assignments and recorded approvals are business data to preserve.
    }
};
