<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the user's last name ("name" holds the first name).
     */
    public function up(): void
    {
        // Added as nullable first: SQLite can't add a NOT NULL column without a default.
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_name');
        });
    }
};
