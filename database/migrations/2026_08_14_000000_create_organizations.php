<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('vertical_pack')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('password');
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('collaborator')->index();
            $table->json('permissions')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });

        Schema::dropIfExists('organizations');
    }
};
