<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->foreignId('email_mailbox_id')->nullable()->after('conversation_id')
                ->constrained('email_mailboxes')->nullOnDelete();
            $table->index(['organization_id', 'email_mailbox_id'], 'conversation_message_mailbox_index');
        });

        Schema::create('email_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('remote_name', 255);
            $table->string('role', 16);
            $table->string('uid_validity', 64)->nullable();
            $table->unsignedBigInteger('last_uid')->nullable();
            $table->string('sync_status', 16)->default('idle');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'email_mailbox_id', 'remote_name'], 'email_folder_name_unique');
        });

        Schema::create('email_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_mailbox_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('folders_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'email_mailbox_id', 'created_at'], 'email_sync_run_mailbox_index');
        });

        Schema::create('email_message_copies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('uid_validity', 64)->nullable();
            $table->unsignedBigInteger('uid');
            $table->timestamp('synchronized_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'email_folder_id', 'uid'], 'email_message_copy_uid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_message_copies');
        Schema::dropIfExists('email_sync_runs');
        Schema::dropIfExists('email_folders');

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropIndex('conversation_message_mailbox_index');
            $table->dropConstrainedForeignId('email_mailbox_id');
        });
    }
};
