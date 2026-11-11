<?php

namespace App\Services\Landing;

use App\Http\Controllers\SemanalesController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lo que la landing ofrece para "reservar lugar": el próximo semanal y la fase
 * de la liga, con datos reales (inscritos, fecha, si está abierto).
 *
 * Ojo: un semanal con fecha pasada NO cuenta como abierto aunque en la base
 * diga registration_status=open (en producción el Semanal 2 del 15 de agosto
 * seguía "abierto" en noviembre).
 */
class EventosAbiertos
{
    public function obtener(): array
    {
        return [
            'semanal' => $this->semanal(),
            'liga' => $this->liga(),
        ];
    }

    private function semanal(): array
    {
        $base = [
            'nombre' => 'Semanales',
            'fecha' => null,
            'fecha_label' => null,
            'inscritos' => 0,
            'cupo' => SemanalesController::CUPO_LLENO,
            'premio_base' => SemanalesController::PREMIO_BASE,
            'premio_lleno' => SemanalesController::PREMIO_LLENO,
            'abierto' => false,
            'url' => '/semanales',
        ];

        try {
            if (!Schema::hasTable('tournaments')) {
                return $base;
            }

            $conFecha = Schema::hasColumn('tournaments', 'start_date');
            $hoy = Carbon::today();

            $filas = DB::table('tournaments')
                ->where('slug', 'like', 'semanal-%')
                ->get(array_filter(['id', 'name', 'registration_status', $conFecha ? 'start_date' : null, Schema::hasColumn('tournaments', 'date_label') ? 'date_label' : null]));

            // El primero abierto con fecha de hoy en adelante (o sin fecha).
            $proximo = $filas
                ->filter(fn ($t) => ($t->registration_status ?? '') === 'open'
                    && (empty($t->start_date) || Carbon::parse($t->start_date)->gte($hoy)))
                ->sortBy(fn ($t) => $t->start_date ?? '9999')
                ->first();

            if (!$proximo) {
                return $base;
            }

            return array_merge($base, [
                'nombre' => (string) $proximo->name,
                'fecha' => $proximo->start_date ?? null,
                'fecha_label' => $proximo->date_label ?? null,
                'inscritos' => Schema::hasTable('tournament_registrations')
                    ? DB::table('tournament_registrations')->where('tournament_id', $proximo->id)->count()
                    : 0,
                'abierto' => true,
            ]);
        } catch (Throwable $e) {
            Log::warning('Landing: no se pudo leer el próximo semanal', ['error' => $e->getMessage()]);

            return $base;
        }
    }

    private function liga(): array
    {
        $base = ['fase' => null, 'abierta' => false, 'url' => '/league'];

        try {
            if (!Schema::hasTable('tournaments') || !Schema::hasColumn('tournaments', 'is_serialized')) {
                return $base;
            }

            $abierta = DB::table('tournaments')
                ->where('is_serialized', true)
                ->where('slug', 'like', 'v0.1-rankit-league-%')
                ->where('registration_status', 'open')
                ->orderBy('id')
                ->first(['id', 'name']);

            if (!$abierta) {
                return $base;
            }

            // "Rankit Pro League - Clasificatorio 2 (v0.1)" → "Clasificatorio 2"
            // /u: sin él, "·" (2 bytes) se corta a la mitad y el JSON de la página truena.
            $fase = trim(preg_replace(['/^rankit\s*\.?\s*pro\s*league\s*[-–—:·]\s*/iu', '/\s*\(v[\d.]+\)\s*$/iu'], '', (string) $abierta->name));

            return ['fase' => $fase ?: (string) $abierta->name, 'abierta' => true, 'url' => '/league'];
        } catch (Throwable $e) {
            Log::warning('Landing: no se pudo leer la fase de la liga', ['error' => $e->getMessage()]);

            return $base;
        }
    }
}
