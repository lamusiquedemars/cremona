<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->unsignedBigInteger('remote_id');
            $table->string('reference', 120)->nullable();
            $table->string('supplier_name')->nullable();
            $table->date('issued_on')->nullable();
            $table->string('status', 64)->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('original_private_document_id')->nullable()->constrained('private_documents')->nullOnDelete();
            $table->timestamp('synchronized_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'provider', 'remote_id']);
            $table->index(['organization_id', 'issued_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_invoices');
    }
};
