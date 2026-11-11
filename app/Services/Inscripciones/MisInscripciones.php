<?php

namespace App\Services\Inscripciones;

use App\Models\User;
use App\Support\EsquemaInscripciones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Los torneos a los que está inscrito un jugador, para /mis-torneos y el dashboard.
 *
 * Una inscripción es suya si trae su `user_id` (se inscribió con sesión) o si el
 * correo coincide con el de su cuenta sin importar mayúsculas: las inscripciones
 * viejas no tienen `user_id` y solo se pueden reconocer por el correo.
 *
 * Igual que el resto del sistema de torneos no usa modelos: `tournaments` y
 * `tournament_registrations` son DB::table y sus columnas varían según qué
 * pantalla las haya creado, así que las opcionales se revisan antes de pedirlas.
 */
class MisInscripciones
{
    /** Los torneos se juegan en México: "hoy" y las fechas se calculan con su hora. */
    private const ZONA = 'America/Mexico_City';

    /**
     * Un torneo sin fecha y sin partidas jugadas cuenta como próximo solo durante
     * estos días desde la inscripción. Si nunca se jugó, no se queda para siempre
     * arriba como "por anunciar".
     */
    private const DIAS_SIN_FECHA = 30;

    /** Columnas de `tournaments` que no siempre existen (las agregan otras pantallas). */
    private const OPCIONALES = [
        'slug', 'banner_image', 'is_private', 'is_serialized',
        'event_type', 'start_date',
    ];

    /**
     * Una fila por torneo, primero los próximos (el más cercano arriba) y luego
     * los pasados (el más reciente arriba).
     *
     * @return array<int, array<string, mixed>>
     */
    public function de(User $user): array
    {
        if (!Schema::hasTable('tournament_registrations') || !Schema::hasTable('tournaments')) {
            return [];
        }

        $filas = $this->consultar($user);
        if ($filas->isEmpty()) {
            return [];
        }

        $jugadas = $this->ultimaPartidaJugada($filas->pluck('torneo_id')->unique()->all());
        $ahora   = CarbonImmutable::now(self::ZONA);

        $torneos = $this->unaPorTorneo($filas)
            ->map(fn (object $fila) => $this->armar($fila, $jugadas[$fila->torneo_id] ?? null, $ahora));

        // Próximos: el más cercano primero y los "por anunciar" al final.
        $proximos = $torneos->where('proximo', true)
            ->sort(fn (array $a, array $b) => [$a['fecha'] === null, $a['fecha'], $b['id']]
                <=> [$b['fecha'] === null, $b['fecha'], $a['id']]);

        // Pasados: el más reciente primero (sin fecha, cuenta el día de la inscripción).
        $pasados = $torneos->where('proximo', false)
            ->sort(fn (array $a, array $b) => [$b['fecha'] ?? $b['inscrito_el'], $b['id']]
                <=> [$a['fecha'] ?? $a['inscrito_el'], $a['id']]);

        return $proximos->concat($pasados)->values()->all();
    }

    /** Inscripciones del usuario unidas con su torneo (puede haber varias por torneo). */
    private function consultar(User $user): Collection
    {
        $conUserId = $this->columnaUserIdLista();
        $correo    = Str::lower(trim((string) $user->email));

        // Sin ningún criterio el where quedaría vacío y traería las inscripciones de todos.
        if (!$conUserId && $correo === '') {
            return collect();
        }

        $columnas = Schema::getColumnListing('tournaments');

        $select = [
            'r.id', 'r.payment_status', 'r.player_name', 'r.created_at',
            't.id as torneo_id', 't.name as torneo',
        ];
        foreach (self::OPCIONALES as $columna) {
            if (in_array($columna, $columnas, true)) {
                $select[] = "t.$columna";
            }
        }

        return DB::table('tournament_registrations as r')
            ->join('tournaments as t', 't.id', '=', 'r.tournament_id')
            ->where(function ($q) use ($conUserId, $user, $correo) {
                if ($conUserId) {
                    $q->where('r.user_id', $user->id);
                }
                if ($correo !== '') {
                    // LOWER/TRIM funciona igual en MySQL y en SQLite (pruebas).
                    $q->orWhereRaw('LOWER(TRIM(r.email)) = ?', [$correo]);
                }
            })
            ->select($select)
            ->get();
    }

