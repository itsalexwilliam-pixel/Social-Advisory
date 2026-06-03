<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('campaigns', 'email_gap_seconds')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->unsignedInteger('email_gap_seconds')
                    ->nullable()
                    ->after('emails_per_minute');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'email_gap_seconds')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('email_gap_seconds');
            });
        }
    }
};
