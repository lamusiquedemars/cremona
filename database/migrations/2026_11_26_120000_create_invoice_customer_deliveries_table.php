<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_customer_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_email');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['organization_id', 'invoice_id', 'sent_at'], 'invoice_customer_delivery_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_customer_deliveries');
    }
};
