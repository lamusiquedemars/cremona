<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_acceptance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->constrained()->restrictOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_email');
            $table->char('token_hash', 64)->unique();
            $table->string('status', 24)->default('created');
            $table->text('consent_text');
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('accepted_name')->nullable();
            $table->string('accepted_ip_address', 45)->nullable();
            $table->text('accepted_user_agent')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'rental_id', 'status'], 'rental_acceptance_request_status_idx');
        });

        Schema::create('rental_acceptance_request_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rental_acceptance_request_id');
            $table->foreign('rental_acceptance_request_id', 'rental_acceptance_document_request_fk')->references('id')->on('rental_acceptance_requests')->cascadeOnDelete();
            $table->foreignId('rental_document_id')->constrained()->restrictOnDelete();
            $table->char('document_sha256', 64);
            $table->timestamps();
            $table->unique(['rental_acceptance_request_id', 'rental_document_id'], 'rental_acceptance_document_unique');
        });

        Schema::create('rental_acceptance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('request_id');
            $table->foreign('request_id', 'rental_acceptance_event_request_fk')->references('id')->on('rental_acceptance_requests')->cascadeOnDelete();
            $table->string('event', 32);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'request_id', 'created_at'], 'rental_acceptance_event_request_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_acceptance_events');
        Schema::dropIfExists('rental_acceptance_request_documents');
        Schema::dropIfExists('rental_acceptance_requests');
    }
};
