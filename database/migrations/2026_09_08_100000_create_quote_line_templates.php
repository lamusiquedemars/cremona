<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_line_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80)->nullable();
            $table->string('label');
            $table->string('kind', 80)->nullable();
            $table->text('description');
            $table->decimal('default_quantity', 10, 2)->default(1);
            $table->decimal('default_unit_amount', 12, 2)->default(0);
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_line_templates');
    }
};