    /**
     * Crea `user_id` si falta (primer uso). Si el ALTER falla, la página no se cae:
     * se busca solo por correo, como antes.
     */
    private function columnaUserIdLista(): bool
    {
        try {
            return EsquemaInscripciones::asegurar();
        } catch (\Throwable $e) {
            Log::warning('[MisInscripciones] No se pudo agregar user_id: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Si alguien se inscribió dos veces al mismo torneo (con la cuenta y con otro
     * correo, por ejemplo), se muestra una sola tarjeta: la confirmada si hay, y si
     * no, la más reciente.
     */
    private function unaPorTorneo(Collection $filas): Collection
    {
        return $filas
            ->sort(fn (object $a, object $b) => [$b->payment_status === 'paid', (string) $b->created_at, $b->id]
                <=> [$a->payment_status === 'paid', (string) $a->created_at, $a->id])
            ->unique('torneo_id')
            ->values();
    }

    /**
     * Día de la última partida con replay procesado de cada torneo. Es la única
     * pista de cuándo se jugó un torneo que no tiene fecha programada.
     *
     * @return array<int, string> torneo_id => 'Y-m-d'
     */
    private function ultimaPartidaJugada(array $torneoIds): array
    {
        if (!Schema::hasTable('tournament_matches')) {
            return [];
        }

        return DB::table('tournament_matches')
            ->whereIn('tournament_id', $torneoIds)
            ->whereNotNull('raw_data')
            ->groupBy('tournament_id')
            ->select('tournament_id', DB::raw('MAX(updated_at) as jugada'))
            ->get()
            ->filter(fn (object $m) => $m->jugada !== null)
            // updated_at se guarda en la zona de la app (UTC).
            ->mapWithKeys(fn (object $m) => [
                (int) $m->tournament_id => CarbonImmutable::parse($m->jugada, config('app.timezone'))
                    ->setTimezone(self::ZONA)
                    ->toDateString(),
            ])
            ->all();
    }

    /** Lo que necesita una tarjeta, ya resuelto (tipo, fecha, URL, estado). */
    private function armar(object $fila, ?string $jugada, CarbonImmutable $ahora): array
    {
        $tipo = $this->tipo($fila);

        // La fecha programada manda; si no hay, el día en que se jugó la última partida.
        // start_date es un día de calendario: se toma tal cual, sin cambiarle la zona.
        $programada = !empty($fila->start_date)
            ? CarbonImmutable::parse($fila->start_date)->toDateString()
            : null;
        $fecha = $programada ?? $jugada;

        $inscritoEl = CarbonImmutable::parse($fila->created_at, config('app.timezone'))->setTimezone(self::ZONA);

        // Las fechas son 'Y-m-d', así que compararlas como texto es compararlas como días.
        $proximo = $fecha !== null
            ? $fecha >= $ahora->toDateString()
            : $inscritoEl->greaterThanOrEqualTo($ahora->subDays(self::DIAS_SIN_FECHA));

        return [
            'id'          => (int) $fila->id,
            'torneo_id'   => (int) $fila->torneo_id,
            'nombre'      => (string) $fila->torneo,
            'jugador'     => (string) ($fila->player_name ?? ''),
            // Todo lo que vive en `tournaments` es Fortnite (LoL y fútbol tienen sus tablas).
            'juego'       => 'Fortnite',
            'tipo'        => $tipo,
            'privado'     => (bool) ($fila->is_private ?? false),
            'banner'      => $this->banner($fila->banner_image ?? null),
            'fecha'       => $fecha,
            'fecha_tipo'  => $programada ? 'programada' : ($jugada ? 'jugada' : null),
            'estado'      => in_array($fila->payment_status, ['paid', 'rejected'], true) ? $fila->payment_status : 'pending',
            'proximo'     => $proximo,
            'url'         => $this->url($fila, $tipo),
            'inscrito_el' => $inscritoEl->toDateString(),
        ];
    }

    private function tipo(object $fila): string
    {
        if (($fila->event_type ?? null) === 'semanal') {
            return 'semanal';
        }

        $esLiga = (bool) ($fila->is_serialized ?? false)
            || Str::startsWith((string) ($fila->slug ?? ''), 'v0.1-rankit-league-');

        return $esLiga ? 'liga' : 'torneo';
    }

    /**
     * Página pública del torneo. /t/{id} y /live/{id} son la misma vista
     * (PublicTournamentController); /tournament/{id} es una maqueta con datos
     * de ejemplo, no sirve aquí.
     *
     * Va por id y no por slug porque el panel arma el slug con el nombre y dos
     * torneos con el mismo nombre comparten slug. Los semanales sí van por slug
     * (fijo y único), igual que en /semanales.
     */
    private function url(object $fila, string $tipo): string
    {
        if ($tipo === 'semanal' && !empty($fila->slug)) {
            return '/t/' . $fila->slug;
        }

        return '/t/' . $fila->torneo_id;
    }

    /** Misma ruta que usa la vista pública (los banners se suben a public/torneos). */
    private function banner(?string $ruta): ?string
    {
        if (empty($ruta)) {
            return null;
        }

        return Str::startsWith($ruta, ['http://', 'https://']) ? $ruta : asset('public/' . $ruta);
    }
}
