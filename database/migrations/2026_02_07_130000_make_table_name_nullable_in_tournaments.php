<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('table_name')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('tournaments', function (Blueprint $table) {
        // Cannot easily revert to not null without knowing if there are nulls, 
        // but we can try to set it back if we assume no nulls were added.
        // For safety, we might skip reverting or enforce a default.
        // $table->string('table_name')->nullable(false)->change();
        });
    }
};