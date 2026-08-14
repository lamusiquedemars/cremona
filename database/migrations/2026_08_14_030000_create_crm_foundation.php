<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('locale', 16)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('source', 40)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'display_name']);
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('industry')->nullable();
            $table->string('source', 40)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('company_person', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('job_title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'company_id', 'person_id']);
        });

        Schema::create('contact_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('contactable_type', 32);
            $table->unsignedBigInteger('contactable_id');
            $table->string('type', 16);
            $table->string('label')->nullable();
            $table->string('value');
            $table->string('normalized_value')->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('deliverability_status', 32)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(
                ['organization_id', 'contactable_type', 'contactable_id'],
                'contact_methods_owner_index',
            );
            $table->index(['organization_id', 'type', 'normalized_value']);
            $table->unique(
                ['organization_id', 'contactable_type', 'contactable_id', 'type', 'normalized_value'],
                'contact_methods_owner_value_unique',
            );
        });

        Schema::create('incoming_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('idempotency_key')->nullable();
            $table->string('payload_fingerprint', 64);
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('source_channel', 32)->default('website');
            $table->string('source', 64)->nullable();
            $table->string('source_site_reference')->nullable();
            $table->string('source_form_reference')->nullable();
            $table->string('attribution_source')->nullable();
            $table->string('attribution_medium')->nullable();
            $table->string('attribution_campaign')->nullable();
            $table->string('name_snapshot')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->string('phone_snapshot')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('category')->nullable();
            $table->string('urgency', 24)->default('unknown');
            $table->date('important_date')->nullable();
            $table->string('status', 32)->default('new');
            $table->string('outcome', 32)->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['organization_id', 'status', 'received_at']);
            $table->index(['organization_id', 'assigned_user_id', 'status']);
        });

        Schema::create('incoming_request_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_request_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label_snapshot');
            $table->string('value_type', 24)->default('text');
            $table->text('value')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(
                ['organization_id', 'incoming_request_id', 'field_key', 'position'],
                'incoming_request_answer_unique',
            );
        });

        Schema::create('incoming_request_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_request_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 64);
            $table->string('channel', 32)->default('unspecified');
            $table->string('status', 24)->default('unknown');
            $table->text('statement_snapshot');
            $table->string('statement_version')->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'incoming_request_id', 'purpose', 'channel'],
                'incoming_request_consent_unique',
            );
        });

        Schema::create('incoming_request_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incoming_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('related_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('event', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            $table->index(['organization_id', 'incoming_request_id', 'recorded_at'], 'request_activity_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_request_activities');
        Schema::dropIfExists('incoming_request_consents');
        Schema::dropIfExists('incoming_request_answers');
        Schema::dropIfExists('incoming_requests');
        Schema::dropIfExists('contact_methods');
        Schema::dropIfExists('company_person');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('people');
    }
};
