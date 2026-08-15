<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\TournamentParserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Schema\Blueprint;
use Inertia\Inertia;

/**
 * SEMANALES (apartado promocional)
 * --------------------------------------------------------------------------
 * Eventos semanales gratuitos, públicos y auto-generados. Si no existen al
 * entrar a /semanales, se crean solos (Semanal 1 y Semanal 2) con fecha
 * "PRÓXIMAMENTE". NO forman parte de Rankit League: van sin seriación
 * (is_serialized = false, parent_tournament_id = null) para que nunca
 * aparezcan en getSerializedStandings().
 *
 * Igual que el resto del sistema Jangel, aquí NO usamos modelos Eloquent para
 * torneos: todo es DB::table('tournaments') + Schema::hasColumn.
 */
class SemanalesController extends Controller
{
    /** Email del dueño preferente de los semanales (pedido del cliente). */
    private const OWNER_EMAIL = '18jangel18@gmail.com';

    /**
     * Definición de los semanales que deben existir siempre.
     *
     * 'date_label' es el texto que se ve en la tarjeta (/semanales) y 'start_date'
     * la fecha real en BD. Para cambiar la fecha de un semanal basta con editar
     * aquí: fillMissingFields() reemplaza las etiquetas que nadie tocó a mano
     * (ver legacyDateLabels()). IMPORTANTE: al cambiar una fecha hay que dejar la
     * anterior en legacyDateLabels() / legacyStartDates() para que el evento ya
     * creado en BD se actualice solo.
     *
     * 'registration_status' controla el registro desde código:
     *   - 'closed' se fuerza siempre (cerrar gana sobre lo que haya en BD).
     *   - 'open'   sólo se aplica al crear el evento; si un admin lo cerró a mano
     *              desde el panel, NO se vuelve a abrir solo.
     */
    private const SEMANALES = [
        1 => [
            'name'                => 'Semanal 1',
            'slug'                => 'semanal-1',
            'date_label'          => 'JUEVES 13 DE AGOSTO',
            'start_date'          => '2026-08-13',
            'registration_status' => 'closed', // Ya se jugó: inscripciones cerradas.
        ],
        2 => [
            'name'                => 'Semanal 2',
            'slug'                => 'semanal-2',
            'date_label'          => 'SÁBADO 15 DE AGOSTO',
            'start_date'          => '2026-08-15',
            'registration_status' => 'open',
        ],
    ];

    /** Bolsa de cada semanal: base y la que aplica si se llena el lobby. */
    private const PREMIO_BASE  = 250;
    private const PREMIO_LLENO = 500;

    /** Jugadores que tiene que haber para que la bolsa suba a PREMIO_LLENO. */
    private const CUPO_LLENO = 100;

    /** Cache local de columnas ya verificadas (evita golpear el information_schema de más). */
    private array $colCache = [];

    // =====================================================================
    // 1) ESQUEMA
    // =====================================================================

    /**
     * Garantiza que existan las tablas base y las columnas extra que necesitan
     * los semanales. Todo va nullable / con default para no romper filas viejas.
     * Si algo falla, sólo avisamos por log: la página NO se debe caer.
     */
    private function ensureSchema(): void
    {
        try {
            // Las tablas base ya las sabe crear el parser oficial: no duplicamos CREATE TABLE.
            if (!Schema::hasTable('tournaments') || !Schema::hasTable('tournament_registrations')) {
                (new TournamentParserController())->ensureDatabaseIsReady();
            }

            if (!Schema::hasTable('tournaments')) {
                return; // No hay nada más que hacer.
            }

            Schema::table('tournaments', function (Blueprint $table) {
                if (!Schema::hasColumn('tournaments', 'event_type'))       $table->string('event_type')->nullable();
                if (!Schema::hasColumn('tournaments', 'week_number'))      $table->unsignedInteger('week_number')->nullable();
                if (!Schema::hasColumn('tournaments', 'start_date'))       $table->dateTime('start_date')->nullable();
                if (!Schema::hasColumn('tournaments', 'date_label'))       $table->string('date_label')->nullable();
                // Nullable igual que las de arriba: son columnas nuevas y nadie depende de
                // que sean NOT NULL. Así un torneo creado por otra vía (p. ej. store() del
                // panel) queda en NULL y no en 0, y se distingue "sin configurar" de "false".
                if (!Schema::hasColumn('tournaments', 'requires_discord')) $table->boolean('requires_discord')->nullable()->default(false);
                if (!Schema::hasColumn('tournaments', 'requires_whatsapp'))$table->boolean('requires_whatsapp')->nullable()->default(false);
                if (!Schema::hasColumn('tournaments', 'requires_epic'))    $table->boolean('requires_epic')->nullable()->default(false);
                if (!Schema::hasColumn('tournaments', 'is_free'))          $table->boolean('is_free')->nullable()->default(false);
            });

            // El Epic ID se guarda junto a la inscripción (la tabla original no lo tenía).
            if (Schema::hasTable('tournament_registrations') && !Schema::hasColumn('tournament_registrations', 'epic_id')) {
                Schema::table('tournament_registrations', function (Blueprint $table) {
                    $table->string('epic_id')->nullable();
                });
            }

            $this->colCache = []; // Se agregaron columnas: invalidamos el cache.
        } catch (\Throwable $e) {
            Log::warning('[Semanales] No se pudo asegurar el esquema: ' . $e->getMessage());
        }
    }

