<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Player Stats
        if (!Schema::hasTable('player_match_stats')) {
            Schema::create('player_match_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_match_id')->constrained('tournament_matches')->cascadeOnDelete();
                $table->string('player_name');
                $table->integer('placement')->default(0);
                $table->integer('kills')->default(0);
                $table->integer('damage_done')->default(0);
                $table->integer('damage_taken')->default(0);
                $table->json('extra_stats')->nullable();
                $table->timestamps();
            });
        }

        // Team Stats
        if (!Schema::hasTable('team_match_stats')) {
            Schema::create('team_match_stats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_match_id')->constrained('tournament_matches')->cascadeOnDelete();
                $table->integer('team_id_in_match'); // ID inside the game session
                $table->integer('rank');
                $table->json('member_names');
                $table->string('team_signature'); // MD5 of sorted member names for tracking
                $table->integer('total_kills');
                $table->integer('total_points');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_match_stats');
        Schema::dropIfExists('player_match_stats');
    }
};