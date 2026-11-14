<?php

use App\Models\Organization;
use App\Services\WorkshopReferenceManager;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotes', 'workshop_orders'] as $name) {
            if (! Schema::hasColumn($name, 'previous_reference')) {
                Schema::table($name, fn (Blueprint $table) => $table->string('previous_reference', 80)->nullable()->index());
            }
        }

        Organization::query()->each(function (Organization $organization): void {
            app(OrganizationContext::class)->run($organization, fn () => app(WorkshopReferenceManager::class)->reconcile());
        });
    }

    public function down(): void
    {
        // Keep the reference history and reserved numbers when rolling back code.
    }
};
