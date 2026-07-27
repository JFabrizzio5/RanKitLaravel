<script setup>
/**
 * 1V1 & WAGERS · Landing "coming soon".
 * Explica lo que viene: cobro de inscripciones dentro de la plataforma, retos
 * 1v1 con bolsa y wagers entre equipos. Pensada para que organizadores y
 * streamers entiendan el producto y contacten por WhatsApp.
 */
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import RankitContactWidget from '@/Components/RankitContactWidget.vue'

const WA = '525532351392'

const menuAbierto = ref(false)

function irA(id) {
    menuAbierto.value = false
    const destino = document.getElementById(id)
    if (destino) destino.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const enlaceInteres = computed(() => {
    const msg =
        'Hola Rankit, me interesa el módulo de 1v1 / Wagers y el cobro de inscripciones en la plataforma. ¿Me avisan cuando abra?'
    return 'https://wa.me/' + WA + '?text=' + encodeURIComponent(msg)
})

/* Módulos que vienen en camino */
const modulos = [
    {
        icono: 'ph-credit-card',
        color: 'text-fnEmerald',
        borde: 'border-fnEmerald/30',
        fondo: 'bg-fnEmerald/5',
        titulo: 'Cobro de inscripciones',
        estado: 'En desarrollo',
        texto:
            'Abre inscripciones con costo y recibe el dinero dentro de Rankit. Se acabaron las transferencias sueltas, las capturas de pantalla y la hoja de cálculo para saber quién pagó.',
        puntos: [
            'Cobro en línea al momento de inscribirse',
            'Cupo y lista de espera automáticos',
            'Control de pagados, pendientes y reembolsos',
            'Corte y reporte por evento',
        ],
    },
    {
        icono: 'ph-sword',
        color: 'text-fnNeonCyan',
        borde: 'border-fnNeonCyan/30',
        fondo: 'bg-fnNeonCyan/5',
        titulo: 'Duelos 1v1',
        estado: 'Próximamente',
        texto:
            'Retos directos entre dos jugadores con bolsa de por medio. Cada quien pone su parte, Rankit la resguarda y el ganador se la lleva cuando el resultado queda validado.',
        puntos: [
            'Reto abierto o invitación directa',
            'Bolsa resguardada hasta validar el resultado',
            'Validación con captura, replay o reporte',
            'Historial y récord público de cada jugador',
        ],
    },
    {
        icono: 'ph-users-three',
        color: 'text-fnMagenta',
        borde: 'border-fnMagenta/30',
        fondo: 'bg-fnMagenta/5',
        titulo: 'Wagers por equipos',
        estado: 'Próximamente',
        texto:
            'Lo mismo que el 1v1 pero entre escuadras: dos equipos acuerdan la bolsa, juegan su serie y el sistema reparte según el resultado.',
        puntos: [
            'Retos de equipo contra equipo',
            'Aportación por integrante o por equipo',
            'Reparto automático de la bolsa',
            'Tabla de récords por escuadra',
        ],
    },
]

/* Por qué le sirve a cada perfil */
const perfiles = [
    {
        icono: 'ph-video-camera',
        titulo: 'Streamers y creadores',
        texto:
            'Monetiza tu comunidad sin depender de donativos: cobra la entrada de tus torneos, arma retos 1v1 en vivo y saca los datos al stream con los widgets de OBS.',
        bullets: ['Widgets de OBS con tablas en vivo', 'Retos con tu comunidad', 'Ingreso por inscripciones'],
    },
    {
        icono: 'ph-megaphone',
        titulo: 'Organizadores de eventos',
        texto:
            'Deja de perseguir pagos por WhatsApp. Abres el registro, cobras, armas el bracket y publicas resultados desde un solo lugar.',
        bullets: ['Inscripciones y cobros en un flujo', 'Brackets y calendario automáticos', 'Página pública del evento'],
    },
    {
        icono: 'ph-buildings',
        titulo: 'Negocios y canchas',
        texto:
            'Llena horarios muertos con torneos recurrentes y cobra en línea. Sirve igual para un centro de gaming que para una cancha de fútbol 7.',
        bullets: ['Ligas y torneos recurrentes', 'Reservas y cupos', 'Reporte por evento'],
    },
]

const faqs = [
    {
        p: '¿Cuándo estará disponible?',
        r: 'El cobro de inscripciones es lo primero en salir; 1v1 y wagers vienen después. No damos fecha todavía porque no anunciamos nada que no esté listo. Déjanos tu contacto y te avisamos apenas abra.',
    },
    {
        p: '¿Cómo se resguarda la bolsa de un reto?',
        r: 'Los participantes aportan antes de jugar y Rankit retiene el monto hasta que el resultado queda validado. Si el reto se cancela o no se juega, se devuelve.',
    },
    {
        p: '¿Y si hay una disputa?',
        r: 'Cada reto guarda evidencia (captura, replay o reporte). Si las partes no coinciden, el caso pasa a revisión de la organización antes de liberar la bolsa.',
    },
    {
        p: '¿Puedo usarlo para mi liga que ya existe?',
        r: 'Sí. Rankit ya gestiona torneos y ligas hoy; estos módulos se suman a lo que ya tienes montado, sin empezar de cero.',
    },
    {
        p: '¿Esto tiene costo extra?',
        r: 'La gestión va con tu plan. El cobro de inscripciones y los retos con bolsa llevan una comisión por transacción que te decimos claramente antes de activarlo.',
    },
]

const faqAbierta = ref(0)
function alternarFaq(i) {
    faqAbierta.value = faqAbierta.value === i ? -1 : i
}

onMounted(() => {
    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script')
        script.src = 'https://unpkg.com/@phosphor-icons/web'
        script.async = true
        document.head.appendChild(script)
    }
})
</script>

