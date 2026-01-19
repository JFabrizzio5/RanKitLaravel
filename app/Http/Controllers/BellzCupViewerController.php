<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BellzCupViewerController extends Controller
{
    /**
     * A partir de qué fecha/hora empiezan a contar los viewer points
     */
    private function viewerStartAt(): Carbon
    {
        return Carbon::create(2026, 1, 18, 22, 15, 0, 'America/Mexico_City');
    }

    /**
     * Cada cuántos segundos se otorga 1 punto
     * 60 = 1 min (para pruebas). En prod puedes poner 600 = 10 min.
     */
    private const POINT_SECONDS = 60;

    /**
     * Crea un insert nuevo al entrar a "comunidad"
     */
    public function start(Request $request)
    {
        $userId = (int) $request->user()->id;
        $now = now('America/Mexico_City');

        if ($now->lt($this->viewerStartAt())) {
            return response()->json(['message' => 'Viewer points aún no activos'], 403);
        }

        $id = DB::table('bellzcup_viewerpoints')->insertGetId([
            'userid'         => $userId,
            'tiempo_inicial' => $now,
            'tiempo_final'   => $now,
            'point_boole'    => 0,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return response()->json(['session_id' => $id]);
    }

    /**
     * Cada X segundos actualiza tiempo_final y si ya juntó >= POINT_SECONDS, otorga 1 punto
     */
    public function heartbeat(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'integer'],
        ]);

        $userId = (int) $request->user()->id;
        $sessionId = (int) $request->integer('session_id');
        $now = now('America/Mexico_City');

        if ($now->lt($this->viewerStartAt())) {
            return response()->json(['message' => 'Viewer points aún no activos'], 403);
        }

        $awarded = false;

        DB::transaction(function () use ($userId, $sessionId, $now, &$awarded) {

            // 1) Sesión actual (debe ser del usuario)
            $current = DB::table('bellzcup_viewerpoints')
                ->where('id', $sessionId)
                ->where('userid', $userId)
                ->lockForUpdate()
                ->first();

            if (!$current) {
                logger()->warning('viewer heartbeat: session not found', [
                    'userId' => $userId,
                    'sessionId' => $sessionId,
                ]);
                return;
            }

            // Ya otorgada => no hacer nada
            if ((int) $current->point_boole === 1) {
                logger()->info('viewer heartbeat: already awarded', [
                    'userId' => $userId,
                    'sessionId' => $sessionId,
                ]);
                return;
            }

            // 2) Actualizar tiempo_final del actual
            DB::table('bellzcup_viewerpoints')
                ->where('id', $sessionId)
                ->update([
                    'tiempo_final' => $now,
                    'updated_at'   => $now,
                ]);

            // (Opcional) log de la fila actualizada (sirve mucho para debug)
            $rowAfter = DB::table('bellzcup_viewerpoints')->where('id', $sessionId)->first();
            logger()->info('viewer row after update', [
                'userId' => $userId,
                'sessionId' => $sessionId,
                'now' => $now->toDateTimeString(),
                'tiempo_inicial' => $rowAfter->tiempo_inicial ?? null,
                'tiempo_final' => $rowAfter->tiempo_final ?? null,
            ]);

            // 3) Traer todas las filas no consumidas (point_boole=0)
            $rowsFalse = DB::table('bellzcup_viewerpoints')
                ->where('userid', $userId)
                ->where('point_boole', 0)
                ->lockForUpdate()
                ->get(['id', 'tiempo_inicial', 'tiempo_final']);

            // 4) Sumar segundos (FIX: evitar líos de timezone parseando directo a epoch)
            $totalSeconds = 0;
            foreach ($rowsFalse as $r) {
                $start = strtotime($r->tiempo_inicial);
                $end   = strtotime($r->tiempo_final);
                if ($end > $start) {
                    $totalSeconds += ($end - $start);
                }
            }

            logger()->info('viewer seconds', [
                'userId' => $userId,
                'sessionId' => $sessionId,
                'totalSeconds' => $totalSeconds,
                'threshold' => self::POINT_SECONDS,
                'rowsFalseCount' => $rowsFalse->count(),
            ]);

            // 5) Aún no llega
            if ($totalSeconds < self::POINT_SECONDS) {
                return;
            }

            // 6) Llegó => otorgar 1 punto
            $awarded = true;
            $overflow = $totalSeconds - self::POINT_SECONDS;

            // 7) Consumir otras filas false (dejarlas en duración 0)
            $otherFalseIds = $rowsFalse->pluck('id')->filter(fn ($id) => (int) $id !== $sessionId)->values();

            if ($otherFalseIds->count()) {
                DB::table('bellzcup_viewerpoints')
                    ->whereIn('id', $otherFalseIds)
                    ->update([
                        'tiempo_final' => DB::raw('tiempo_inicial'),
                        'updated_at'   => $now,
                    ]);
            }

            // 8) Marcar la actual como true
            DB::table('bellzcup_viewerpoints')
                ->where('id', $sessionId)
                ->update([
                    'point_boole' => 1,
                    'updated_at'  => $now,
                ]);

            // 9) Sumar +1 a rifapoints (con username cuando no exista)
            $rifa = DB::table('bellzcup_rifapoints')
                ->where('userid', $userId)
                ->lockForUpdate()
                ->first();

            if ($rifa) {
                DB::table('bellzcup_rifapoints')
                    ->where('userid', $userId)
                    ->update([
                        'totalpoints' => (int) $rifa->totalpoints + 1,
                        'updated_at'  => $now,
                    ]);
            } else {
                $u = DB::table('users')->where('id', $userId)->first();

                DB::table('bellzcup_rifapoints')->insert([
                    'userid'      => $userId,
                    'username'    => $u->name ?? ('user_' . $userId),
                    'totalpoints' => 1,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            // 10) Crear siguiente fila false arrastrando overflow
            $startNext = $overflow > 0 ? $now->copy()->subSeconds($overflow) : $now;

            DB::table('bellzcup_viewerpoints')->insert([
                'userid'         => $userId,
                'tiempo_inicial' => $startNext,
                'tiempo_final'   => $now,
                'point_boole'    => 0,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        });

        return response()->json([
            'ok'      => true,
            'awarded' => $awarded,
        ]);
    }
}
