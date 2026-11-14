<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $t): void {
            $t->foreignId('workshop_order_id')->nullable()->after('incoming_request_id')->constrained()->nullOnDelete();
        });
        Schema::table('quote_lines', function (Blueprint $t): void {
            $t->foreignId('workshop_order_service_id')->nullable()->after('quote_id')->constrained()->nullOnDelete();
            $t->unique(['quote_id', 'workshop_order_service_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $t): void {
            $t->dropUnique(['quote_id', 'workshop_order_service_id']);
            $t->dropConstrainedForeignId('workshop_order_service_id');
        });
        Schema::table('quotes', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('workshop_order_id');
        });
    }
};
