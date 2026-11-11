<script setup lang="ts">
/**
 * RANKIT PRO LEAGUE (/league)
 *
 * Página plana y para lectores jóvenes: qué es, cómo entrar en 3 pasos, cuándo
 * es lo próximo y la tabla de posiciones. Lo demás (reglas de cada fase,
 * calculadora de premios, otros torneos) va más abajo o dentro de acordeones.
 *
 * Datos: GET /api/league/standings (TournamentParserController@getSerializedStandings)
 * devuelve [{ tournament: <fila completa de `tournaments`>, standings: [...] }].
 * Los puntos no vienen en la respuesta: se calculan como eliminaciones ×
 * scoring_format.kill_points (10 por defecto), igual que hacía la versión anterior.
 */
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';

/* ------------------------------------------------------------------ */
/* Tipos de /api/league/standings                                      */
/* ------------------------------------------------------------------ */

interface TorneoLiga {
    id: number;
    name: string;
    slug?: string | null;
    is_private?: boolean | number | string | null;
    registration_status?: string | null; // open | closed | disabled
    scoring_format?: string | { kill_points?: number | string } | null;
    // Columnas que agrega SemanalesController; pueden no existir.
    start_date?: string | null;
    date_label?: string | null;
}

interface FilaTabla {
    player_name: string | null;
    total_kills: number | string | null;
    total_damage: number | string | null;
    matches_played: number | string | null;
    best_placement?: number | string | null;
}

interface BloqueLiga {
    tournament: TorneoLiga;
    standings: FilaTabla[] | null;
}

interface Fase {
    num: number;
    semana: string;
    titulo: string;
    lema: string;
    color: string;
    icono: string;
    poster: string;
    resumen: string;
    pase: string;
    reglas: string[];
}

/* ------------------------------------------------------------------ */
/* Contenido fijo                                                      */
/* ------------------------------------------------------------------ */

const FASES: Fase[] = [
    {
        num: 1,
        semana: 'Semana 1',
        titulo: 'Clasificatorio 1',
        lema: 'Fase inicial',
        color: '#9b30ff',
        icono: 'ph-flag-banner',
        poster: '/public/league/clasificatorio-1.png',
        resumen: '3 partidas gratis para clasificar. Con Premium juegas 2 más y peleas por el premio de la semana.',
        pase: 'Pasa el Top 10% Free y el Top 10% Pro',
        reglas: [
            'Las primeras 3 partidas son gratis.',
            'Con el Upgrade Premium juegas 2 partidas extra (5 en total) y participas por el pozo de dinero semanal.',
            'Lobby privado y formato Solos. Prohibido el teaming.',
            'Corte Free (partidas 1 a 3): avanza el Top 10% general.',
            'Corte Premium (partidas 4 y 5): avanza el Top 10% de quienes pagaron.',
        ],
    },
    {
        num: 2,
        semana: 'Semana 2',
        titulo: 'Clasificatorio 2',
        lema: 'Segunda oportunidad',
        color: '#00b074',
        icono: 'ph-arrows-clockwise',
        poster: '/public/league/clasificatorio-2.png',
        resumen: 'Mismo formato que la semana 1, con tabla de puntos nueva. Otra oportunidad de entrar al Top 10%.',
        pase: 'Pasa el Top 10% Free y el Top 10% Pro',
        reglas: [
            'Battle Royale Clásico en Solos, igual que la semana 1.',
            '3 partidas gratis + 2 Premium.',
            'Corte Free (partidas 1 a 3): avanza el Top 10%.',
            'Corte Premium (partidas 4 y 5): avanza el Top 10% de quienes pagaron.',
        ],
    },
    {
        num: 3,
        semana: 'Semana 3',
        titulo: 'Repechaje',
        lema: 'La última vida',
        color: '#d7263d',
        icono: 'ph-heartbeat',
        poster: '/public/league/repechaje.png',
        resumen: '4 partidas: 2 Battle Royale y 2 Recarga. Solo hay 8 pases a la final.',
        pase: '8 pases: victorias, puntos y kills',
        reglas: [
            '4 partidas exactas: 2 Battle Royale + 2 Recarga.',
            'Ganador de cada partida: 4 pases.',
            'Top 3 en puntos totales: 3 pases.',
            'MVP de kills (el que más eliminó y todavía no clasificaba): 1 pase.',
        ],
    },
    {
        num: 4,
        semana: 'Semana 4',
        titulo: 'Gran Final',
        lema: 'El cierre absoluto',
        color: '#f1c40f',
        icono: 'ph-crown',
        poster: '/public/league/gran-final.png',
        resumen: 'Solo los mejores: el Top 15% de las semanas 1 y 2 más los que pasan del Repechaje.',
        pase: 'Premios: 80% de la bolsa · 20% en sorteos',
        reglas: [
            'Battle Royale Clásico en lobbies privados.',
            '6 partidas. No hay suplentes.',
            'Aquí se paga el Mega Pozo.',
            'La bolsa se reparte: 80% en premios para los campeones y 20% en sorteos entre todos los participantes.',
            'Para los clasificados, entrar a la final no cuesta.',
        ],
    },
];

const OTROS_TORNEOS = [
    {
        juego: 'Valorant',
        nombre: 'DM Showdown',
        descripcion: 'Deathmatch de todos contra todos. El jugador con más bajas tras 3 rondas gana la bolsa.',
        entrada: '$5 USD',
        color: '#f87171',
    },
    {
        juego: 'League of Legends',
        nombre: 'Rey ARAM',
        descripcion: 'Duelos 1v1 oficiales. Primera sangre, 100 súbditos o primera torreta define al ganador.',
        entrada: '$5 USD',
        color: '#60a5fa',
    },
    {
        juego: 'Rocket League',
        nombre: 'Hoops 2v2',
        descripcion: 'Bracket rápido de eliminación en la cancha de baloncesto. Domina el aire.',
        entrada: '$10 USD',
        color: '#22d3ee',
    },
    {
        juego: 'Overwatch 2',
        nombre: 'Copa Defensora',
        descripcion: 'Arma tu escuadra de 5 (cola de roles) y domina los mapas en eliminación directa.',
        entrada: '$15 USD',
        color: '#fb923c',
    },
];

