<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// En producción la columna la crea App\Support\EsquemaInscripciones en el primer uso.
// La tabla no tiene migración propia (la crea ensureDatabaseIsReady() ya con user_id),
// así que aquí solo se toca si ya existe, p. ej. una base local vieja.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tournament_registrations')) {
            return;
        }

        Schema::table('tournament_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('tournament_registrations', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tournament_registrations') || !Schema::hasColumn('tournament_registrations', 'user_id')) {
            return;
        }

        Schema::table('tournament_registrations', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
