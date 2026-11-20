<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();          // used in URLs and to find the game's engine
            $table->string('title', 100);
            $table->string('description')->nullable();
            $table->string('cover')->nullable();           // path relative to public/, e.g. img/games/covers/tic-tac-toe.svg
            $table->unsignedTinyInteger('min_players')->default(2);
            $table->unsignedTinyInteger('max_players')->default(2);
            $table->boolean('is_active')->default(true);   // hidden from the catalogue when false
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
