<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_requests', function (Blueprint $table): void {
            $table->json('attribution_first_touch')->nullable()->after('attribution_campaign');
            $table->json('attribution_last_touch')->nullable()->after('attribution_first_touch');
            $table->string('attribution_method', 32)->nullable()->after('attribution_last_touch');
            $table->decimal('attribution_confidence', 3, 2)->nullable()->after('attribution_method');
            $table->decimal('commercial_value', 14, 2)->nullable()->after('outcome');
            $table->char('commercial_currency', 3)->nullable()->after('commercial_value');
            $table->string('lost_reason')->nullable()->after('commercial_currency');
            $table->timestamp('converted_at')->nullable()->after('qualified_at');

            $table->index(
                ['organization_id', 'attribution_source', 'received_at'],
                'incoming_requests_acquisition_source_index',
            );
            $table->index(
                ['organization_id', 'outcome', 'converted_at'],
                'incoming_requests_conversion_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('incoming_requests', function (Blueprint $table): void {
            $table->dropIndex('incoming_requests_acquisition_source_index');
            $table->dropIndex('incoming_requests_conversion_index');
            $table->dropColumn([
                'attribution_first_touch',
                'attribution_last_touch',
                'attribution_method',
                'attribution_confidence',
                'commercial_value',
                'commercial_currency',
                'lost_reason',
                'converted_at',
            ]);
        });
    }
};
