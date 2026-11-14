<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_insurance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('family', 80)->nullable();
            $table->json('eligible_sizes')->nullable();
            $table->foreignId('rental_tier_id')->nullable()->constrained('rental_tiers')->nullOnDelete();
            $table->decimal('monthly_amount', 10, 2);
            $table->string('clause_version', 100)->nullable();
            $table->text('coverage_summary')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'family', 'is_active'], 'rental_insurance_plan_lookup_idx');
        });

        Schema::table('rentals', function (Blueprint $table): void {
            $table->foreignId('insurance_plan_id')->nullable()->after('insurance_monthly_amount')->constrained('rental_insurance_plans')->nullOnDelete();
            $table->string('insurance_plan_name')->nullable()->after('insurance_plan_id');
            $table->string('insurance_clause_version', 100)->nullable()->after('insurance_plan_name');
            $table->text('insurance_coverage_summary')->nullable()->after('insurance_clause_version');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('insurance_plan_id');
            $table->dropColumn(['insurance_plan_name', 'insurance_clause_version', 'insurance_coverage_summary']);
        });
        Schema::dropIfExists('rental_insurance_plans');
    }
};
