<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_duration_minutes')->nullable();
            $table->string('brevo_meeting_name')->nullable();
            $table->foreignId('quote_line_template_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'label']);
            $table->unique(['organization_id', 'brevo_meeting_name']);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('appointment_type_id')->nullable()->after('conversation_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'appointment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'appointment_type_id']);
            $table->dropConstrainedForeignId('appointment_type_id');
        });

        Schema::dropIfExists('appointment_types');
    }
};