const MEDALLAS = [
    { nombre: 'oro', fondo: '#f5c518', texto: '#1a1300' },
    { nombre: 'plata', fondo: '#c7ccd4', texto: '#111418' },
    { nombre: 'bronce', fondo: '#cd7f32', texto: '#1a0e02' },
];

const INSTAGRAM = 'https://instagram.com/rankit.pro';
const FILAS_INICIALES = 10;

const SECCIONES = [
    { href: '#como-entrar', texto: 'Cómo entrar', icono: 'ph-sign-in' },
    { href: '#tabla', texto: 'Tabla', icono: 'ph-list-numbers' },
    { href: '#fases', texto: 'Fases', icono: 'ph-flag-checkered' },
    { href: '#premios', texto: 'Premios', icono: 'ph-coins' },
    { href: '#otros', texto: 'Otros torneos', icono: 'ph-game-controller' },
];

/* ------------------------------------------------------------------ */
/* Utilidades                                                          */
/* ------------------------------------------------------------------ */

const formatoNumero = new Intl.NumberFormat('es-MX');
const fmt = (n: number) => formatoNumero.format(n);

function num(valor: unknown): number {
    const n = Number(valor);
    return Number.isFinite(n) ? n : 0;
}

function esVerdadero(valor: unknown): boolean {
    return valor === true || valor === 1 || valor === '1' || valor === 'true';
}

/** "Rankit Pro League - Clasificatorio 1 (v0.1)" → "Clasificatorio 1" */
function nombreCorto(nombre: string | null | undefined): string {
    const original = (nombre ?? '').trim();
    const limpio = original
        .replace(/^rankit\s*\.?\s*pro\s*league\s*[-–—:·]\s*/i, '')
        .replace(/\s*\(v[\d.]+\)\s*$/i, '')
        .trim();
    return limpio || original || 'Torneo';
}

function faseDe(t: TorneoLiga | null | undefined): Fase | null {
    if (!t) return null;
    const texto = `${t.slug ?? ''} ${t.name ?? ''}`.toLowerCase();
    let numero = 0;
    if (/clasificatorio[\s-]*1/.test(texto)) numero = 1;
    else if (/clasificatorio[\s-]*2/.test(texto)) numero = 2;
    else if (texto.includes('repechaje')) numero = 3;
    else if (texto.includes('final')) numero = 4;
    return FASES.find((f) => f.num === numero) ?? null;
}

function puntosPorKill(t: TorneoLiga | null | undefined): number {
    let formato: unknown = t?.scoring_format ?? null;
    if (typeof formato === 'string') {
        try {
            formato = JSON.parse(formato);
        } catch {
            formato = null;
        }
    }
    const kp = Number((formato as { kill_points?: unknown } | null)?.kill_points);
    return Number.isFinite(kp) && kp > 0 ? kp : 10;
}

