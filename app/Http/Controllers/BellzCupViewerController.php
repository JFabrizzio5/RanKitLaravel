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
     * 60 = 1 min (pruebas). En prod puedes poner 600 = 10 min.
     */
    private const POINT_SECONDS = 60;

    /**
     * Anti-trampa / anti-sleep:
     * Si el navegador estuvo dormido mucho tiempo, capea el delta máximo por ping.
     * Ajusta o ponlo en null si no quieres cap.
     */
    private const MAX_DELTA_SECONDS = 300; // 5 min

    /**
     * START: crea o reinicia UNA sola sesión por usuario (O(1))
     * Tabla requerida: bellzcup_viewer_sessions (UNIQUE userid)
     */
    public function start(Request $request)
    {
        $userId = (int) $request->user()->id;
        $now = now('America/Mexico_City');

        if ($now->lt($this->viewerStartAt())) {
            return response()->json(['message' => 'Viewer points aún no activos'], 403);
        }

        // Upsert (1 fila por usuario)
        DB::statement(
            "INSERT INTO bellzcup_viewer_sessions
                (userid, started_at, last_ping_at, acc_seconds, active, created_at, updated_at)
             VALUES
                (?, ?, ?, 0, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                started_at   = VALUES(started_at),
                last_ping_at = VALUES(last_ping_at),
                acc_seconds  = 0,
                active       = 1,
                updated_at   = VALUES(updated_at)",
            [
                $userId,
                $now->toDateTimeString(),
                $now->toDateTimeString(),
                $now->toDateTimeString(),
                $now->toDateTimeString(),
            ]
        );

        // Obtener session_id
        $row = DB::selectOne(
            "SELECT id FROM bellzcup_viewer_sessions WHERE userid = ? LIMIT 1",
            [$userId]
        );

        return response()->json(['session_id' => (int) ($row->id ?? 0)]);
    }

    /**
     * HEARTBEAT O(1):
     * - Bloquea solo la fila de sesión del usuario
     * - Calcula delta, acumula segundos, decide puntos, guarda overflow
     * - Upsert en rifapoints sumando puntos
     *
     * Tablas requeridas:
     * - bellzcup_viewer_sessions (UNIQUE userid)
     * - bellzcup_rifapoints (UNIQUE userid recomendado)
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

        // username para rifapoints (solo si se otorgan puntos)
        $username = null;

        $awardedPoints = 0;

        DB::transaction(function () use ($userId, $sessionId, $now, &$awardedPoints, &$username) {

            // 1) Lock de la sesión (1 fila)
            $pointSeconds = (int) self::POINT_SECONDS;
            $maxDelta = (int) self::MAX_DELTA_SECONDS;

            // Nota: usamos LEAST(delta, maxDelta) para capear.
            $session = DB::selectOne(
                "SELECT
                    id,
                    userid,
                    last_ping_at,
                    acc_seconds,
                    LEAST(GREATEST(0, TIMESTAMPDIFF(SECOND, last_ping_at, ?)), ?) AS delta_seconds,
                    (acc_seconds + LEAST(GREATEST(0, TIMESTAMPDIFF(SECOND, last_ping_at, ?)), ?)) AS acc_total,
                    FLOOR((acc_seconds + LEAST(GREATEST(0, TIMESTAMPDIFF(SECOND, last_ping_at, ?)), ?)) / ?) AS award_points,
                    MOD((acc_seconds + LEAST(GREATEST(0, TIMESTAMPDIFF(SECOND, last_ping_at, ?)), ?)), ?) AS acc_remainder
                 FROM bellzcup_viewer_sessions
                 WHERE id = ?
                   AND userid = ?
                   AND active = 1
                 FOR UPDATE",
                [
                    $now->toDateTimeString(), $maxDelta,
                    $now->toDateTimeString(), $maxDelta,
                    $now->toDateTimeString(), $maxDelta, $pointSeconds,
                    $now->toDateTimeString(), $maxDelta, $pointSeconds,
                    $sessionId,
                    $userId,
                ]
            );

            if (!$session) {
                // sesión inválida o inactiva
                return;
            }

            $awardedPoints = (int) ($session->award_points ?? 0);
            $accRemainder  = (int) ($session->acc_remainder ?? 0);

            // 2) Update de la sesión (siempre)
            DB::statement(
                "UPDATE bellzcup_viewer_sessions
                 SET last_ping_at = ?,
                     acc_seconds  = ?,
                     updated_at   = ?
                 WHERE id = ?
                   AND userid = ?
                   AND active = 1",
                [
                    $now->toDateTimeString(),
                    $accRemainder,
                    $now->toDateTimeString(),
                    $sessionId,
                    $userId,
                ]
            );

            // 3) Si no hay puntos, termina
            if ($awardedPoints <= 0) {
                return;
            }

            // 4) username (solo si hay puntos)
            $u = DB::selectOne("SELECT name FROM users WHERE id = ? LIMIT 1", [$userId]);
            $username = $u->name ?? ('user_' . $userId);

            // 5) Upsert rifapoints (requiere UNIQUE(userid) recomendado)
            DB::statement(
                "INSERT INTO bellzcup_rifapoints (userid, username, totalpoints, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    totalpoints = totalpoints + VALUES(totalpoints),
                    updated_at  = VALUES(updated_at),
                    username    = COALESCE(username, VALUES(username))",
                [
                    $userId,
                    $username,
                    $awardedPoints,
                    $now->toDateTimeString(),
                    $now->toDateTimeString(),
                ]
            );
        });

        return response()->json([
            'ok'            => true,
            'awarded'       => $awardedPoints > 0,
            'awardedPoints' => $awardedPoints,
        ]);
    }

    /**
     * STOP (opcional): desactiva la sesión
     * Útil si quieres llamarlo cuando el usuario sale de "comunidad"
     */
    public function stop(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'integer'],
        ]);

        $userId = (int) $request->user()->id;
        $sessionId = (int) $request->integer('session_id');
        $now = now('America/Mexico_City');

        DB::statement(
            "UPDATE bellzcup_viewer_sessions
             SET active = 0,
                 updated_at = ?
             WHERE id = ? AND userid = ?",
            [
                $now->toDateTimeString(),
                $sessionId,
                $userId,
            ]
        );

        return response()->json(['ok' => true]);
    }
}
