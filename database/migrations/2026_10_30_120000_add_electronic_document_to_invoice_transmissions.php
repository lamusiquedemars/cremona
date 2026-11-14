<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_transmissions', function (Blueprint $table): void {
            $table->foreignId('electronic_private_document_id')
                ->nullable()
                ->after('payload_sha256')
                ->constrained('private_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_transmissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('electronic_private_document_id');
        });
    }
};
