<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'bracket_data')) {
                $table->json('bracket_data')->nullable()->after('scoring_format');
            }
            if (!Schema::hasColumn('tournaments', 'has_prizes')) {
                $table->boolean('has_prizes')->default(false)->after('is_private');
            }
        });

        // Use raw SQL to modify the column to be nullable to avoid doctrine/dbal dependency issues
        if (Schema::hasColumn('tournaments', 'table_name')) {
            DB::statement('ALTER TABLE tournaments MODIFY table_name VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'bracket_data')) {
                $table->dropColumn('bracket_data');
            }
            if (Schema::hasColumn('tournaments', 'has_prizes')) {
                $table->dropColumn('has_prizes');
            }
        });
    }
};