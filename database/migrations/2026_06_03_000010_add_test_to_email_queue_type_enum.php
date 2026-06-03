<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'test' as a valid type so that test emails sent from the SMTP
     * settings page are recorded in email_queue like every other email.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE email_queue MODIFY COLUMN `type` ENUM('campaign','single','test') NOT NULL DEFAULT 'campaign'"
        );
    }

    public function down(): void
    {
        // Remove test-type rows first so the rollback doesn't fail on invalid enum values
        DB::table('email_queue')->where('type', 'test')->delete();

        DB::statement(
            "ALTER TABLE email_queue MODIFY COLUMN `type` ENUM('campaign','single') NOT NULL DEFAULT 'campaign'"
        );
    }
};
