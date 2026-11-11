<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas de `users` que se agregan en el primer uso. En producción no se
 * corren migraciones (igual que ensureDatabaseIsReady() y las tablas de
 * planes), así que la migración solo cubre local y pruebas.
 */
class EsquemaUsuarios
{
    private static bool $listo = false;

    public static function asegurar(): void
    {
        if (self::$listo) {
            return;
        }

        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 20)->nullable()->after('email');
            });
        }

        self::$listo = true;
    }
}
