<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('sku', 80)->nullable();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('quantity_on_hand', 12, 2)->default(0);
            $t->decimal('reorder_level', 12, 2)->default(0);
            $t->decimal('suggested_unit_amount', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['organization_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
