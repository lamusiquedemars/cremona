<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_mailboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_integration_id')->constrained()->cascadeOnDelete();
            $table->string('address');
            $table->string('display_name')->nullable();
            $table->string('status', 24)->default('paused');
            $table->string('inbox_folder')->default('INBOX');
            $table->string('sent_folder')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'address'], 'email_mailbox_address_unique');
            $table->unique(['organization_id', 'organization_integration_id'], 'email_mailbox_integration_unique');
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_mailboxes');
    }
};
