<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_id')->unique()->constrained()->restrictOnDelete();
            $table->date('returned_on');
            $table->text('accessories_state')->nullable();
            $table->text('condition_notes')->nullable();
            $table->decimal('charge_amount', 10, 2)->default(0);
            $table->text('charge_note')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['organization_id', 'returned_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_returns');
    }
};
