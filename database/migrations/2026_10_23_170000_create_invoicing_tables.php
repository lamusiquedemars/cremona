<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_invoice_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reference_prefix', 24)->default('FAC');
            $table->unsignedBigInteger('next_reference_number')->default(1);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('default_due_days')->nullable();
            $table->decimal('default_tax_rate', 5, 2)->default(20);
            $table->string('vat_regime', 32)->default('standard');
            $table->text('default_payment_terms')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->text('late_payment_terms')->nullable();
            $table->string('regulatory_provider', 40)->default('super_pdp');
            $table->boolean('super_pdp_enabled')->default(false);
            $table->string('super_pdp_connection_status', 32)->default('not_configured');
            $table->boolean('pennylane_copy_enabled')->default(false);
            $table->text('pennylane_sales_email')->nullable();
            $table->string('rental_billing_mode', 32)->default('manual');
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('kind', 24)->default('invoice');
            $table->string('status', 24)->default('draft');
            $table->string('reference', 80)->nullable();
            $table->foreignId('person_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workshop_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_note_for_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('issued_on')->nullable();
            $table->date('supply_on')->nullable();
            $table->date('due_on')->nullable();
            $table->char('currency', 3)->default('EUR');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->text('late_payment_terms')->nullable();
            $table->json('issuer_snapshot')->nullable();
            $table->json('recipient_snapshot')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->foreignId('pdf_private_document_id')->nullable()->constrained('private_documents')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status', 'due_on']);
            $table->index(['organization_id', 'rental_id', 'issued_on']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 24)->nullable();
            $table->decimal('unit_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->string('tax_category', 32)->default('standard');
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['invoice_id', 'position']);
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('method', 32);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'paid_on']);
        });

        Schema::create('invoice_transmissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('direction', 16)->default('outbound');
            $table->string('status', 32)->default('pending');
            $table->string('external_id', 120)->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'provider', 'status']);
            $table->unique(['organization_id', 'provider', 'external_id'], 'invoice_transmissions_external_unique');
        });

        DB::table('organizations')->where('vertical_pack', 'luthier')->orderBy('id')->each(function (object $organization): void {
            DB::table('organization_modules')->updateOrInsert(
                ['organization_id' => $organization->id, 'module' => 'invoicing'],
                ['enabled' => true, 'configuration' => json_encode([]), 'updated_at' => now(), 'created_at' => now()],
            );
        });
    }

    public function down(): void
    {
        DB::table('organization_modules')->where('module', 'invoicing')->delete();
        Schema::dropIfExists('invoice_transmissions');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('organization_invoice_settings');
    }
};
