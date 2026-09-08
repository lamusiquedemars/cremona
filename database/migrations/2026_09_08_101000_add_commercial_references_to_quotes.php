<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->string('pennylane_quote_id')->nullable()->after('reference');
            $table->string('pennylane_status', 40)->nullable()->after('pennylane_quote_id');
            $table->string('pennylane_pdf_url', 2048)->nullable()->after('pennylane_status');
            $table->timestamp('pennylane_synced_at')->nullable()->after('pennylane_pdf_url');
            $table->text('pennylane_last_error')->nullable()->after('pennylane_synced_at');
            $table->unique(['organization_id', 'pennylane_quote_id']);
        });

        Schema::table('quote_lines', function (Blueprint $table): void {
            $table->foreignId('quote_line_template_id')->nullable()->after('quote_id')->constrained()->nullOnDelete();
            $table->string('template_label_snapshot')->nullable()->after('quote_line_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quote_line_template_id');
            $table->dropColumn('template_label_snapshot');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'pennylane_quote_id']);
            $table->dropColumn(['pennylane_quote_id', 'pennylane_status', 'pennylane_pdf_url', 'pennylane_synced_at', 'pennylane_last_error']);
        });
    }
};
