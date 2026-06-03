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
        Schema::table('smtp_servers', function (Blueprint $table) {
            if (! Schema::hasColumn('smtp_servers', 'reply_to_email')) {
                $table->string('reply_to_email')->nullable()->after('from_email');
            }

            if (! Schema::hasColumn('smtp_servers', 'reply_to_name')) {
                $table->string('reply_to_name')->nullable()->after('reply_to_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_servers', function (Blueprint $table) {
            if (Schema::hasColumn('smtp_servers', 'reply_to_name')) {
                $table->dropColumn('reply_to_name');
            }

            if (Schema::hasColumn('smtp_servers', 'reply_to_email')) {
                $table->dropColumn('reply_to_email');
            }
        });
    }
};
