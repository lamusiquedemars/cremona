<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('incoming_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('initial_channel', 24)->default('website');
            $table->string('subject')->nullable();
            $table->string('normalized_subject')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'last_message_at'], 'conversation_inbox_index');
            $table->index(['organization_id', 'person_id', 'last_message_at'], 'conversation_person_timeline');
            $table->index(['organization_id', 'normalized_subject'], 'conversation_subject_index');
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 16);
            $table->string('channel', 24);
            $table->string('subject')->nullable();
            $table->longText('body_text');
            $table->longText('body_html_sanitized')->nullable();
            $table->string('message_id', 998)->nullable();
            $table->string('canonical_message_id', 998)->nullable();
            $table->string('message_id_hash', 64)->nullable();
            $table->string('in_reply_to', 998)->nullable();
            $table->string('canonical_in_reply_to', 998)->nullable();
            $table->string('in_reply_to_hash', 64)->nullable();
            $table->string('transport_status', 24);
            $table->string('threading_status', 24)->default('pending');
            $table->string('idempotency_key')->nullable();
            $table->string('payload_fingerprint', 64);
            $table->timestamp('authored_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key'], 'conversation_message_idempotency_unique');
            $table->index(['organization_id', 'message_id_hash'], 'conversation_message_rfc_id_index');
            $table->index(['organization_id', 'in_reply_to_hash'], 'conversation_message_reply_index');
            $table->index(['organization_id', 'conversation_id', 'authored_at'], 'conversation_message_timeline');
            $table->index(['organization_id', 'threading_status', 'created_at'], 'conversation_message_threading_queue');
        });

        Schema::create('conversation_user_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'conversation_id', 'user_id'], 'conversation_user_state_unique');
        });

        Schema::create('message_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 16);
            $table->string('name')->nullable();
            $table->string('address');
            $table->string('normalized_address')->index();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'conversation_message_id', 'role', 'normalized_address', 'position'], 'message_participant_unique');
            $table->index(['organization_id', 'normalized_address'], 'message_participant_address_index');
        });

        Schema::create('message_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 998);
            $table->string('canonical_reference', 998);
            $table->string('reference_hash', 64);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'conversation_message_id', 'reference_hash', 'position'], 'message_reference_unique');
            $table->index(['organization_id', 'reference_hash'], 'message_reference_lookup');
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('declared_mime_type')->nullable();
            $table->string('detected_mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('content_id')->nullable();
            $table->string('disposition', 16)->default('attachment');
            $table->string('scan_status', 24)->default('pending');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'conversation_message_id', 'sha256'], 'message_attachment_unique');
        });

        Schema::create('message_thread_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->decimal('confidence', 5, 4);
            $table->string('reason', 64);
            $table->string('status', 16)->default('proposed');
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'conversation_message_id', 'conversation_id'], 'message_thread_candidate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_thread_candidates');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_references');
        Schema::dropIfExists('message_participants');
        Schema::dropIfExists('conversation_user_states');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
