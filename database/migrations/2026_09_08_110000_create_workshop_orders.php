<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('reference', 80);
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('incoming_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instrument_description')->nullable();
            $table->text('customer_instructions')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('status', 32)->default('received');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_orders');
    }
};
