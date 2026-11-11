<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columna `user_id` de `tournament_registrations`, que se agrega en el primer
 * uso: en producción no se corren migraciones (igual que EsquemaUsuarios y
 * ensureDatabaseIsReady()). La migración solo cubre local y pruebas.
 *
 * Antes la inscripción solo guardaba el correo que la persona escribía, así que
 * si se inscribía con otro correo no había forma de saber que era suya. Con
 * `user_id` queda ligada a la cuenta aunque el correo no coincida.
 */
class EsquemaInscripciones
{
    /**
     * Agrega `user_id` (nullable, con índice) si todavía no existe.
     *
     * Devuelve si la columna quedó disponible. Si la tabla aún no existe no hace
     * nada: la crea ensureDatabaseIsReady(), que ya la trae con `user_id`.
     *
     * Sin caché estático a propósito (mismo criterio que asegurarTablas()): es una
     * sola consulta y así cada prueba, que arranca con base nueva, vuelve a revisar.
     */
    public static function asegurar(): bool
    {
        if (!Schema::hasTable('tournament_registrations')) {
            return false;
        }

        if (!Schema::hasColumn('tournament_registrations', 'user_id')) {
            Schema::table('tournament_registrations', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }

        return true;
    }

    /**
     * Liga la fila de inscripción a la cuenta de quien tiene sesión. Sin sesión
     * (o si la columna no se pudo crear) la fila se guarda igual que antes.
     */
    public static function conUsuario(array $fila, ?Authenticatable $user): array
    {
        if ($user && self::asegurar()) {
            $fila['user_id'] = $user->getAuthIdentifier();
        }

        return $fila;
    }
}
