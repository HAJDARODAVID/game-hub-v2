<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The player who left during the match (their opponent was given the win).
     */
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->foreignId('forfeited_by')->nullable()->after('winner_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('forfeited_by');
        });
    }
};
