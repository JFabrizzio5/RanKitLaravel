<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (!Schema::hasColumn('tournaments', 'image_path')) {
                $table->string('image_path')->nullable()->after('name');
            }
            if (!Schema::hasColumn('tournaments', 'game_type')) {
                $table->string('game_type')->default('fortnite')->after('game');
            }
            if (!Schema::hasColumn('tournaments', 'entry_fee')) {
                $table->decimal('entry_fee', 10, 2)->default(0)->after('is_private');
            }
            if (!Schema::hasColumn('tournaments', 'currency')) {
                $table->string('currency')->default('USD')->after('entry_fee');
            }
            if (!Schema::hasColumn('tournaments', 'platform_fee_percentage')) {
                $table->decimal('platform_fee_percentage', 5, 2)->default(10.00)->after('currency');
            }
            if (!Schema::hasColumn('tournaments', 'bracket_data')) {
                $table->json('bracket_data')->nullable()->after('scoring_format');
            }
            if (!Schema::hasColumn('tournaments', 'has_prizes')) {
                $table->boolean('has_prizes')->default(false)->after('platform_fee_percentage');
            }
        });
    }

    public function down()
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'image_path',
                'game_type',
                'entry_fee',
                'currency',
                'platform_fee_percentage',
                'bracket_data',
                'has_prizes'
            ]);
        });
    }
};