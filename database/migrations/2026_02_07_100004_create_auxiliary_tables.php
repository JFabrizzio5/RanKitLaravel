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
        // Registrations
        if (!Schema::hasTable('tournament_registrations')) {
            Schema::create('tournament_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->boolean('has_paid')->default(false);
                $table->string('payment_intent_id')->nullable();
                $table->integer('amount_paid')->default(0);
                $table->string('currency')->default('mxn');
                $table->timestamps();

                $table->unique(['user_id', 'tournament_id']);
            });
        }

        // Score Logs (Audit)
        if (!Schema::hasTable('tournament_score_logs')) {
            Schema::create('tournament_score_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('match_id')->nullable(); // Can be null if global adj
                $table->string('player_name');
                $table->integer('points_change');
                $table->text('reason');
                $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_score_logs');
        Schema::dropIfExists('tournament_registrations');
    }
};