    /** Atajo con cache para Schema::hasColumn('tournaments', ...). */
    private function hasCol(string $column): bool
    {
        if (!array_key_exists($column, $this->colCache)) {
            try {
                $this->colCache[$column] = Schema::hasColumn('tournaments', $column);
            } catch (\Throwable $e) {
                $this->colCache[$column] = false;
            }
        }

        return $this->colCache[$column];
    }

    // =====================================================================
    // 2) DUEÑO DE LOS EVENTOS
    // =====================================================================

    /**
     * Resuelve al dueño de los semanales, en este orden:
     *   a) 18jangel18@gmail.com  b) superadmin  c) admin  d) el primer usuario.
     * Devuelve null si la BD no tiene usuarios (tournaments.user_id es FK a users).
     */
    private function resolveOwner()
    {
        try {
            if (!Schema::hasTable('users')) {
                return null;
            }

            $owner = DB::table('users')->where('email', self::OWNER_EMAIL)->first();
            if ($owner) return $owner;

            if ($this->usersHasRole()) {
                $owner = DB::table('users')->where('role', 'superadmin')->orderBy('id')->first();
                if ($owner) return $owner;

                $owner = DB::table('users')->where('role', 'admin')->orderBy('id')->first();
                if ($owner) return $owner;
            }

            return DB::table('users')->orderBy('id')->first();
        } catch (\Throwable $e) {
            Log::warning('[Semanales] No se pudo resolver el dueño: ' . $e->getMessage());
            return null;
        }
    }

