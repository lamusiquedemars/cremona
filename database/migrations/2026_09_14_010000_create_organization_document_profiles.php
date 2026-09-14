<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_legal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('city')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('registration_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->text('legal_notice')->nullable();
            $table->timestamps();
        });

        Schema::create('organization_quote_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('default_validity_days')->nullable();
            $table->text('default_payment_terms')->nullable();
            $table->text('default_tax_note')->nullable();
            $table->string('terms_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::table('people', function (Blueprint $table): void {
            $table->string('address_line_1')->nullable()->after('country_code');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('postal_code', 32)->nullable()->after('address_line_2');
            $table->string('city')->nullable()->after('postal_code');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('address_line_1')->nullable()->after('website');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('postal_code', 32)->nullable()->after('address_line_2');
            $table->string('city')->nullable()->after('postal_code');
            $table->string('country_code', 2)->nullable()->after('city');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->json('issuer_snapshot')->nullable()->after('sent_via');
            $table->json('recipient_snapshot')->nullable()->after('issuer_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropColumn(['issuer_snapshot', 'recipient_snapshot']);
        });
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['address_line_1', 'address_line_2', 'postal_code', 'city', 'country_code']);
        });
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['address_line_1', 'address_line_2', 'postal_code', 'city']);
        });
        Schema::dropIfExists('organization_quote_settings');
        Schema::dropIfExists('organization_legal_profiles');
    }
};