<template>
    <Head title="1v1 y Wagers | Rankit">
        <meta
            name="description"
            content="Cobro de inscripciones dentro de la plataforma, duelos 1v1 con bolsa y wagers entre equipos. Lo que viene en Rankit para organizadores, streamers y negocios."
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div class="min-h-screen text-white bg-[#050505] font-sans selection:bg-[var(--rankit-neon)] selection:text-white">
        <!-- ============================== NAV ============================== -->
        <nav class="fixed inset-x-0 top-0 z-50 border-b border-white/10 bg-[#050505]/95 backdrop-blur-md">
            <div class="flex items-center justify-between h-20 px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-3 group">
                    <svg class="w-9 h-9 text-white transition-colors group-hover:text-[var(--rankit-neon)]" viewBox="0 0 100 100" fill="none">
                        <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                        <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                        <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
                    </svg>
                    <span class="text-2xl italic font-bold tracking-tighter uppercase font-display">Rankit</span>
                </Link>

                <div class="items-center hidden gap-7 text-xs font-bold tracking-widest text-gray-400 uppercase md:flex">
                    <Link href="/" class="transition hover:text-white">Inicio</Link>
                    <Link href="/league" class="transition hover:text-white">League</Link>
                    <Link href="/semanales" class="transition hover:text-white">Semanales</Link>
                    <Link href="/mundial" class="transition hover:text-white">Mundial</Link>
                    <span class="text-[var(--rankit-neon)]">1v1 / Duels</span>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        :href="enlaceInteres"
                        target="_blank"
                        rel="noopener"
                        class="hidden px-5 py-2 text-xs font-bold tracking-wider uppercase sm:inline-flex btn-skew"
                    >
                        <span class="btn-content">Quiero enterarme</span>
                    </a>
                    <button class="p-2 text-white md:hidden hover:text-[var(--rankit-neon)]" aria-label="Menú" @click="menuAbierto = !menuAbierto">
                        <i class="text-2xl ph-bold" :class="menuAbierto ? 'ph-x' : 'ph-list'"></i>
                    </button>
                </div>
            </div>

            <div v-show="menuAbierto" class="px-6 py-5 space-y-4 border-t md:hidden border-white/10 bg-[#050505]">
                <Link href="/" class="block text-sm font-bold tracking-wider text-gray-300 uppercase" @click="menuAbierto = false">Inicio</Link>
                <Link href="/league" class="block text-sm font-bold tracking-wider text-gray-300 uppercase" @click="menuAbierto = false">League</Link>
                <Link href="/semanales" class="block text-sm font-bold tracking-wider text-gray-300 uppercase" @click="menuAbierto = false">Semanales</Link>
                <Link href="/mundial" class="block text-sm font-bold tracking-wider text-gray-300 uppercase" @click="menuAbierto = false">Mundial</Link>
                <a :href="enlaceInteres" target="_blank" rel="noopener" class="block py-3 text-sm font-bold tracking-wider text-center uppercase btn-skew">
                    <span class="btn-content">Quiero enterarme</span>
                </a>
            </div>
        </nav>

        <!-- ============================== HERO ============================= -->
        <header class="relative pt-20 overflow-hidden bg-tech-grid-dark bg-[length:40px_40px]">
            <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-[var(--rankit-neon)]/10 via-transparent to-[#050505]"></div>

            <div class="relative z-10 px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-28">
                <div class="max-w-3xl">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 mb-6 text-[11px] font-bold tracking-[0.25em] uppercase border border-fnGold/50 text-fnGold bg-fnGold/10">
                        <i class="ph-fill ph-hammer"></i> En construcción · Próximamente
                    </span>

                    <h1 class="text-5xl italic font-black leading-none tracking-tighter uppercase sm:text-7xl font-display">
                        1V1 <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-fnNeonCyan">& WAGERS</span>
                    </h1>

                    <p class="max-w-2xl mt-6 text-lg font-light leading-relaxed text-gray-300 border-l-4 border-[var(--rankit-neon)] pl-5">
                        Estamos construyendo la parte que le faltaba al ecosistema: <strong class="font-bold text-white">cobrar
                        inscripciones dentro de la plataforma</strong> y dejar que jugadores y equipos se reten
                        <strong class="font-bold text-white">con bolsa de por medio</strong>, con el dinero resguardado hasta que
                        haya un resultado validado.
                    </p>

                    <div class="flex flex-col gap-4 mt-10 sm:flex-row">
                        <a :href="enlaceInteres" target="_blank" rel="noopener" class="px-8 py-4 text-sm font-bold tracking-wider text-center uppercase btn-skew">
                            <span class="btn-content"><i class="ph-fill ph-whatsapp-logo"></i> Avísenme cuando abra</span>
                        </a>
                        <button
                            class="px-8 py-4 text-sm font-bold tracking-wider text-gray-300 uppercase transition-colors border border-white/20 hover:border-[var(--rankit-neon)] hover:text-white"
                            @click="irA('modulos')"
                        >
                            Ver qué incluye <i class="ph-bold ph-arrow-down"></i>
                        </button>
                    </div>

                    <p class="mt-6 text-xs text-gray-500">
                        Mientras tanto, lo que ya funciona hoy: torneos, ligas, brackets, inscripciones y widgets de OBS.
                        <Link href="/" class="underline text-[var(--rankit-neon)] hover:text-white">Ver la plataforma</Link>
                    </p>
                </div>
            </div>
        </header>

        <!-- ============================ MÓDULOS ============================ -->
        <section id="modulos" class="border-t border-white/5 bg-[#080808] scroll-mt-24">
            <div class="px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="max-w-2xl mb-14">
                    <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnNeonCyan">Lo que viene</span>
                    <h2 class="mb-4 text-4xl italic font-black tracking-tight uppercase font-display sm:text-5xl">Tres piezas nuevas</h2>
                    <p class="font-light text-gray-400">
                        Ninguna está disponible todavía. Las publicamos aquí para que sepas hacia dónde va la plataforma
                        antes de contratar, no después.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <article v-for="m in modulos" :key="m.titulo" class="flex flex-col p-6 border sm:p-8" :class="[m.borde, m.fondo]">
                        <div class="flex items-start justify-between mb-5">
                            <i class="text-4xl ph-fill" :class="[m.icono, m.color]"></i>
                            <span class="px-2 py-1 text-[9px] font-bold tracking-widest uppercase border border-fnGold/40 text-fnGold bg-fnGold/10">
                                {{ m.estado }}
                            </span>
                        </div>

                        <h3 class="mb-3 text-2xl italic font-black uppercase font-display">{{ m.titulo }}</h3>
                        <p class="mb-6 text-sm leading-relaxed text-gray-400">{{ m.texto }}</p>

                        <ul class="mt-auto space-y-2.5">
                            <li v-for="p in m.puntos" :key="p" class="flex items-start gap-2 text-sm text-gray-300">
                                <i class="mt-1 text-xs ph-bold ph-check" :class="m.color"></i>
                                <span>{{ p }}</span>
                            </li>
                        </ul>
                    </article>
                </div>

                <p class="mt-8 text-xs leading-relaxed text-gray-500">
                    <i class="ph-fill ph-info text-fnNeonCyan"></i>
                    Los retos con bolsa se habilitarán por región según lo que permita la normativa local. Si tu caso
                    necesita algo distinto, escríbenos y lo revisamos contigo antes de prometerte nada.
                </p>
            </div>
        </section>

        <!-- ============================ PERFILES =========================== -->
        <section class="border-t border-white/5">
            <div class="px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="max-w-2xl mb-14">
                    <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnMagenta">Para quién es</span>
                    <h2 class="mb-4 text-4xl italic font-black tracking-tight uppercase font-display sm:text-5xl">
                        Si organizas, esto es para ti
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div v-for="p in perfiles" :key="p.titulo" class="p-6 border border-white/10 bg-white/[0.02] hover:border-[var(--rankit-neon)]/50 transition-colors">
                        <i class="mb-4 text-3xl ph-fill text-[var(--rankit-neon)]" :class="p.icono"></i>
                        <h3 class="mb-3 text-xl italic font-black uppercase font-display">{{ p.titulo }}</h3>
                        <p class="mb-5 text-sm leading-relaxed text-gray-400">{{ p.texto }}</p>
                        <ul class="space-y-2">
                            <li v-for="b in p.bullets" :key="b" class="flex items-start gap-2 text-xs text-gray-300">
                                <i class="mt-0.5 ph-fill ph-check-circle text-fnEmerald"></i>
                                <span>{{ b }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================== CÓMO FUNCIONA ======================== -->
        <section class="border-t border-white/5 bg-[#080808]">
            <div class="px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="max-w-2xl mb-14">
                    <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnEmerald">Cómo funcionará un reto</span>
                    <h2 class="text-4xl italic font-black tracking-tight uppercase font-display sm:text-5xl">Cuatro pasos</h2>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="(paso, i) in [
                            { t: 'Se crea el reto', d: 'Uno lanza el desafío: modo, formato y monto de la bolsa.' },
                            { t: 'Ambos aportan', d: 'Las dos partes ponen su parte y Rankit la resguarda.' },
                            { t: 'Se juega', d: 'Juegan la partida o la serie acordada y suben la evidencia.' },
                            { t: 'Se libera la bolsa', d: 'Validado el resultado, el ganador recibe. Si hay disputa, se revisa.' },
                        ]"
                        :key="paso.t"
                        class="relative p-6 border border-white/10 bg-white/[0.02]"
                    >
                        <span class="absolute text-5xl italic font-black text-white/5 font-display top-3 right-4">
                            {{ i + 1 < 10 ? '0' + (i + 1) : i + 1 }}
                        </span>
                        <h3 class="relative mb-2 text-base font-bold tracking-wide uppercase font-display">{{ paso.t }}</h3>
                        <p class="relative text-sm leading-relaxed text-gray-400">{{ paso.d }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== FAQ ============================== -->
        <section class="border-t border-white/5">
            <div class="max-w-4xl px-4 py-20 mx-auto sm:px-6 lg:px-8 sm:py-24">
                <h2 class="mb-10 text-4xl italic font-black tracking-tight uppercase font-display sm:text-5xl">Preguntas</h2>

                <div class="divide-y divide-white/10">
                    <div v-for="(f, i) in faqs" :key="f.p">
                        <button class="flex items-center justify-between w-full gap-4 py-5 text-left" @click="alternarFaq(i)">
                            <span class="text-sm font-bold tracking-wide text-white uppercase sm:text-base">{{ f.p }}</span>
                            <i
                                class="text-lg transition-transform ph-bold ph-caret-down text-[var(--rankit-neon)] shrink-0"
                                :class="faqAbierta === i ? 'rotate-180' : ''"
                            ></i>
                        </button>
                        <p v-show="faqAbierta === i" class="pb-5 -mt-1 text-sm leading-relaxed text-gray-400">{{ f.r }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== CTA ============================== -->
        <section class="border-t border-white/5 bg-gradient-to-b from-[var(--rankit-neon)]/10 to-transparent">
            <div class="max-w-4xl px-4 py-20 mx-auto text-center sm:px-6 lg:px-8 sm:py-24">
                <h2 class="mb-5 text-4xl italic font-black tracking-tight uppercase font-display sm:text-5xl">
                    ¿Lo quieres para tu comunidad?
                </h2>
                <p class="max-w-2xl mx-auto mb-10 font-light text-gray-300">
                    Cuéntanos qué organizas y te avisamos en cuanto abra el acceso. Si además quieres montar tus torneos
                    o tu liga desde hoy, lo vemos en la misma conversación.
                </p>

                <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <a :href="enlaceInteres" target="_blank" rel="noopener" class="px-8 py-4 text-sm font-bold tracking-wider uppercase btn-skew">
                        <span class="btn-content"><i class="ph-fill ph-whatsapp-logo"></i> Escribir por WhatsApp</span>
                    </a>
                    <a
                        href="https://instagram.com/rankit.pro"
                        target="_blank"
                        rel="noopener"
                        class="px-8 py-4 text-sm font-bold tracking-wider text-gray-300 uppercase transition-colors border border-white/20 hover:border-[var(--rankit-neon)] hover:text-white"
                    >
                        <i class="ph-fill ph-instagram-logo"></i> @rankit.pro
                    </a>
                </div>
            </div>
        </section>

        <!-- ============================ FOOTER ============================= -->
        <footer class="border-t border-white/10 bg-[#050505]">
            <div class="flex flex-col items-center justify-between gap-4 px-4 py-10 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:flex-row">
                <p class="text-xs tracking-wider text-gray-500 uppercase">
                    Rankit · Un producto de
                    <a href="https://cometax.click" target="_blank" rel="noopener" class="text-[var(--rankit-neon)] hover:text-white">CometaX</a>
                </p>
                <div class="flex gap-6 text-xs tracking-wider text-gray-500 uppercase">
                    <Link href="/" class="hover:text-white">Inicio</Link>
                    <Link href="/semanales" class="hover:text-white">Semanales</Link>
                    <Link href="/league" class="hover:text-white">League</Link>
                </div>
            </div>
        </footer>

        <RankitContactWidget origen="Landing 1v1 / Wagers" />
    </div>
</template>
