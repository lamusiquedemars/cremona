<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_message_copies', function (Blueprint $table): void {
            // MySQL currently uses the old unique index to support this foreign key.
            // Create a dedicated index before replacing the uniqueness constraint.
            $table->index('organization_id', 'email_message_copy_organization_index');
            $table->index('email_folder_id', 'email_message_copy_folder_index');
        });

        Schema::table('email_message_copies', function (Blueprint $table): void {
            $table->dropUnique('email_message_copy_uid_unique');
            $table->unique(
                ['organization_id', 'email_folder_id', 'uid_validity', 'uid'],
                'email_message_copy_uid_validity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('email_message_copies', function (Blueprint $table): void {
            $table->dropUnique('email_message_copy_uid_validity_unique');
            $table->unique(['organization_id', 'email_folder_id', 'uid'], 'email_message_copy_uid_unique');
        });

        Schema::table('email_message_copies', function (Blueprint $table): void {
            $table->dropIndex('email_message_copy_folder_index');
            $table->dropIndex('email_message_copy_organization_index');
        });
    }
};