    /** La columna 'role' puede no existir en instalaciones viejas. */
    private function usersHasRole(): bool
    {
        try {
            return Schema::hasColumn('users', 'role');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // 3) AUTO-CREACIÓN DE LOS SEMANALES
    // =====================================================================

    /**
     * Crea Semanal 1 y Semanal 2 si no existen. Es idempotente: si el evento ya
     * está creado NO lo sobrescribe, sólo rellena huecos (valores nulos).
     * Nunca toca name / rules / prizes / banner / registration_status ya editados
     * por un admin.
     */
    private function ensureSemanales($owner): void
    {
        if (!$owner) return;

        foreach (self::SEMANALES as $week => $def) {
            try {
                $existing = DB::table('tournaments')->where('slug', $def['slug'])->first();

                if (!$existing) {
                    $this->insertSemanal($owner, $week, $def);
                    continue;
                }

                $this->fillMissingFields($owner, $week, $existing);
            } catch (\Throwable $e) {
                Log::warning("[Semanales] Falló el alta/ajuste de {$def['slug']}: " . $e->getMessage());
            }
        }
    }

    /** Inserta un semanal nuevo con la configuración pedida por el cliente. */
    private function insertSemanal($owner, int $week, array $def): void
    {
        $data = [
            'user_id'    => $owner->id,
            'name'       => $def['name'],
            'rules'      => $this->defaultRules($def['name'], $def['date_label'] ?? null),
            'prizes'     => $this->defaultPrizes(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Mismo patrón de table_name que store(): slug + timestamp.
        if ($this->hasCol('table_name'))            $data['table_name'] = $def['slug'] . '_' . time();
        if ($this->hasCol('slug'))                  $data['slug'] = $def['slug'];
        if ($this->hasCol('is_private'))            $data['is_private'] = false;   // Evento público
        if ($this->hasCol('is_serialized'))         $data['is_serialized'] = false; // NO es Rankit League
        if ($this->hasCol('parent_tournament_id'))  $data['parent_tournament_id'] = null;
        if ($this->hasCol('access_code'))           $data['access_code'] = null;
        if ($this->hasCol('registration_status'))   $data['registration_status'] = $def['registration_status'] ?? 'open';
        if ($this->hasCol('ticket_price'))          $data['ticket_price'] = '0';
        if ($this->hasCol('event_type'))            $data['event_type'] = 'semanal';
        if ($this->hasCol('week_number'))           $data['week_number'] = $week;
        if ($this->hasCol('start_date'))            $data['start_date'] = $def['start_date'] ?? null;
        if ($this->hasCol('date_label'))            $data['date_label'] = $def['date_label'] ?? 'PRÓXIMAMENTE';
        if ($this->hasCol('is_free'))               $data['is_free'] = true;
        if ($this->hasCol('requires_discord'))      $data['requires_discord'] = false; // Discord es OPCIONAL
        if ($this->hasCol('requires_whatsapp'))     $data['requires_whatsapp'] = true;
        if ($this->hasCol('requires_epic'))         $data['requires_epic'] = true;     // Epic ID obligatorio

        DB::table('tournaments')->insert($data);
    }

    /**
     * El evento ya existe: completamos lo que esté en null y reafirmamos los tres
     * requisitos que definen a un semanal (gratuito + Discord + WhatsApp obligatorios).
     * Esos tres son invariantes del apartado, no configuración opcional: un semanal
     * adoptado (creado a mano o recreado desde /admin/jangel, donde los booleanos se
     * quedan en su default) debe quedar bien sí o sí.
     * Lo demás (name / rules / prizes / banner / registration_status) NO se pisa:
     * eso sí es configuración que el admin puede haber cambiado a mano.
     */
    private function fillMissingFields($owner, int $week, $existing): void
    {
        $patch = [];

        if ($this->hasCol('user_id') && is_null($existing->user_id ?? null)) {
            $patch['user_id'] = $owner->id;
        }
        if ($this->hasCol('event_type') && is_null($existing->event_type ?? null)) {
            $patch['event_type'] = 'semanal';
        }
        if ($this->hasCol('week_number') && is_null($existing->week_number ?? null)) {
            $patch['week_number'] = $week;
        }
        // Fecha: se rellena si está vacía y se actualiza si sigue teniendo una etiqueta
        // por defecto que nadie editó (p. ej. el viejo 'PRÓXIMAMENTE'). Si un admin
        // escribió otra cosa a mano, se respeta.
        $def       = self::SEMANALES[$week] ?? [];
        $dateLabel = $def['date_label'] ?? null;
        $etiquetaActual = trim((string) ($existing->date_label ?? ''));

        if ($this->hasCol('date_label') && $dateLabel) {
            if ($etiquetaActual === '' || in_array($etiquetaActual, $this->legacyDateLabels(), true)) {
                $patch['date_label'] = $dateLabel;
            }
        }
        // start_date: se rellena si está vacía y se corrige si sigue teniendo una fecha
        // que puso el propio código en un deploy anterior (ver legacyStartDates()).
        // Si el admin le puso otra fecha/hora desde el panel, no se toca.
        if ($this->hasCol('start_date') && !empty($def['start_date'])) {
            $fechaActual = $existing->start_date ?? null;
            $soloFecha   = $fechaActual ? substr((string) $fechaActual, 0, 10) : '';

            if (is_null($fechaActual) || $soloFecha === '') {
                $patch['start_date'] = $def['start_date'];
            } elseif ($soloFecha !== $def['start_date'] && in_array($soloFecha, $this->legacyStartDates(), true)) {
                $patch['start_date'] = $def['start_date'];
            }
        }
        // Registro: 'closed' se fuerza siempre (es la forma de cerrar un semanal desde
        // código). 'open' sólo se aplica si en BD no hay nada, para no reabrir un evento
        // que un admin cerró a mano.
        $estadoDeseado = $def['registration_status'] ?? null;
        $estadoActual  = trim((string) ($existing->registration_status ?? ''));

        if ($this->hasCol('registration_status') && $estadoDeseado) {
            if ($estadoDeseado === 'closed' && $estadoActual !== 'closed') {
                $patch['registration_status'] = 'closed';
            } elseif ($estadoActual === '') {
                $patch['registration_status'] = $estadoDeseado;
            }
        }
        // Los requisitos centrales del cliente se fuerzan (no basta con mirar si son
        // null): un torneo con slug 'semanal-N' creado por otra vía llega con estas
        // columnas en 0/false y hay que corregirlo, no dejarlo mintiendo en /semanales.
        if ($this->hasCol('is_free') && !($existing->is_free ?? false)) {
            $patch['is_free'] = true;
        }
        if ($this->hasCol('requires_whatsapp') && !($existing->requires_whatsapp ?? false)) {
            $patch['requires_whatsapp'] = true;
        }
        if ($this->hasCol('requires_epic') && !($existing->requires_epic ?? false)) {
            $patch['requires_epic'] = true;
        }
        // Discord pasó a ser OPCIONAL: si quedó marcado como obligatorio de la versión
        // anterior, lo bajamos.
        if ($this->hasCol('requires_discord') && ($existing->requires_discord ?? false)) {
            $patch['requires_discord'] = false;
        }
        // Reglas y premios: sólo actualizamos los textos que nadie tocó (siguen siendo
        // idénticos a un default viejo). Si un admin los editó, se respetan.
        $reglasActuales = trim((string) ($existing->rules ?? ''));
        if ($reglasActuales !== '') {
            foreach ($this->legacyRules($existing->name ?? '') as $viejas) {
                if ($reglasActuales === trim($viejas)) {
                    $patch['rules'] = $this->defaultRules($existing->name ?? '', $dateLabel);
                    break;
                }
            }
        }

        $premiosActuales = trim((string) ($existing->prizes ?? ''));
        if ($premiosActuales !== '') {
            foreach ($this->legacyPrizes() as $viejos) {
                if ($premiosActuales === trim($viejos)) {
                    $patch['prizes'] = $this->defaultPrizes();
                    break;
                }
            }
        }
        // Cuarto invariante: un semanal NUNCA es parte de Rankit League. Si el evento fue
        // creado/editado por otra vía (p. ej. /admin/jangel, donde se puede marcar "seriado"
        // y elegir torneo padre), lo desenganchamos para que no entre en la clasificación
        // de la liga ni en getSerializedStandings().
        if ($this->hasCol('is_serialized') && ($existing->is_serialized ?? false)) {
            $patch['is_serialized'] = false;
        }
        if ($this->hasCol('parent_tournament_id') && !is_null($existing->parent_tournament_id ?? null)) {
            $patch['parent_tournament_id'] = null;
        }

        if (!empty($patch)) {
            $patch['updated_at'] = now();
            DB::table('tournaments')->where('id', $existing->id)->update($patch);
        }
    }

    /** Reglas por defecto (texto libre, editable después desde el panel). */
    private function defaultRules(string $name, ?string $dateLabel = null): string
    {
        // La etiqueta va en mayúsculas para la tarjeta; dentro de la frase se lee mejor en minúsculas.
        $fecha = $dateLabel
            ? '6. El evento se juega el ' . mb_strtolower($dateLabel, 'UTF-8') . '. La hora se avisa con anticipación.'
            : '6. La fecha y la hora están POR ANUNCIAR. Se avisan con anticipación.';

        return implode("\n", [
            "REGLAS — {$name}",
            '',
            '1. La entrada es GRATUITA. No se cobra inscripción de ningún tipo.',
            '2. Es un evento PÚBLICO: cualquiera puede inscribirse mientras el registro esté abierto.',
            '3. El Epic ID y el WhatsApp son OBLIGATORIOS: con el Epic ID te identificamos en la partida y por WhatsApp te llega el aviso. El Discord es OPCIONAL.',
            '4. Te notificamos por mensaje a tu WhatsApp, y una vez inscrito los códigos de partida te llegan por correo.',
            '5. También puedes entrar al Semanal desde la app para ver las tablas y cómo vas en tiempo real.',
            $fecha,
            '7. Debes estar en el lobby a la hora indicada. Si no llegas, tu lugar se libera.',
            '8. Prohibido el teaming (aliarse con otros jugadores fuera de tu equipo). Descalificación inmediata.',
            '9. Prohibido cualquier tipo de cheat, macro, glitch o cuenta compartida. Descalificación y veto de futuros semanales.',
            '10. Un solo registro por jugador. Los registros duplicados se eliminan.',
            '11. La organización puede pedir grabación o replay para validar un resultado.',
            '12. Evento PROMOCIONAL e independiente: los semanales NO forman parte de Rankit League ni otorgan puntos para su clasificación.',
            '13. La decisión de la organización sobre cualquier incidencia es final.',
        ]);
    }

    /**
     * Etiquetas de fecha que puso el sistema (no un admin). Si date_label sigue
     * siendo una de estas, se puede reemplazar por la fecha actual sin pisar
     * nada escrito a mano.
     */
    private function legacyDateLabels(): array
    {
        return [
            'PRÓXIMAMENTE',
            'PROXIMAMENTE',
            'POR ANUNCIAR',
            // Fechas que puso el código en deploys anteriores. Al mover un semanal,
            // su etiqueta anterior se agrega aquí para que la BD se actualice sola.
            'JUEVES 13 DE AGOSTO',
            'VIERNES 14 DE AGOSTO',
        ];
    }

    /**
     * Fechas reales (start_date) que puso el código en deploys anteriores. Si la BD
     * sigue teniendo una de estas, se puede mover al valor actual sin pisar una fecha
     * que un admin haya escrito a mano desde el panel.
     */
    private function legacyStartDates(): array
    {
        return [
            '2026-08-13',
            '2026-08-14',
        ];
    }

    /** Versiones anteriores del texto por defecto de los premios (ver legacyRules). */
    private function legacyPrizes(): array
    {
        return [
            implode("\n", [
                'PREMIOS EN METÁLICO — SEMANALES',
                '',
                'Cada semanal reparte premios en metálico entre los mejores del ranking.',
                'La bolsa exacta de esta edición está POR ANUNCIAR.',
                '',
                'El desglose por posición y la forma de pago se publican aquí mismo y se avisan por Discord y WhatsApp antes de que arranque el evento.',
            ]),
            implode("\n", [
                'PREMIOS EN METÁLICO — SEMANALES',
                '',
                'Cada semanal reparte premios en metálico entre los mejores del ranking.',
                'La bolsa exacta de esta edición está POR ANUNCIAR.',
                '',
                'El desglose por posición y la forma de pago se publican aquí mismo y te los avisamos por mensaje antes de que arranque el evento.',
            ]),
        ];
    }

    /**
     * Versiones anteriores del texto por defecto de las reglas.
     * Sirven para actualizar los eventos que NADIE editó a mano: si el texto
     * guardado es idéntico a un default viejo, se reemplaza por el actual;
     * si un admin lo cambió, se respeta.
     */
    private function legacyRules(string $name): array
    {
        // El texto por defecto lleva la fecha dentro (punto 6), así que cada etiqueta
        // que usó el código en algún momento genera una versión distinta de las reglas.
        // Se agregan todas para poder refrescarlas cuando se mueve la fecha del semanal.
        $porFecha = [$this->defaultRules($name, null)];
        foreach ($this->legacyDateLabels() as $etiqueta) {
            $porFecha[] = $this->defaultRules($name, $etiqueta);
        }

        return array_merge($porFecha, [
            implode("\n", [
                "REGLAS — {$name}",
                '',
                '1. La entrada es GRATUITA. No se cobra inscripción de ningún tipo.',
                '2. Es un evento PÚBLICO: cualquiera puede inscribirse mientras el registro esté abierto.',
                '3. Discord y WhatsApp son OBLIGATORIOS: son los únicos canales por los que te avisamos del lobby y del código de la partida. Si no los dejas bien, no podemos contactarte.',
                '4. La fecha y la hora están POR ANUNCIAR. Se avisan por Discord y WhatsApp con anticipación.',
                '5. Debes estar en el lobby a la hora indicada. Si no llegas, tu lugar se libera.',
                '6. Prohibido el teaming (aliarse con otros jugadores fuera de tu equipo). Descalificación inmediata.',
                '7. Prohibido cualquier tipo de cheat, macro, glitch o cuenta compartida. Descalificación y veto de futuros semanales.',
                '8. Un solo registro por jugador. Los registros duplicados se eliminan.',
                '9. La organización puede pedir grabación o replay para validar un resultado.',
                '10. Evento PROMOCIONAL e independiente: los semanales NO forman parte de Rankit League ni otorgan puntos para su clasificación.',
                '11. La decisión de la organización sobre cualquier incidencia es final.',
            ]),
            implode("\n", [
                "REGLAS — {$name}",
                '',
                '1. La entrada es GRATUITA. No se cobra inscripción de ningún tipo.',
                '2. Es un evento PÚBLICO: cualquiera puede inscribirse mientras el registro esté abierto.',
                '3. El Epic ID y el WhatsApp son OBLIGATORIOS: con el Epic ID te identificamos en la partida y por WhatsApp te llega el aviso. El Discord es OPCIONAL.',
                '4. Te notificamos por mensaje a tu WhatsApp, y una vez inscrito los códigos de partida te llegan por correo.',
                '5. También puedes entrar al Semanal desde la app para ver las tablas y cómo vas en tiempo real.',
                '6. La fecha y la hora están POR ANUNCIAR. Se avisan con anticipación.',
                '7. Debes estar en el lobby a la hora indicada. Si no llegas, tu lugar se libera.',
                '8. Prohibido el teaming (aliarse con otros jugadores fuera de tu equipo). Descalificación inmediata.',
                '9. Prohibido cualquier tipo de cheat, macro, glitch o cuenta compartida. Descalificación y veto de futuros semanales.',
                '10. Un solo registro por jugador. Los registros duplicados se eliminan.',
                '11. La organización puede pedir grabación o replay para validar un resultado.',
                '12. Evento PROMOCIONAL e independiente: los semanales NO forman parte de Rankit League ni otorgan puntos para su clasificación.',
                '13. La decisión de la organización sobre cualquier incidencia es final.',
            ]),
        ]);
    }

    /** Premios por defecto: bolsa base y bolsa ampliada si se llena el lobby. */
    private function defaultPrizes(): string
    {
        $base  = number_format(self::PREMIO_BASE);
        $lleno = number_format(self::PREMIO_LLENO);
        $cupo  = number_format(self::CUPO_LLENO);

        return implode("\n", [
            'PREMIOS EN METÁLICO — SEMANALES',
            '',
            "Cada semanal reparte una bolsa de \${$base} MXN entre los mejores del ranking.",
            "Si se llenan los {$cupo} lugares del evento, la bolsa sube a \${$lleno} MXN.",
            '',
            'El desglose por posición y la forma de pago se publican aquí mismo y te los avisamos por mensaje antes de que arranque el evento.',
        ]);
    }

    // =====================================================================
    // 4) VISTA PÚBLICA  (GET /semanales)
    // =====================================================================

    public function index()
    {
        $this->ensureSchema();

        // Sin sesión no se puede inscribir: dejamos marcada la vuelta para que,
        // al iniciar sesión, redirect()->intended() los traiga de regreso aquí.
        if (!auth()->check()) {
            session(['url.intended' => url('/semanales')]);
        }

        $owner        = $this->resolveOwner();
        $errorMessage = null;
        $semanales    = [];

        if (!$owner) {
            $errorMessage = 'Todavía no podemos crear los semanales porque no hay ningún usuario registrado en la plataforma. Crea la cuenta de administrador y vuelve a entrar.';

            return Inertia::render('Semanales/Index', [
                'semanales'    => [],
                'ownerEmail'   => null,
                'canRegister'  => false,
                'errorMessage' => $errorMessage,
            ]);
        }

        $this->ensureSemanales($owner);

        try {
            $rows = DB::table('tournaments')
                ->whereIn('slug', array_column(self::SEMANALES, 'slug'))
                ->get();

            // Ordenamos por week_number (con fallback al slug si la columna no existe).
            $rows = $rows->sortBy(function ($t) {
                return (int) ($t->week_number ?? 0);
            })->values();

            $userEmail = auth()->user()?->email;

            foreach ($rows as $t) {
                $playersCount = 0;
                $isRegistered = false;

                try {
                    $playersCount = DB::table('tournament_registrations')
                        ->where('tournament_id', $t->id)
                        ->count();

                    if ($userEmail) {
                        $isRegistered = DB::table('tournament_registrations')
                            ->where('tournament_id', $t->id)
                            ->where('email', $userEmail)
                            ->exists();
                    }
                } catch (\Throwable $e) {
                    Log::warning('[Semanales] No se pudieron contar inscritos: ' . $e->getMessage());
                }

                $slug = $t->slug ?? '';

                $semanales[] = [
                    'id'                  => (int) $t->id,
                    'name'                => (string) $t->name,
                    'slug'                => (string) $slug,
                    'week_number'         => (int) ($t->week_number ?? 0),
                    'date_label'          => (string) ($t->date_label ?: 'PRÓXIMAMENTE'),
                    'start_date'          => $t->start_date ? (string) $t->start_date : null,
                    'is_free'             => (bool) ($t->is_free ?? true),
                    'entry_fee_label'     => 'GRATIS',
                    'is_public'           => !((bool) ($t->is_private ?? false)),
                    'requires_discord'    => (bool) ($t->requires_discord ?? false), // opcional
                    'requires_whatsapp'   => (bool) ($t->requires_whatsapp ?? true),
                    'requires_epic'       => (bool) ($t->requires_epic ?? true),
                    'registration_status' => (string) ($t->registration_status ?? 'open'),
                    'prizes'              => $t->prizes ?? null,
                    'rules'               => $t->rules ?? null,
                    'players_count'       => (int) $playersCount,
                    'is_registered'       => (bool) $isRegistered,
                    'public_url'          => '/t/' . $slug,
                ];
            }

            if (empty($semanales)) {
                $errorMessage = 'No se pudieron crear los semanales automáticamente. Revisa la configuración de la base de datos e inténtalo de nuevo.';
            }
        } catch (\Throwable $e) {
            Log::warning('[Semanales] Error al leer los eventos: ' . $e->getMessage());
            $semanales    = [];
            $errorMessage = 'No se pudieron cargar los semanales en este momento. Vuelve a intentarlo en unos minutos.';
        }

        $canRegister = collect($semanales)
            ->contains(fn ($s) => $s['registration_status'] === 'open');

        return Inertia::render('Semanales/Index', [
            'semanales'    => $semanales,
            'ownerEmail'   => $owner->email ?? null,
            'canRegister'  => $canRegister,
            'errorMessage' => $errorMessage,
            // Habilita las acciones de administración en la página (avisar cambio de fecha).
            'isAdmin'      => $this->userIsAdmin(),
        ]);
    }

    /** ¿El usuario de la sesión puede usar las acciones de admin de los semanales? */
    private function userIsAdmin(): bool
    {
        $user = auth()->user();

        if (!$user) return false;

        try {
            return method_exists($user, 'isAdmin') ? (bool) $user->isAdmin() : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // =====================================================================
    // 5) INSCRIPCIÓN  (POST /semanales/{id}/registro)
    // =====================================================================

    public function register(Request $request, $id)
    {
        $this->ensureSchema();

        // --- 1. Localizar el semanal (por id o por slug) ---
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) {
            $tournament = DB::table('tournaments')->where('slug', $id)->first();
        }

        if (!$tournament || ($tournament->event_type ?? null) !== 'semanal') {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos ese semanal. Refresca la página e inténtalo de nuevo.',
            ], 404);
        }

        // --- 2. ¿Está abierto el registro? ---
        $status = $tournament->registration_status ?? 'open';
        if ($status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Las inscripciones para este semanal ya están cerradas.',
            ], 403);
        }
        if ($status === 'disabled') {
            return response()->json([
                'success' => false,
                'message' => 'Las inscripciones para este semanal todavía no están habilitadas. Atento a Discord y WhatsApp.',
            ], 403);
        }

        // --- 3. Hay que tener cuenta: el correo sale de la sesión, no del formulario ---
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success'      => false,
                'requiresAuth' => true,
                'message'      => 'Necesitas una cuenta de Rankit para inscribirte. Inicia sesión (o créala en 30 segundos) y vuelve a intentarlo.',
            ], 401);
        }

        // --- 4. Validación (siempre JSON, nunca redirect) ---
        $rules = [
            'player_name' => 'required|string|max:255',
            'epic_id'     => 'required|string|max:100',
            'whatsapp'    => 'required|string|max:30',
            'discord'     => 'nullable|string|max:100', // Discord es OPCIONAL
        ];

        $messages = [
            'player_name.required' => 'Necesitamos tu nombre de jugador.',
            'player_name.max'      => 'El nombre de jugador es demasiado largo (máx. 255 caracteres).',
            'epic_id.required'     => 'El Epic ID es obligatorio: con él te identificamos dentro de la partida.',
            'epic_id.max'          => 'El Epic ID es demasiado largo (máx. 100 caracteres).',
            'whatsapp.required'    => 'El WhatsApp es obligatorio: ahí te mandamos el aviso del evento.',
            'whatsapp.max'         => 'El WhatsApp es demasiado largo (máx. 30 caracteres).',
            'discord.max'          => 'El Discord es demasiado largo (máx. 100 caracteres).',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        // El correo SIEMPRE sale de la sesión: el formulario no puede inscribir a otra persona.
        $email      = $user->email;
        $whatsapp   = trim($data['whatsapp']);
        $discord    = trim((string) ($data['discord'] ?? '')) ?: null; // opcional
        $epicId     = trim($data['epic_id']);
        $playerName = trim($data['player_name']);

        // --- 5. Anti-duplicados (mismo correo o mismo WhatsApp en el mismo semanal) ---
        try {
            $duplicated = DB::table('tournament_registrations')
                ->where('tournament_id', $tournament->id)
                ->where(function ($q) use ($email, $whatsapp) {
                    $q->where('email', $email)->orWhere('whatsapp', $whatsapp);
                })
                ->exists();

            if ($duplicated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya estás inscrito en este semanal con esos datos. Nos vemos en el lobby.',
                ], 409);
            }
        } catch (\Throwable $e) {
            Log::warning('[Semanales] No se pudo verificar duplicados: ' . $e->getMessage());
        }

        // --- 6. Alta. La entrada es gratis => queda confirmada al instante ---
        try {
            $fila = [
                'tournament_id'  => $tournament->id,
                'player_name'    => $playerName,
                'email'          => $email,
                'whatsapp'       => $whatsapp,
                'discord'        => $discord,
                'payment_status' => 'paid',
                'payment_notes'  => 'Inscripción gratuita (Semanal) · Epic ID: ' . $epicId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // La columna epic_id se agrega en ensureSchema(); si por lo que sea no existe,
            // el Epic ID igual queda registrado en payment_notes.
            if (Schema::hasColumn('tournament_registrations', 'epic_id')) {
                $fila['epic_id'] = $epicId;
            }

            DB::table('tournament_registrations')->insert($fila);
        } catch (\Throwable $e) {
            Log::warning('[Semanales] No se pudo guardar la inscripción: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No pudimos guardar tu inscripción. Inténtalo de nuevo en unos segundos.',
            ], 422);
        }

        // --- 7. Correos (mismo patrón que registerForTournament) ---
        // Si el SMTP falla, la inscripción ya quedó guardada: no rompemos nada.
        if ($email) {
            try {
                \Illuminate\Support\Facades\Mail::to($email)
                    ->send(new \App\Mail\GenericRankitMail(
                        'Inscripción Recibida - ' . $tournament->name,
                        'emails.registration_received',
                        ['tournamentName' => $tournament->name, 'playerName' => $playerName]
                    ));
            } catch (\Exception $e) { }
        }

        $adminEmails = ['18jangel18@gmail.com', 'cometax.ti@gmail.com'];
        try {
            $owner = DB::table('users')->where('id', $tournament->user_id)->first();
            if ($owner && $owner->email && !in_array($owner->email, $adminEmails)) {
                $adminEmails[] = $owner->email;
            }
        } catch (\Exception $e) { }

        foreach ($adminEmails as $adminEmail) {
            try {
                \Illuminate\Support\Facades\Mail::to($adminEmail)
                    ->send(new \App\Mail\GenericRankitMail(
                        'Nueva Inscripción - ' . $tournament->name,
                        'emails.registration_admin_notification',
                        ['tournamentName' => $tournament->name, 'playerName' => $playerName, 'playerEmail' => $email]
                    ));
            } catch (\Exception $e) { }
        }

        return response()->json([
            'success' => true,
            'message' => '¡Listo! Quedaste inscrito en ' . $tournament->name
                . '. Te avisamos por mensaje a tu WhatsApp y los códigos de partida te llegarán a ' . $email
                . '. También puedes entrar al evento desde la app para ver las tablas y cómo vas.',
        ]);
    }

    // =====================================================================
    // 6) AVISO DE CAMBIO DE FECHA  (POST /semanales/{id}/aviso-recorrido)
    // =====================================================================

    /**
     * Manda un correo a TODOS los inscritos del semanal avisando que el evento se
     * recorrió. Sólo para administradores. No modifica el torneo: la fecha de la
     * tarjeta se sigue cambiando desde el panel o desde la constante SEMANALES.
     */
    public function notifyReschedule(Request $request, $id)
    {
        $this->ensureSchema();

        if (!$this->userIsAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Sólo un administrador puede mandar este aviso.',
            ], 403);
        }

        // --- 1. Localizar el semanal (por id o por slug) ---
        $tournament = DB::table('tournaments')->where('id', $id)->first();
        if (!$tournament) {
            $tournament = DB::table('tournaments')->where('slug', $id)->first();
        }

        if (!$tournament || ($tournament->event_type ?? null) !== 'semanal') {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos ese semanal. Refresca la página e inténtalo de nuevo.',
            ], 404);
        }

        // --- 2. Datos del aviso ---
        $validator = Validator::make($request->all(), [
            'date_label' => 'nullable|string|max:120',
            'note'       => 'nullable|string|max:600',
        ], [
            'date_label.max' => 'La fecha nueva es demasiado larga (máx. 120 caracteres).',
            'note.max'       => 'La nota es demasiado larga (máx. 600 caracteres).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Revisa los datos del aviso.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $nuevaFecha = trim((string) $request->input('date_label', ''));
        if ($nuevaFecha === '') {
            $nuevaFecha = trim((string) ($tournament->date_label ?? '')) ?: 'POR ANUNCIAR';
        }
        $nota = trim((string) $request->input('note', ''));

        // --- 3. Destinatarios: los inscritos del evento (correos únicos) ---
        try {
            $inscritos = DB::table('tournament_registrations')
                ->where('tournament_id', $tournament->id)
                ->whereNotNull('email')
                ->where('email', '<>', '')
                ->get(['email', 'player_name']);
        } catch (\Throwable $e) {
            Log::warning('[Semanales] No se pudieron leer los inscritos para el aviso: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No pudimos leer la lista de inscritos. Inténtalo de nuevo en unos segundos.',
            ], 500);
        }

        // Un correo por persona aunque tenga varios registros.
        $destinatarios = [];
        foreach ($inscritos as $fila) {
            $correo = strtolower(trim((string) $fila->email));
            if ($correo === '' || isset($destinatarios[$correo])) continue;
            $destinatarios[$correo] = trim((string) ($fila->player_name ?? '')) ?: 'jugador';
        }

        if (empty($destinatarios)) {
            return response()->json([
                'success' => false,
                'message' => 'Todavía no hay inscritos en ' . $tournament->name . ', así que no hay a quién avisarle.',
            ], 422);
        }

        // --- 4. Envío (si un correo falla, seguimos con los demás) ---
        $enviados = 0;
        $fallidos = 0;

        foreach ($destinatarios as $correo => $nombre) {
            try {
                \Illuminate\Support\Facades\Mail::to($correo)
                    ->send(new \App\Mail\GenericRankitMail(
                        'Cambio de fecha - ' . $tournament->name,
                        'emails.semanal_rescheduled',
                        [
                            'playerName'     => $nombre,
                            'tournamentName' => $tournament->name,
                            'dateLabel'      => $nuevaFecha,
                            'note'           => $nota,
                        ]
                    ));
                $enviados++;
            } catch (\Throwable $e) {
                $fallidos++;
                Log::warning("[Semanales] Falló el aviso de cambio de fecha a {$correo}: " . $e->getMessage());
            }
        }

        Log::info('[Semanales] Aviso de cambio de fecha enviado', [
            'tournament' => $tournament->slug ?? $tournament->id,
            'admin'      => auth()->user()?->email,
            'nueva'      => $nuevaFecha,
            'enviados'   => $enviados,
            'fallidos'   => $fallidos,
        ]);

        if ($enviados === 0) {
            return response()->json([
                'success'  => false,
                'message'  => 'No se pudo enviar ningún correo. Revisa la configuración de correo e inténtalo de nuevo.',
                'sent'     => 0,
                'failed'   => $fallidos,
                'total'    => count($destinatarios),
            ], 500);
        }

        $mensaje = "Aviso enviado a {$enviados} inscrito(s) de {$tournament->name}.";
        if ($fallidos > 0) {
            $mensaje .= " {$fallidos} correo(s) no salieron: revisa el log.";
        }

        return response()->json([
            'success' => true,
            'message' => $mensaje,
            'sent'    => $enviados,
            'failed'  => $fallidos,
            'total'   => count($destinatarios),
        ]);
    }
}
