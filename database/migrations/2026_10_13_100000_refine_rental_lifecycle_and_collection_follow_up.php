<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rentals')->where('status', 'draft')->update(['status' => 'preparing']);
        DB::table('rentals')->where('status', 'active')->update(['status' => 'handed_over']);

        Schema::table('rentals', function (Blueprint $table): void {
            $table->string('collection_status', 24)->default('not_applicable')->after('status');
            $table->text('collection_note')->nullable()->after('collection_status');
            $table->timestamp('collection_handled_at')->nullable()->after('collection_note');
            $table->index(['organization_id', 'status', 'collection_status'], 'rentals_lifecycle_collection_idx');
        });

        DB::table('rentals')->where('status', 'handed_over')->update(['collection_status' => 'to_set_up']);
    }

    public function down(): void
    {
        DB::table('rentals')->where('status', 'preparing')->update(['status' => 'draft']);
        DB::table('rentals')->where('status', 'handed_over')->update(['status' => 'active']);

        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropIndex('rentals_lifecycle_collection_idx');
            $table->dropColumn(['collection_status', 'collection_note', 'collection_handled_at']);
        });
    }
};
