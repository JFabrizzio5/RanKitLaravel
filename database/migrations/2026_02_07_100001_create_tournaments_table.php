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
        if (!Schema::hasTable('tournaments')) {
            Schema::create('tournaments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Owner
                $table->string('name');
                $table->string('slug')->nullable();
                $table->string('game')->default('fortnite'); // Game selection
                $table->string('twitch_channel')->nullable();
                $table->boolean('is_private')->default(false);
                $table->string('access_code')->nullable();
                $table->longText('rules')->nullable();
                $table->longText('prizes')->nullable();
                $table->json('scoring_format')->nullable();
                $table->integer('entry_fee')->default(0); // Cents
                $table->string('currency')->default('mxn');
                $table->string('table_name')->nullable(); // Legacy support or future use
                $table->timestamps();
            });
        }
        else {
            Schema::table('tournaments', function (Blueprint $table) {
                // Ensure new columns exist if table already exists
                if (!Schema::hasColumn('tournaments', 'user_id'))
                    $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
                if (!Schema::hasColumn('tournaments', 'game'))
                    $table->string('game')->default('fortnite');
                if (!Schema::hasColumn('tournaments', 'entry_fee'))
                    $table->integer('entry_fee')->default(0);
                if (!Schema::hasColumn('tournaments', 'currency'))
                    $table->string('currency')->default('mxn');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};