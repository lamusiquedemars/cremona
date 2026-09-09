<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_order_stock_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workshop_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('label_snapshot');
            $t->decimal('quantity', 12, 2)->default(1);
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_order_stock_items');
    }
};
