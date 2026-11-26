<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User ids of players who said "play again" after this match ended.
     * The rematch starts once every seated player has voted.
     */
    public function up(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->json('rematch_votes')->nullable()->after('winner_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_matches', function (Blueprint $table) {
            $table->dropColumn('rematch_votes');
        });
    }
};
