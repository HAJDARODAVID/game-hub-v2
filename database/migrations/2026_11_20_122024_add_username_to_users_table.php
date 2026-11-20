<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique gamertag (username) that users log in with.
     */
    public function up(): void
    {
        // Added as nullable first: SQLite can't add a NOT NULL column without a default.
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