function fechaDe(t: TorneoLiga): Date | null {
    if (!t.start_date) return null;
    const d = new Date(String(t.start_date).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? null : d;
}

function textoFecha(t: TorneoLiga): string | null {
    if (t.date_label && String(t.date_label).trim()) return String(t.date_label).trim();
    const d = fechaDe(t);
    if (!d) return null;
    const conHora = d.getHours() !== 0 || d.getMinutes() !== 0;
    const opciones: Intl.DateTimeFormatOptions = { weekday: 'long', day: 'numeric', month: 'long' };
    if (conHora) {
        opciones.hour = 'numeric';
        opciones.minute = '2-digit';
    }
    const texto = new Intl.DateTimeFormat('es-MX', opciones).format(d);
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function ocultarImagen(e: Event) {
    const img = e.target as HTMLImageElement | null;
    if (img) img.style.display = 'none';
}

/* ------------------------------------------------------------------ */
/* Sesión y menú                                                       */
/* ------------------------------------------------------------------ */

const page = usePage();
const usuario = computed(() => page.props.auth?.user ?? null);

const menuAbierto = ref(false);
const botonMenu = ref<HTMLButtonElement | null>(null);

function cerrarMenu() {
    menuAbierto.value = false;
}

function alPresionarTecla(e: KeyboardEvent) {
    if (e.key === 'Escape' && menuAbierto.value) {
        menuAbierto.value = false;
        botonMenu.value?.focus();
    }
}

/* ------------------------------------------------------------------ */
/* Tabla de posiciones                                                 */
/* ------------------------------------------------------------------ */

const torneos = ref<BloqueLiga[]>([]);
const cargando = ref(true);
const hayError = ref(false);
const indiceActivo = ref(0);
const busqueda = ref('');
const verTodos = ref(false);

/** Ordenados por fase (Clasificatorio 1 → Gran Final); los que no se reconocen van al final. */
const torneosOrdenados = computed(() =>
    torneos.value
        .map((bloque, i) => ({ bloque, i, orden: faseDe(bloque.tournament)?.num ?? 99 }))
        .sort((a, b) => a.orden - b.orden || a.i - b.i)
        .map((x) => x.bloque),
);

const bloqueActivo = computed<BloqueLiga | null>(() => torneosOrdenados.value[indiceActivo.value] ?? null);
const puntosKillActivo = computed(() => puntosPorKill(bloqueActivo.value?.tournament));

const filas = computed(() =>
    (bloqueActivo.value?.standings ?? []).map((f, i) => ({
        lugar: i + 1,
        nombre: f.player_name || 'Desconocido',
        partidas: num(f.matches_played),
        kills: num(f.total_kills),
        dano: num(f.total_damage),
        puntos: num(f.total_kills) * puntosKillActivo.value,
    })),
);

const textoBusqueda = computed(() => busqueda.value.trim().toLowerCase());

const filasFiltradas = computed(() =>
    textoBusqueda.value ? filas.value.filter((f) => f.nombre.toLowerCase().includes(textoBusqueda.value)) : filas.value,
);

const filasVisibles = computed(() =>
    textoBusqueda.value || verTodos.value ? filasFiltradas.value : filasFiltradas.value.slice(0, FILAS_INICIALES),
);

function elegirTorneo(i: number) {
    indiceActivo.value = i;
    busqueda.value = '';
    verTodos.value = false;
}

/* ------------------------------------------------------------------ */
/* Lo próximo                                                          */
/* ------------------------------------------------------------------ */

/**
 * 1) el torneo con fecha (start_date) más cercana desde hoy;
 * 2) si ninguno tiene fecha, el primero con inscripciones abiertas;
 * 3) si no, el primero que todavía no tiene partidas jugadas.
 */
const proximo = computed<BloqueLiga | null>(() => {
    const lista = torneosOrdenados.value;
    if (!lista.length) return null;

    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const conFecha = lista
        .map((b) => ({ b, fecha: fechaDe(b.tournament) }))
        .filter((x): x is { b: BloqueLiga; fecha: Date } => x.fecha !== null && x.fecha >= hoy)
        .sort((a, b) => a.fecha.getTime() - b.fecha.getTime());
    if (conFecha.length) return conFecha[0].b;

    return (
        lista.find((b) => b.tournament.registration_status === 'open') ??
        lista.find((b) => !b.standings || b.standings.length === 0) ??
        null
    );
});

const faseProxima = computed(() => faseDe(proximo.value?.tournament));
const fechaProxima = computed(() => (proximo.value ? textoFecha(proximo.value.tournament) : null));
const proximoAbierto = computed(() => proximo.value?.tournament.registration_status === 'open');
const proximoPrivado = computed(() => esVerdadero(proximo.value?.tournament.is_private));
const puntosKillGeneral = computed(() => puntosPorKill(proximo.value?.tournament ?? torneosOrdenados.value[0]?.tournament));

const textoBotonProximo = computed(() => {
    if (proximoAbierto.value) return 'Inscribirme';
    if (proximoPrivado.value) return 'Ver la final';
    return 'Ver el torneo';
});

/* ------------------------------------------------------------------ */
/* Calculadora de la bolsa                                             */
/* ------------------------------------------------------------------ */

const jugadoresPremium = ref(50);
const precioPase = ref(10);

const bolsaTotal = computed(() => jugadoresPremium.value * precioPase.value);
const bolsaSemanal = computed(() => Math.round(bolsaTotal.value * 0.4));
const bolsaFinal = computed(() => Math.round(bolsaTotal.value * 0.4));
const bolsaSorteos = computed(() => Math.round(bolsaTotal.value * 0.2));

/* ------------------------------------------------------------------ */
/* Carga de datos                                                      */
/* ------------------------------------------------------------------ */

async function cargarTabla() {
    cargando.value = true;
    hayError.value = false;
    try {
        const res = await axios.get('/api/league/standings');
        if (!Array.isArray(res.data)) throw new Error('Respuesta inesperada de /api/league/standings');
        torneos.value = (res.data as BloqueLiga[]).filter((b) => b && b.tournament);

        // Abrimos la última fase que ya tiene jugadores (la "tabla actual").
        const ordenados = torneosOrdenados.value;
        let elegido = -1;
        ordenados.forEach((b, i) => {
            if (b.standings && b.standings.length > 0) elegido = i;
        });
        if (elegido < 0 && proximo.value) elegido = ordenados.indexOf(proximo.value);
        elegirTorneo(Math.max(elegido, 0));
    } catch (e) {
        console.error(e);
        hayError.value = true;
    } finally {
        cargando.value = false;
    }
}

onMounted(() => {
    // Iconos Phosphor por CDN (mismo patrón que Inicio.vue y Semanales).
    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/@phosphor-icons/web';
        script.async = true;
        document.head.appendChild(script);
    }
    window.addEventListener('keydown', alPresionarTecla);
    cargarTabla();
});

onUnmounted(() => {
    window.removeEventListener('keydown', alPresionarTecla);
});
</script>

<template>
    <Head title="Rankit Pro League" />

    <div class="min-h-screen overflow-x-clip bg-[#0a0a0a] font-sans text-gray-300">
        <!-- ============================ NAVEGACIÓN ============================ -->
        <nav aria-label="Principal" class="sticky top-0 z-40 border-b border-white/10 bg-[#0a0a0a]">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                <Link :href="route('Inicio')" class="foco flex min-w-0 items-center gap-2 rounded-lg">
                    <img
                        src="/public/league/logo.png"
                        alt=""
                        width="36"
                        height="36"
                        class="h-9 w-9 shrink-0 object-contain"
                        @error="ocultarImagen"
                    />
                    <span class="font-display text-lg font-bold uppercase leading-none text-white">
                        Rankit<span class="text-[var(--rankit-neon)]">.Pro</span>
                        <span class="mt-0.5 block text-[11px] font-semibold tracking-[0.2em] text-gray-400">League</span>
                    </span>
                    <span class="sr-only">(ir al inicio de Rankit)</span>
                </Link>

                <!-- Escritorio -->
                <div class="hidden items-center gap-1 lg:flex">
                    <a
                        v-for="s in SECCIONES"
                        :key="s.href"
                        :href="s.href"
                        class="foco rounded-lg px-3 py-2 text-sm font-semibold text-gray-300 hover:bg-white/5 hover:text-white"
                    >
                        {{ s.texto }}
                    </a>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="usuario"
                        :href="route('dashboard')"
                        class="foco hidden min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-300 hover:text-white lg:inline-flex"
                    >
                        Mi cuenta
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="foco hidden min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-gray-300 hover:text-white lg:inline-flex"
                    >
                        Entrar
                    </Link>
                    <a
                        href="#como-entrar"
                        class="foco hidden min-h-11 items-center rounded-xl bg-[var(--rankit-neon)] px-5 font-display text-sm font-bold uppercase tracking-wide text-white hover:bg-[#a300db] lg:inline-flex"
                    >
                        ¡Quiero jugar!
                    </a>

                    <!-- Botón del menú móvil -->
                    <button
                        ref="botonMenu"
                        type="button"
                        class="foco inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/15 px-4 text-sm font-semibold text-white lg:hidden"
                        aria-controls="menu-liga"
                        :aria-expanded="menuAbierto ? 'true' : 'false'"
                        @click="menuAbierto = !menuAbierto"
                    >
                        <i class="ph-bold text-xl" :class="menuAbierto ? 'ph-x' : 'ph-list'" aria-hidden="true"></i>
                        {{ menuAbierto ? 'Cerrar' : 'Menú' }}
                    </button>
                </div>
            </div>

            <!-- Menú móvil -->
            <div
                v-show="menuAbierto"
                id="menu-liga"
                class="max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-white/10 bg-[#0a0a0a] lg:hidden"
            >
                <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3 sm:px-6">
                    <a
                        v-for="s in SECCIONES"
                        :key="s.href"
                        :href="s.href"
                        class="foco flex min-h-12 items-center gap-3 rounded-lg px-3 text-base font-semibold text-gray-200 hover:bg-white/5"
                        @click="cerrarMenu"
                    >
                        <i class="ph-bold text-xl text-[var(--rankit-neon)]" :class="s.icono" aria-hidden="true"></i>
                        {{ s.texto }}
                    </a>
                    <div class="my-2 border-t border-white/10"></div>
                    <Link
                        :href="route('Inicio')"
                        class="foco flex min-h-12 items-center gap-3 rounded-lg px-3 text-base font-semibold text-gray-200 hover:bg-white/5"
                        @click="cerrarMenu"
                    >
                        <i class="ph-bold ph-house text-xl text-gray-400" aria-hidden="true"></i>
                        Inicio de Rankit
                    </Link>
                    <Link
                        v-if="usuario"
                        :href="route('dashboard')"
                        class="foco flex min-h-12 items-center gap-3 rounded-lg px-3 text-base font-semibold text-gray-200 hover:bg-white/5"
                        @click="cerrarMenu"
                    >
                        <i class="ph-bold ph-user-circle text-xl text-gray-400" aria-hidden="true"></i>
                        Mi cuenta
                    </Link>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="foco flex min-h-12 items-center gap-3 rounded-lg px-3 text-base font-semibold text-gray-200 hover:bg-white/5"
                            @click="cerrarMenu"
                        >
                            <i class="ph-bold ph-sign-in text-xl text-gray-400" aria-hidden="true"></i>
                            Entrar
                        </Link>
                        <Link
                            :href="route('register')"
                            class="foco mt-1 flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[var(--rankit-neon)] px-4 font-display text-base font-bold uppercase tracking-wide text-white hover:bg-[#a300db]"
                            @click="cerrarMenu"
                        >
                            Crear cuenta gratis
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="mx-auto max-w-6xl px-4 sm:px-6">
            <!-- ============================== INICIO ============================== -->
            <section id="inicio" class="grid scroll-mt-20 gap-6 py-10 sm:py-14 lg:grid-cols-[1.2fr_1fr] lg:items-center lg:gap-10">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wider text-gray-400">
                        Liga oficial · Fortnite · Solos
                    </p>
                    <h1 class="mt-3 break-words font-display text-4xl font-bold uppercase leading-none text-white sm:text-6xl">
                        Rankit <span class="text-[var(--rankit-neon)]">Pro</span> League
                    </h1>
                    <p class="mt-4 max-w-xl text-lg leading-relaxed text-gray-300">
                        La liga de Fortnite en Solos: juegas, sumas puntos por cada eliminación y los mejores llegan a la
                        <strong class="text-white">Gran Final</strong>.
                    </p>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        <a
                            href="#como-entrar"
                            class="foco inline-flex min-h-14 items-center justify-center gap-2 rounded-xl bg-[var(--rankit-neon)] px-7 font-display text-lg font-bold uppercase tracking-wide text-white hover:bg-[#a300db]"
                        >
                            <i class="ph-bold ph-game-controller text-2xl" aria-hidden="true"></i>
                            ¡Quiero jugar!
                        </a>
                        <a
                            href="#tabla"
                            class="foco inline-flex min-h-14 items-center justify-center gap-2 rounded-xl border border-white/20 px-7 font-display text-lg font-bold uppercase tracking-wide text-white hover:bg-white/5"
                        >
                            <i class="ph-bold ph-list-numbers text-2xl" aria-hidden="true"></i>
                            Ver la tabla
                        </a>
                    </div>
                </div>

                <!-- Lo próximo -->
                <div id="proximo" class="scroll-mt-20 rounded-2xl border border-white/10 bg-[#111111] p-5 sm:p-6">
                    <p class="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-gray-400">
                        <i class="ph-bold ph-calendar-blank text-lg text-[var(--rankit-neon)]" aria-hidden="true"></i>
                        Lo próximo
                    </p>

                    <div v-if="cargando" class="mt-4 flex items-center gap-3 text-gray-300" role="status">
                        <span
                            class="h-6 w-6 shrink-0 animate-spin rounded-full border-2 border-white/20 border-t-[var(--rankit-neon)] motion-reduce:animate-none"
                            aria-hidden="true"
                        ></span>
                        Buscando la próxima fecha…
                    </div>

                    <div v-else-if="hayError" class="mt-4">
                        <p class="text-gray-300">No pudimos cargar la próxima fecha.</p>
                        <button
                            type="button"
                            class="foco mt-3 inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/20 px-5 font-semibold text-white hover:bg-white/5"
                            @click="cargarTabla"
                        >
                            <i class="ph-bold ph-arrow-clockwise text-lg" aria-hidden="true"></i>
                            Reintentar
                        </button>
                    </div>

                    <div v-else-if="proximo" class="mt-4">
                        <p v-if="faseProxima" class="flex items-center gap-2 text-sm font-semibold text-gray-300">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: faseProxima.color }" aria-hidden="true"></span>
                            {{ faseProxima.semana }} · {{ faseProxima.lema }}
                        </p>
                        <h2 class="mt-1 font-display text-2xl font-bold uppercase text-white sm:text-3xl">
                            {{ nombreCorto(proximo.tournament.name) }}
                        </h2>
                        <p class="mt-3 flex items-center gap-2 text-lg font-semibold text-white">
                            <i class="ph-bold ph-clock text-xl text-gray-400" aria-hidden="true"></i>
                            {{ fechaProxima ?? 'Fecha por anunciar' }}
                        </p>
                        <p v-if="!fechaProxima" class="mt-1 text-sm text-gray-400">
                            Avisamos la fecha en
                            <a :href="INSTAGRAM" target="_blank" rel="noopener noreferrer" class="foco rounded font-semibold text-white underline underline-offset-2">Instagram</a>.
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-if="proximoAbierto"
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#0f2e22] px-3 py-1 text-sm font-semibold text-[#4ade80]"
                            >
                                <i class="ph-fill ph-check-circle" aria-hidden="true"></i> Inscripciones abiertas
                            </span>
                            <span
                                v-else-if="proximo.tournament.registration_status === 'closed'"
                                class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-sm font-semibold text-gray-300"
                            >
                                <i class="ph-fill ph-lock-simple" aria-hidden="true"></i> Inscripciones cerradas
                            </span>
                            <span
                                v-if="proximoPrivado"
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#2e2606] px-3 py-1 text-sm font-semibold text-[#f5c518]"
                            >
                                <i class="ph-fill ph-crown" aria-hidden="true"></i> Solo clasificados
                            </span>
                        </div>
                        <Link
                            :href="route('public.tournament.show', proximo.tournament.id)"
                            class="foco mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-white px-5 font-display text-base font-bold uppercase tracking-wide text-black hover:bg-gray-200 sm:w-auto"
                        >
                            {{ textoBotonProximo }}
                            <i class="ph-bold ph-arrow-right text-lg" aria-hidden="true"></i>
                        </Link>
                    </div>

                    <div v-else-if="torneosOrdenados.length" class="mt-4">
                        <h2 class="font-display text-2xl font-bold uppercase text-white">La temporada terminó</h2>
                        <p class="mt-2 text-gray-300">Mira quién quedó arriba en la tabla.</p>
                        <a
                            href="#tabla"
                            class="foco mt-4 inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-5 font-display font-bold uppercase tracking-wide text-black hover:bg-gray-200"
                        >
                            Ver la tabla
                        </a>
                    </div>

                    <div v-else class="mt-4">
                        <h2 class="font-display text-2xl font-bold uppercase text-white">Muy pronto</h2>
                        <p class="mt-2 text-gray-300">
                            Estamos preparando la próxima liga. Avisamos en
                            <a :href="INSTAGRAM" target="_blank" rel="noopener noreferrer" class="foco rounded font-semibold text-white underline underline-offset-2">Instagram</a>.
                        </p>
                    </div>
                </div>
            </section>

            <!-- =========================== CÓMO ENTRAR =========================== -->
            <section id="como-entrar" class="scroll-mt-20 py-10 sm:py-14" aria-labelledby="titulo-como-entrar">
                <h2 id="titulo-como-entrar" class="font-display text-3xl font-bold uppercase text-white">Cómo entrar</h2>
                <p class="mt-2 text-gray-400">Solo son 3 pasos.</p>

                <ol class="mt-6 grid gap-4 md:grid-cols-3">
                    <li class="flex flex-col rounded-2xl border border-white/10 bg-[#111111] p-5">
                        <div class="flex items-center gap-4">
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border-2 border-[var(--rankit-neon)] text-[var(--rankit-neon)]">
                                <i class="ph-bold ph-user-plus text-4xl" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-sm font-bold uppercase tracking-wider text-gray-400">Paso 1</p>
                                <h3 class="font-display text-xl font-bold uppercase text-white">Crea tu cuenta</h3>
                            </div>
                        </div>
                        <p class="mt-3 flex-1 text-gray-300">Es gratis. Con tu cuenta ves si ya quedaste inscrito.</p>
                        <p v-if="usuario" class="mt-4 flex min-h-12 items-center gap-2 font-semibold text-[#4ade80]">
                            <i class="ph-fill ph-check-circle text-2xl" aria-hidden="true"></i> Ya tienes cuenta
                        </p>
                        <Link
                            v-else
                            :href="route('register')"
                            class="foco mt-4 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/20 px-5 font-semibold text-white hover:bg-white/5"
                        >
                            Crear cuenta
                        </Link>
                    </li>

                    <li class="flex flex-col rounded-2xl border border-white/10 bg-[#111111] p-5">
                        <div class="flex items-center gap-4">
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border-2 border-[var(--rankit-neon)] text-[var(--rankit-neon)]">
                                <i class="ph-bold ph-ticket text-4xl" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-sm font-bold uppercase tracking-wider text-gray-400">Paso 2</p>
                                <h3 class="font-display text-xl font-bold uppercase text-white">Inscríbete</h3>
                            </div>
                        </div>
                        <p class="mt-3 flex-1 text-gray-300">Entra a la fase con inscripciones abiertas y llena tus datos.</p>
                        <Link
                            v-if="proximo"
                            :href="route('public.tournament.show', proximo.tournament.id)"
                            class="foco mt-4 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/20 px-5 font-semibold text-white hover:bg-white/5"
                        >
                            Ir a {{ nombreCorto(proximo.tournament.name) }}
                        </Link>
                        <a
                            v-else
                            href="#proximo"
                            class="foco mt-4 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/20 px-5 font-semibold text-white hover:bg-white/5"
                        >
                            Ver la próxima fecha
                        </a>
                    </li>

                    <li class="flex flex-col rounded-2xl border border-white/10 bg-[#111111] p-5">
                        <div class="flex items-center gap-4">
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl border-2 border-[var(--rankit-neon)] text-[var(--rankit-neon)]">
                                <i class="ph-bold ph-crosshair text-4xl" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="text-sm font-bold uppercase tracking-wider text-gray-400">Paso 3</p>
                                <h3 class="font-display text-xl font-bold uppercase text-white">Juega y suma</h3>
                            </div>
                        </div>
                        <p class="mt-3 flex-1 text-gray-300">
                            Cada eliminación vale {{ puntosKillGeneral }} puntos. Los mejores pasan a la siguiente fase.
                        </p>
                        <a
                            href="#tabla"
                            class="foco mt-4 inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/20 px-5 font-semibold text-white hover:bg-white/5"
                        >
                            Ver la tabla
                        </a>
                    </li>
                </ol>

                <p class="mt-4 flex items-start gap-2 text-sm text-gray-400">
                    <i class="ph-bold ph-info mt-0.5 text-base" aria-hidden="true"></i>
                    ¿Eres menor de edad? Inscríbete con ayuda de un adulto, sobre todo si hay que pagar.
                </p>
            </section>

            <!-- ======================= TABLA DE POSICIONES ======================= -->
            <section id="tabla" class="scroll-mt-20 py-10 sm:py-14" aria-labelledby="titulo-tabla">
                <h2 id="titulo-tabla" class="font-display text-3xl font-bold uppercase text-white">Tabla de posiciones</h2>
                <p class="mt-2 text-gray-400">Los puntos se actualizan cuando termina cada partida.</p>

                <div class="mt-6 rounded-2xl border border-white/10 bg-[#111111] p-4 sm:p-6">
                    <!-- Cargando -->
                    <div v-if="cargando" class="flex items-center justify-center gap-3 py-16 text-gray-300" role="status">
                        <span
                            class="h-8 w-8 shrink-0 animate-spin rounded-full border-4 border-white/20 border-t-[var(--rankit-neon)] motion-reduce:animate-none"
                            aria-hidden="true"
                        ></span>
                        Cargando la tabla…
                    </div>

                    <!-- Error -->
                    <div v-else-if="hayError" class="py-12 text-center" role="alert">
                        <i class="ph-bold ph-wifi-slash text-4xl text-gray-400" aria-hidden="true"></i>
                        <p class="mt-3 text-lg font-semibold text-white">No pudimos cargar la tabla.</p>
                        <p class="mt-1 text-gray-400">Revisa tu internet e inténtalo otra vez.</p>
                        <button
                            type="button"
                            class="foco mt-5 inline-flex min-h-12 items-center gap-2 rounded-xl bg-white px-6 font-display font-bold uppercase tracking-wide text-black hover:bg-gray-200"
                            @click="cargarTabla"
                        >
                            <i class="ph-bold ph-arrow-clockwise text-lg" aria-hidden="true"></i>
                            Reintentar
                        </button>
                    </div>

                    <!-- Sin torneos -->
                    <div v-else-if="torneosOrdenados.length === 0" class="py-12 text-center">
                        <i class="ph-bold ph-trophy text-4xl text-gray-400" aria-hidden="true"></i>
                        <p class="mt-3 text-lg font-semibold text-white">Aún no hay tablas de clasificación.</p>
                        <p class="mt-1 text-gray-400">No hay torneos de la liga activos en este momento.</p>
                    </div>

                    <template v-else>
                        <!-- Selector de fase -->
                        <p id="etiqueta-fases" class="text-sm font-bold uppercase tracking-wider text-gray-400">Elige la fase</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4" role="group" aria-labelledby="etiqueta-fases">
                            <button
                                v-for="(bloque, i) in torneosOrdenados"
                                :key="bloque.tournament.id"
                                type="button"
                                class="foco min-h-14 rounded-xl border px-3 py-2 text-left"
                                :class="
                                    indiceActivo === i
                                        ? 'border-[var(--rankit-neon)] bg-[var(--rankit-neon)] text-white'
                                        : 'border-white/15 text-gray-200 hover:bg-white/5'
                                "
                                :aria-pressed="indiceActivo === i ? 'true' : 'false'"
                                @click="elegirTorneo(i)"
                            >
                                <span class="block font-display text-base font-bold leading-tight">
                                    {{ nombreCorto(bloque.tournament.name) }}
                                </span>
                                <span
                                    v-if="faseDe(bloque.tournament)"
                                    class="block text-xs font-semibold"
                                    :class="indiceActivo === i ? 'text-white' : 'text-gray-400'"
                                >
                                    {{ faseDe(bloque.tournament)?.semana }}
                                </span>
                            </button>
                        </div>

                        <!-- Buscador -->
                        <div v-if="filas.length > 0" class="mt-5">
                            <label for="buscar-jugador" class="text-sm font-bold uppercase tracking-wider text-gray-400">
                                Busca tu nombre
                            </label>
                            <div class="relative mt-2">
                                <i
                                    class="ph-bold ph-magnifying-glass pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-gray-400"
                                    aria-hidden="true"
                                ></i>
                                <input
                                    id="buscar-jugador"
                                    v-model="busqueda"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="Escribe tu nombre de jugador"
                                    class="foco h-12 w-full rounded-xl border border-white/15 bg-[#0a0a0a] pl-11 pr-4 text-base text-white placeholder:text-gray-500 focus:border-[var(--rankit-neon)] focus:ring-0"
                                />
                            </div>
                        </div>

                        <p class="mt-5 text-sm text-gray-400">
                            1 eliminación = {{ puntosKillActivo }} puntos
                        </p>

                        <!-- Tabla -->
                        <div class="mt-2 overflow-hidden rounded-xl border border-white/10">
                            <table class="w-full table-fixed text-left">
                                <caption class="sr-only">
                                    Tabla de posiciones de {{ nombreCorto(bloqueActivo?.tournament.name) }}
                                </caption>
                                <thead class="bg-[#161616] text-xs font-bold uppercase tracking-wider text-gray-400">
                                    <tr>
                                        <th scope="col" class="w-14 px-2 py-3 text-center sm:w-16 sm:px-3">
                                            <span aria-hidden="true" class="sm:hidden">#</span>
                                            <span class="sr-only sm:not-sr-only">Lugar</span>
                                        </th>
                                        <th scope="col" class="px-2 py-3 sm:px-3">Jugador</th>
                                        <th scope="col" class="hidden w-24 px-3 py-3 text-right sm:table-cell">Partidas</th>
                                        <th scope="col" class="hidden w-36 px-3 py-3 text-right sm:table-cell">Eliminaciones</th>
                                        <th scope="col" class="hidden w-28 px-3 py-3 text-right md:table-cell">Daño</th>
                                        <th scope="col" class="w-20 px-2 py-3 text-right sm:w-24 sm:px-3">Puntos</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="fila in filasVisibles"
                                        :key="`${fila.lugar}-${fila.nombre}`"
                                        class="border-t border-white/5"
                                        :class="fila.lugar <= 3 ? 'bg-white/[0.04]' : ''"
                                    >
                                        <td class="px-2 py-3 text-center sm:px-3">
                                            <span
                                                v-if="fila.lugar <= 3"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-full font-display text-lg font-bold"
                                                :style="{ backgroundColor: MEDALLAS[fila.lugar - 1].fondo, color: MEDALLAS[fila.lugar - 1].texto }"
                                            >
                                                {{ fila.lugar }}<span class="sr-only">, medalla de {{ MEDALLAS[fila.lugar - 1].nombre }}</span>
                                            </span>
                                            <span
                                                v-else
                                                class="inline-flex h-10 w-10 items-center justify-center font-display text-lg font-bold text-gray-300"
                                            >
                                                {{ fila.lugar }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3 sm:px-3">
                                            <span class="block truncate font-bold text-white" :title="fila.nombre">{{ fila.nombre }}</span>
                                            <!-- En móvil los datos extra van debajo del nombre -->
                                            <span class="mt-0.5 flex flex-wrap gap-x-2 text-xs text-gray-400 sm:hidden">
                                                <span>{{ fmt(fila.kills) }} elim.</span>
                                                <span>{{ fmt(fila.partidas) }} partidas</span>
                                                <span>{{ fmt(fila.dano) }} daño</span>
                                            </span>
                                        </td>
                                        <td class="hidden px-3 py-3 text-right text-gray-300 sm:table-cell">{{ fmt(fila.partidas) }}</td>
                                        <td class="hidden px-3 py-3 text-right text-gray-300 sm:table-cell">{{ fmt(fila.kills) }}</td>
                                        <td class="hidden px-3 py-3 text-right text-gray-300 md:table-cell">{{ fmt(fila.dano) }}</td>
                                        <td class="px-2 py-3 text-right font-display text-lg font-bold text-white sm:px-3">{{ fmt(fila.puntos) }}</td>
                                    </tr>

                                    <tr v-if="filas.length === 0">
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                            Todavía no hay jugadores en esta fase. ¡Aquí saldrán cuando se juegue la primera partida!
                                        </td>
                                    </tr>
                                    <tr v-else-if="filasFiltradas.length === 0">
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                            No encontramos a «{{ busqueda.trim() }}» en esta fase.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="!textoBusqueda && filas.length > FILAS_INICIALES" class="mt-4 text-center">
                            <button
                                type="button"
                                class="foco inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/20 px-6 font-semibold text-white hover:bg-white/5"
                                @click="verTodos = !verTodos"
                            >
                                <i class="ph-bold text-lg" :class="verTodos ? 'ph-caret-up' : 'ph-caret-down'" aria-hidden="true"></i>
                                {{ verTodos ? `Ver solo el top ${FILAS_INICIALES}` : `Ver los ${fmt(filas.length)} jugadores` }}
                            </button>
                        </div>

                        <!-- Inscribirse a la fase elegida -->
                        <div
                            v-if="bloqueActivo"
                            class="mt-6 flex flex-col items-start gap-3 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <p class="text-gray-300">
                                ¿Quieres competir en
                                <strong class="text-white">{{ nombreCorto(bloqueActivo.tournament.name) }}</strong>?
                            </p>
                            <Link
                                :href="route('public.tournament.show', bloqueActivo.tournament.id)"
                                class="foco inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[var(--rankit-neon)] px-6 font-display font-bold uppercase tracking-wide text-white hover:bg-[#a300db] sm:w-auto"
                            >
                                <i class="ph-bold ph-ticket text-lg" aria-hidden="true"></i>
                                Inscríbete aquí
                            </Link>
                        </div>
                    </template>
                </div>
            </section>

            <!-- ============================== FASES ============================== -->
            <section id="fases" class="scroll-mt-20 py-10 sm:py-14" aria-labelledby="titulo-fases">
                <h2 id="titulo-fases" class="font-display text-3xl font-bold uppercase text-white">Las 4 fases</h2>
                <p class="mt-2 text-gray-400">Son 4 semanas, todo en Solos. Cada fase te acerca a la Gran Final.</p>

                <ol class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <li
                        v-for="fase in FASES"
                        :key="fase.num"
                        class="flex flex-col rounded-2xl border border-white/10 border-t-4 bg-[#111111] p-5"
                        :style="{ borderTopColor: fase.color }"
                    >
                        <div class="flex items-center gap-3">
                            <i class="ph-fill text-3xl" :class="fase.icono" :style="{ color: fase.color }" aria-hidden="true"></i>
                            <p class="text-sm font-bold uppercase tracking-wider text-gray-400">{{ fase.semana }}</p>
                        </div>
                        <h3 class="mt-3 font-display text-2xl font-bold uppercase leading-tight text-white">{{ fase.titulo }}</h3>
                        <p class="text-sm font-semibold text-gray-400">{{ fase.lema }}</p>
                        <p class="mt-3 flex-1 text-gray-300">{{ fase.resumen }}</p>
                        <p class="mt-3 text-sm font-semibold text-white">{{ fase.pase }}</p>

                        <details class="group mt-4 border-t border-white/10 pt-2">
                            <summary
                                class="foco flex min-h-12 cursor-pointer list-none items-center justify-between rounded-lg font-semibold text-white [&::-webkit-details-marker]:hidden"
                            >
                                Ver reglas
                                <i class="ph-bold ph-caret-down text-lg transition-transform group-open:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <ul class="mt-2 space-y-2 text-sm text-gray-300">
                                <li v-for="(regla, r) in fase.reglas" :key="r" class="flex gap-2">
                                    <i class="ph-bold ph-check mt-0.5 shrink-0" :style="{ color: fase.color }" aria-hidden="true"></i>
                                    <span>{{ regla }}</span>
                                </li>
                            </ul>
                            <img
                                :src="fase.poster"
                                :alt="`Póster de ${fase.titulo} (${fase.semana})`"
                                loading="lazy"
                                class="mt-4 w-full rounded-xl border border-white/10 object-cover"
                                @error="ocultarImagen"
                            />
                        </details>
                    </li>
                </ol>
            </section>

            <!-- ============================= PREMIOS ============================= -->
            <section id="premios" class="scroll-mt-20 py-10 sm:py-14" aria-labelledby="titulo-premios">
                <h2 id="titulo-premios" class="font-display text-3xl font-bold uppercase text-white">¿Cuánto se reparte?</h2>
                <p class="mt-2 max-w-2xl text-gray-400">
                    Los pases Premium llenan la bolsa de premios. Mueve las barras para ver cuánto se juntaría.
                </p>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl border border-white/10 bg-[#111111] p-5 sm:p-6">
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <label for="jugadores-premium" class="font-semibold text-white">Jugadores Premium</label>
                                <span class="font-display text-2xl font-bold text-white">{{ fmt(jugadoresPremium) }}</span>
                            </div>
                            <input
                                id="jugadores-premium"
                                v-model.number="jugadoresPremium"
                                type="range"
                                min="20"
                                max="500"
                                class="foco mt-2 h-10 w-full cursor-pointer accent-[var(--rankit-neon)]"
                            />
                        </div>
                        <div class="mt-5">
                            <div class="flex items-center justify-between gap-3">
                                <label for="precio-pase" class="font-semibold text-white">Precio del pase Upgrade</label>
                                <span class="font-display text-2xl font-bold text-white">${{ fmt(precioPase) }}</span>
                            </div>
                            <input
                                id="precio-pase"
                                v-model.number="precioPase"
                                type="range"
                                min="5"
                                max="30"
                                step="5"
                                class="foco mt-2 h-10 w-full cursor-pointer accent-[var(--rankit-neon)]"
                            />
                        </div>
                    </div>

                    <div class="rounded-2xl border border-white/10 bg-[#111111] p-5 sm:p-6" aria-live="polite">
                        <p class="text-sm font-bold uppercase tracking-wider text-gray-400">Bolsa estimada</p>
                        <p class="mt-1 font-display text-5xl font-bold text-white">${{ fmt(bolsaTotal) }}</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-white/10 p-4">
                                <p class="text-sm text-gray-400">40% · Premios de cada semana</p>
                                <p class="mt-1 font-display text-2xl font-bold text-white">${{ fmt(bolsaSemanal) }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 p-4">
                                <p class="text-sm text-gray-400">40% · Mega Pozo de la final</p>
                                <p class="mt-1 font-display text-2xl font-bold text-white">${{ fmt(bolsaFinal) }}</p>
                            </div>
                            <div class="rounded-xl border border-white/10 p-4">
                                <p class="text-sm text-gray-400">20% · Sorteos para todos</p>
                                <p class="mt-1 font-display text-2xl font-bold text-white">${{ fmt(bolsaSorteos) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ========================== OTROS TORNEOS ========================== -->
            <section id="otros" class="scroll-mt-20 py-10 sm:py-14" aria-labelledby="titulo-otros">
                <h2 id="titulo-otros" class="font-display text-3xl font-bold uppercase text-white">Otros torneos</h2>

                <Link
                    :href="route('semanales.index')"
                    class="foco mt-6 flex items-center gap-4 rounded-2xl border border-white/10 bg-[#111111] p-5 hover:border-white/30"
                >
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border-2 border-[#00e5ff] text-[#00e5ff]">
                        <i class="ph-bold ph-calendar-star text-3xl" aria-hidden="true"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-display text-xl font-bold uppercase text-white">Semanales</span>
                        <span class="block text-gray-300">Torneos gratis con premios cada semana. No cuentan para la liga.</span>
                    </span>
                    <i class="ph-bold ph-arrow-right shrink-0 text-2xl text-white" aria-hidden="true"></i>
                </Link>

                <details class="group mt-4 rounded-2xl border border-white/10 bg-[#111111] px-5">
                    <summary
                        class="foco flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 rounded-lg font-semibold text-white [&::-webkit-details-marker]:hidden"
                    >
                        Torneos rápidos en otros juegos
                        <i class="ph-bold ph-caret-down text-lg transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <p class="text-sm text-gray-400">
                        Torneos rápidos entre semana: inscripción baja y bolsa de premio directa.
                    </p>
                    <ul class="grid gap-3 pb-5 pt-4 sm:grid-cols-2">
                        <li
                            v-for="t in OTROS_TORNEOS"
                            :key="t.nombre"
                            class="rounded-xl border border-white/10 border-l-4 p-4"
                            :style="{ borderLeftColor: t.color }"
                        >
                            <p class="text-xs font-bold uppercase tracking-wider" :style="{ color: t.color }">{{ t.juego }}</p>
                            <h3 class="mt-1 font-display text-lg font-bold uppercase text-white">{{ t.nombre }}</h3>
                            <p class="mt-1 text-sm text-gray-300">{{ t.descripcion }}</p>
                            <div class="mt-3 flex items-center justify-between gap-2 text-sm">
                                <span class="font-semibold text-white">Entrada: {{ t.entrada }}</span>
                                <span class="rounded-full bg-white/10 px-3 py-1 font-semibold text-gray-300">Próximamente</span>
                            </div>
                        </li>
                    </ul>
                </details>
            </section>
        </main>

        <!-- ============================== PIE ============================== -->
        <footer class="mt-6 border-t border-white/10 px-4 py-10 text-center sm:px-6">
            <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a
                    :href="INSTAGRAM"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="foco inline-flex min-h-12 items-center gap-2 rounded-xl border border-white/15 px-5 font-semibold text-white hover:bg-white/5"
                >
                    <i class="ph-bold ph-instagram-logo text-xl" aria-hidden="true"></i>
                    @rankit.pro
                </a>
                <a
                    href="#inicio"
                    class="foco inline-flex min-h-12 items-center gap-2 rounded-xl px-5 font-semibold text-gray-300 hover:text-white"
                >
                    <i class="ph-bold ph-arrow-up text-xl" aria-hidden="true"></i>
                    Volver arriba
                </a>
            </div>
            <p class="mt-6 font-display text-sm font-bold uppercase tracking-widest text-gray-400">&copy; 2026 Rankit Pro League</p>
            <p class="mx-auto mt-3 max-w-2xl text-xs leading-relaxed text-gray-400">
                Esta liga competitiva no está afiliada ni patrocinada por Epic Games. Todo el circuito es en formato solitario.
                Los pases Premium alimentan directamente las bolsas de premio semanales y la Gran Final. La Gran Final no tiene
                costo de inscripción para los clasificados.
            </p>
        </footer>
    </div>
</template>

<style scoped>
/* Foco visible y de alto contraste en todo lo que se puede tocar o tabular. */
.foco:focus-visible {
    outline: 3px solid #ffffff;
    outline-offset: 3px;
}
</style>
