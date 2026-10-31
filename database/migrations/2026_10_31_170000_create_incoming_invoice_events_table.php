<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_invoice_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('remote_id', 64);
            $table->string('status_code', 64)->nullable();
            $table->string('status_text')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['incoming_invoice_id', 'remote_id']);
            $table->index(['organization_id', 'incoming_invoice_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_invoice_events');
    }
};
