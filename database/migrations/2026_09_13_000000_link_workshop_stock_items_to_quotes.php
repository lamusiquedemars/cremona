<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_order_stock_items', function (Blueprint $table): void {
            $table->decimal('unit_amount', 12, 2)->default(0)->after('quantity');
            $table->boolean('include_in_quote')->default(false)->after('unit_amount');
        });

        Schema::table('quote_lines', function (Blueprint $table): void {
            $table->foreignId('workshop_order_stock_item_id')
                ->nullable()
                ->after('workshop_order_service_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique(['quote_id', 'workshop_order_stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table): void {
            $table->dropUnique(['quote_id', 'workshop_order_stock_item_id']);
            $table->dropConstrainedForeignId('workshop_order_stock_item_id');
        });

        Schema::table('workshop_order_stock_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_amount', 'include_in_quote']);
        });
    }
};
