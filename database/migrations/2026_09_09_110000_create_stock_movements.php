<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workshop_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 32);
            $t->decimal('quantity_change', 12, 2);
            $t->decimal('quantity_after', 12, 2);
            $t->timestamp('occurred_at');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['stock_item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
