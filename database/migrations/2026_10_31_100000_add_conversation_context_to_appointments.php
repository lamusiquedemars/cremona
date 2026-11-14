<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('conversation_id')
                ->nullable()
                ->after('incoming_request_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(['organization_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'conversation_id']);
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};
