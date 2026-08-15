<script setup>
/**
 * SEMANALES · Landing promocional independiente de Rankit.
 * Eventos gratuitos con premios en metálico. NO forman parte de Rankit League.
 * Página siempre en modo oscuro (no depende del toggle de tema de Inicio.vue).
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import RankitContactWidget from '@/Components/RankitContactWidget.vue'

const props = defineProps({
    semanales: { type: Array, default: () => [] },
    ownerEmail: { type: String, default: null },
    canRegister: { type: Boolean, default: false },
    errorMessage: { type: String, default: null },
    isAdmin: { type: Boolean, default: false },
})

const page = usePage()
const usuario = computed(() => page.props?.auth?.user ?? null)
const esAdmin = computed(() => props.isAdmin === true)

/* ------------------------------------------------------------------ */
/* Datos derivados                                                      */
/* ------------------------------------------------------------------ */

const listaSemanales = computed(() => (Array.isArray(props.semanales) ? props.semanales : []))
const hayEventos = computed(() => listaSemanales.value.length > 0)

const totalInscritos = computed(() =>
    listaSemanales.value.reduce((acc, ev) => acc + (Number(ev?.players_count) || 0), 0),
)

// Ids inscritos en esta sesión (feedback inmediato incluso para invitados)
const inscritosLocal = ref([])

function estaInscrito(ev) {
    if (!ev) return false
    return ev.is_registered === true || inscritosLocal.value.includes(ev.id)
}

function registroAbierto(ev) {
    return ev?.registration_status === 'open'
}

function dosDigitos(n) {
    const num = Number(n) || 0
    return num < 10 ? '0' + num : String(num)
}

function etiquetaFecha(ev) {
    return ev?.date_label || 'PRÓXIMAMENTE'
}

function etiquetaEntrada(ev) {
    return ev?.entry_fee_label || (ev?.is_free ? 'GRATIS' : 'POR ANUNCIAR')
}

function textoEstado(ev) {
    if (estaInscrito(ev)) return 'YA ESTÁS INSCRITO'
    if (registroAbierto(ev)) return 'INSCRIPCIONES ABIERTAS'
    if (ev?.registration_status === 'closed') return 'INSCRIPCIONES CERRADAS'
    return 'REGISTRO NO DISPONIBLE'
}

function claseEstado(ev) {
    if (estaInscrito(ev)) return 'text-fnEmerald border-fnEmerald/50 bg-fnEmerald/10'
    if (registroAbierto(ev)) return 'text-fnNeonCyan border-fnNeonCyan/50 bg-fnNeonCyan/10'
    return 'text-gray-400 border-white/15 bg-white/5'
}

function textoBoton(ev) {
    if (estaInscrito(ev)) return 'YA ESTÁS INSCRITO'
    if (registroAbierto(ev)) return usuario.value ? 'INSCRIBIRME GRATIS' : 'INICIA SESIÓN PARA INSCRIBIRTE'
    if (ev?.registration_status === 'closed') return 'INSCRIPCIONES CERRADAS'
    return 'REGISTRO NO DISPONIBLE'
}

function iconoBoton(ev) {
    if (estaInscrito(ev)) return 'ph-check-circle'
    if (registroAbierto(ev) && !usuario.value) return 'ph-sign-in'
    return 'ph-user-plus'
}

function botonDeshabilitado(ev) {
    return !registroAbierto(ev) || estaInscrito(ev)
}

/* ------------------------------------------------------------------ */
/* Navegación / UI                                                      */
/* ------------------------------------------------------------------ */

const menuAbierto = ref(false)

function irA(id) {
    menuAbierto.value = false
    const destino = document.getElementById(id)
    if (destino) destino.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const faqAbierta = ref(0)
function alternarFaq(i) {
    faqAbierta.value = faqAbierta.value === i ? -1 : i
}

const faqs = [
    {
        p: '¿Cuánto cuesta participar?',
        r: 'Nada. La entrada a los Semanales es completamente gratuita. No pedimos pago, ni suscripción, ni tarjeta.',
    },
    {
        p: '¿Qué datos son obligatorios?',
        r: 'Tu Epic ID y tu WhatsApp. Con el Epic ID te identificamos dentro de la partida y por WhatsApp te mandamos el aviso. El Discord es opcional: déjalo si quieres estar en la comunidad.',
    },
    {
        p: '¿Cuándo se juegan?',
        r: 'La fecha de cada Semanal aparece en su tarjeta, aquí arriba. Cuando un Semanal ya se jugó, su registro queda cerrado y el siguiente sigue abierto. La hora del lobby te llega por WhatsApp.',
    },
    {
        p: '¿Puedo inscribirme a los dos Semanales?',
        r: 'Sí. Son eventos independientes entre sí: puedes registrarte en cada uno, pero solo con un registro por persona en cada evento.',
    },
    {
        p: '¿Los Semanales dan puntos de Rankit League?',
        r: 'No. Son eventos promocionales independientes. No suman, restan ni afectan la tabla de Rankit League.',
    },
    {
        p: '¿Cómo se pagan los premios?',
        r: 'En metálico. El monto de la bolsa y el método de entrega se anuncian junto con la fecha de cada Semanal.',
    },
]

const pasos = [
    {
        icono: 'ph-user-plus',
        titulo: 'Te inscribes gratis',
        texto: 'Llenas el formulario con tu gamertag, tu Epic ID y tu WhatsApp. El Discord es opcional. Sin pagos, sin letras chiquitas.',
    },
    {
        icono: 'ph-chat-circle-text',
        titulo: 'Te notificamos por mensaje',
        texto: 'Te llega un mensaje a tu WhatsApp con la fecha y la hora en cuanto se confirmen. Nada de andar adivinando.',
    },
    {
        icono: 'ph-envelope-simple',
        titulo: 'Los códigos llegan a tu correo',
        texto: 'Una vez inscrito, el código de la partida privada te llega por correo al que tienes en tu cuenta.',
    },
    {
        icono: 'ph-trophy',
        titulo: 'Sigue tu avance en la app',
        texto: 'Entra al Semanal desde la app y revisa las tablas y cómo vas mientras se juega. Compites por la bolsa semanal en metálico.',
    },
]

const requisitos = [
    { icono: 'ph-game-controller', texto: 'Epic ID de tu cuenta de juego (obligatorio).' },
    { icono: 'ph-whatsapp-logo', texto: 'WhatsApp con el número que registres (obligatorio): ahí te notificamos.' },
    { icono: 'ph-envelope-simple', texto: 'Correo de tu cuenta Rankit: ahí te llegan los códigos de partida.' },
    { icono: 'ph-discord-logo', texto: 'Discord (opcional): para estar en la comunidad.' },
    { icono: 'ph-user-check', texto: 'Un registro por persona y por evento.' },
    { icono: 'ph-currency-circle-dollar', texto: 'Entrada 100% gratuita, sin costos ocultos.' },
    { icono: 'ph-users-three', texto: 'Cupos limitados: el orden de inscripción cuenta.' },
]

/* ------------------------------------------------------------------ */
/* Modal de inscripción                                                 */
/* ------------------------------------------------------------------ */

const modalAbierto = ref(false)
const eventoActivo = ref(null)
const enviando = ref(false)
const mensaje = ref('')
const mensajeOk = ref(false)
const errores = ref({})
const inputNombre = ref(null)
let cierreProgramado = null
let overflowPrevio = ''

const formulario = ref({
    player_name: '',
    epic_id: '',
    whatsapp: '',
    discord: '',
})

function limpiarFormulario() {
    // El nombre se precarga con el de la cuenta; el correo NO se pide: sale de la sesión.
    formulario.value = {
        player_name: usuario.value?.name || '',
        epic_id: '',
        whatsapp: '',
        discord: '',
    }
    errores.value = {}
    mensaje.value = ''
    mensajeOk.value = false
}

function bloquearScroll() {
    overflowPrevio = document.body.style.overflow
    document.body.style.overflow = 'hidden'
}

function restaurarScroll() {
    document.body.style.overflow = overflowPrevio || ''
}

function abrirModal(ev) {
    if (!ev || botonDeshabilitado(ev)) return

    // Sin cuenta no hay inscripción: mandamos al login y volvemos aquí al terminar.
    if (!usuario.value) {
        router.visit('/login')
        return
    }

    eventoActivo.value = ev
    limpiarFormulario()
    modalAbierto.value = true
    bloquearScroll()
    nextTick(() => {
        if (inputNombre.value) inputNombre.value.focus()
    })
}

function cerrarModal() {
    if (enviando.value) return
    if (cierreProgramado) {
        clearTimeout(cierreProgramado)
        cierreProgramado = null
    }
    modalAbierto.value = false
    eventoActivo.value = null
    restaurarScroll()
}

function manejarTecla(e) {
    if (e.key !== 'Escape') return
    if (modalAbierto.value) cerrarModal()
    else if (avisoAbierto.value) cerrarAviso()
}

function validar() {
    const errs = {}
    const nombre = (formulario.value.player_name || '').trim()
    const whats = (formulario.value.whatsapp || '').trim()
    const disc = (formulario.value.discord || '').trim()
    const epic = (formulario.value.epic_id || '').trim()

    if (!nombre) errs.player_name = 'Escribe tu nombre o gamertag.'
    else if (nombre.length < 3) errs.player_name = 'Debe tener al menos 3 caracteres.'
    else if (nombre.length > 60) errs.player_name = 'Máximo 60 caracteres.'

    const digitos = whats.replace(/\D/g, '')
    if (!whats) errs.whatsapp = 'Tu WhatsApp es obligatorio para avisarte del evento.'
    else if (digitos.length < 10) errs.whatsapp = 'Incluye al menos 10 dígitos (con lada).'
    else if (digitos.length > 15) errs.whatsapp = 'Revisa el número, tiene demasiados dígitos.'

    if (!epic) errs.epic_id = 'Tu Epic ID es obligatorio: con él te identificamos en la partida.'
    else if (epic.length < 3) errs.epic_id = 'El Epic ID es demasiado corto.'
    else if (epic.length > 60) errs.epic_id = 'Máximo 60 caracteres.'

    // Discord es OPCIONAL: sólo validamos la longitud si lo escribieron.
    if (disc && disc.length < 2) errs.discord = 'Usuario de Discord demasiado corto.'
    else if (disc.length > 60) errs.discord = 'Máximo 60 caracteres.'

    errores.value = errs
    return Object.keys(errs).length === 0
}

function enviarInscripcion() {
    if (enviando.value) return
    mensaje.value = ''
    mensajeOk.value = false

    // Si la sesión se cayó mientras el modal estaba abierto, al login.
    if (!usuario.value) {
        router.visit('/login')
        return
    }

    if (!validar()) {
        mensaje.value = 'Revisa los campos marcados en rojo.'
        mensajeOk.value = false
        return
    }

    const evento = eventoActivo.value
    if (!evento) return

    const payload = {
        player_name: (formulario.value.player_name || '').trim(),
        epic_id: (formulario.value.epic_id || '').trim(),
        whatsapp: (formulario.value.whatsapp || '').trim(),
        discord: (formulario.value.discord || '').trim(),
    }

    const token = document.querySelector('meta[name="csrf-token"]')
    const headers = token ? { 'X-CSRF-TOKEN': token.getAttribute('content') } : {}

    enviando.value = true

    axios
        .post('/semanales/' + evento.id + '/registro', payload, { headers })
        .then((res) => {
            const data = res?.data || {}
            if (data.success === false) {
                mensajeOk.value = false
                mensaje.value = data.message || 'No pudimos completar tu inscripción.'
                return
            }
            mensajeOk.value = true
            mensaje.value = data.message || '¡Listo! Tu inscripción quedó registrada.'
            if (!inscritosLocal.value.includes(evento.id)) inscritosLocal.value.push(evento.id)
            router.reload({ only: ['semanales'] })
            cierreProgramado = setTimeout(() => {
                cierreProgramado = null
                modalAbierto.value = false
                eventoActivo.value = null
                restaurarScroll()
            }, 2200)
        })
        .catch((err) => {
            mensajeOk.value = false
            const estado = err?.response?.status
            // 429 (throttle) y 419 (sesión/CSRF) los responde Laravel, no el controlador:
            // no traen { success, message } en español, así que los traducimos aquí.
            if (estado === 429) {
                mensaje.value = 'Demasiados intentos seguidos. Espera un minuto y vuelve a intentarlo.'
                return
            }
            if (estado === 419) {
                mensaje.value = 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.'
                return
            }
            // 401: la sesión se cayó o nunca hubo cuenta -> al login.
            if (estado === 401 || err?.response?.data?.requiresAuth) {
                mensaje.value =
                    err?.response?.data?.message || 'Necesitas iniciar sesión para inscribirte. Te llevamos al login...'
                setTimeout(() => router.visit('/login'), 1500)
                return
            }
            mensaje.value =
                err?.response?.data?.message ||
                'No pudimos procesar tu inscripción. Inténtalo de nuevo en unos segundos.'
        })
        .finally(() => {
            enviando.value = false
        })
}

/* ------------------------------------------------------------------ */
/* Admin · aviso de que el evento se recorrió                           */
/* ------------------------------------------------------------------ */

const avisoAbierto = ref(false)
const avisoEvento = ref(null)
const avisoEnviando = ref(false)
const avisoMensaje = ref('')
const avisoOk = ref(false)
const avisoConfirmado = ref(false)
const avisoForm = ref({ date_label: '', note: '' })

const avisoDestinatarios = computed(() => Number(avisoEvento.value?.players_count) || 0)

function abrirAviso(ev) {
    if (!ev || !esAdmin.value) return

    avisoEvento.value = ev
    avisoForm.value = { date_label: ev.date_label || '', note: '' }
    avisoMensaje.value = ''
    avisoOk.value = false
    avisoConfirmado.value = false
    avisoAbierto.value = true
    bloquearScroll()
}

function cerrarAviso() {
    if (avisoEnviando.value) return
    avisoAbierto.value = false
    avisoEvento.value = null
    restaurarScroll()
}

function enviarAviso() {
    if (avisoEnviando.value || !avisoEvento.value) return

    // Manda correos a gente real: sin la casilla marcada no sale nada.
    if (!avisoConfirmado.value) {
        avisoOk.value = false
        avisoMensaje.value = 'Marca la casilla de confirmación antes de enviar.'
        return
    }

    const token = document.querySelector('meta[name="csrf-token"]')
    const headers = token ? { 'X-CSRF-TOKEN': token.getAttribute('content') } : {}

    const payload = {
        date_label: (avisoForm.value.date_label || '').trim(),
        note: (avisoForm.value.note || '').trim(),
    }

    avisoEnviando.value = true
    avisoMensaje.value = ''

    axios
        .post('/semanales/' + avisoEvento.value.id + '/aviso-recorrido', payload, { headers })
        .then((res) => {
            const data = res?.data || {}
            avisoOk.value = data.success === true
            avisoMensaje.value = data.message || 'Aviso enviado.'
            if (data.success === true) avisoConfirmado.value = false
        })
        .catch((err) => {
            avisoOk.value = false
            const estado = err?.response?.status
            if (estado === 429) {
                avisoMensaje.value = 'Demasiados avisos seguidos. Espera unos minutos antes de volver a enviar.'
                return
            }
            if (estado === 419) {
                avisoMensaje.value = 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.'
                return
            }
            avisoMensaje.value =
                err?.response?.data?.message || 'No pudimos enviar el aviso. Inténtalo de nuevo en unos segundos.'
        })
        .finally(() => {
            avisoEnviando.value = false
        })
}

/* ------------------------------------------------------------------ */
/* Ciclo de vida                                                        */
/* ------------------------------------------------------------------ */

onMounted(() => {
    // Iconos Phosphor por CDN (mismo patrón que Inicio.vue)
    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script')
        script.src = 'https://unpkg.com/@phosphor-icons/web'
        script.async = true
        document.head.appendChild(script)
    }
    window.addEventListener('keydown', manejarTecla)
})

onUnmounted(() => {
    window.removeEventListener('keydown', manejarTecla)
    if (cierreProgramado) clearTimeout(cierreProgramado)
    restaurarScroll()
})
</script>

<template>
    <Head title="Semanales | Rankit">
        <meta
            name="description"
            content="Semanales de Rankit: torneos promocionales con entrada GRATIS y premios en metálico cada semana. Cupo abierto para todos, inscripción en línea con Epic ID y WhatsApp; los códigos de partida llegan por correo."
        />
        <meta
            name="keywords"
            content="semanales, torneo gratis, premios en metálico, rankit, esports, competencia semanal, inscripción gratuita"
        />
        <meta name="author" content="Rankit Systems" />
        <link
            href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <!-- Capa de fondo fija: garantiza oscuro sin depender del toggle de tema -->
    <div class="fixed inset-0 -z-10 bg-[#030108]"></div>

    <div
        class="relative min-h-screen bg-[#030108] text-gray-300 font-sans overflow-x-hidden selection:bg-[var(--rankit-neon)] selection:text-white"
    >
        <!-- ============================= NAVBAR ============================= -->
        <nav class="sticky top-0 z-40 border-b border-white/10 bg-[#030108]/95 backdrop-blur-xl">
            <div class="flex items-center justify-between h-20 px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
                <a href="/semanales" class="flex items-center gap-3 group shrink-0">
                    <svg
                        class="w-9 h-9 text-white transition-colors group-hover:text-[var(--rankit-neon)]"
                        viewBox="0 0 100 100"
                        fill="none"
                    >
                        <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                        <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                        <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
                    </svg>
                    <span class="text-2xl italic font-bold tracking-tighter text-white uppercase sm:text-3xl font-display">
                        Rankit
                    </span>
                </a>

                <!-- Links escritorio -->
                <div class="items-center hidden gap-7 text-xs font-bold tracking-widest text-gray-400 uppercase lg:flex">
                    <a href="/" class="transition-colors hover:text-white">Inicio</a>
                    <a href="/league" class="transition-colors hover:text-white">League</a>
                    <a href="/mundial" class="transition-colors hover:text-white">Mundial</a>
                    <a href="/tournaments" class="transition-colors hover:text-white">Torneos</a>
                    <a href="/semanales" class="text-fnNeonCyan">Semanales</a>
                </div>

                <div class="flex items-center gap-3">
                    <template v-if="usuario">
                        <Link
                            href="/dashboard"
                            class="hidden px-5 py-2 text-xs font-bold tracking-widest text-white uppercase transition-colors border border-white/20 sm:inline-block hover:border-fnNeonCyan hover:text-fnNeonCyan"
                        >
                            Lobby
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="hidden text-xs font-bold tracking-widest text-gray-300 uppercase transition-colors sm:inline-block hover:text-white"
                        >
                            Ingresar
                        </Link>
                        <!-- El wrapper controla la visibilidad: .btn-skew fuerza display:inline-flex y anularía a "hidden" -->
                        <span class="hidden sm:inline-block">
                            <Link href="/register" class="px-5 py-2 text-xs font-bold tracking-widest uppercase btn-skew">
                                <span class="btn-content">Crear cuenta</span>
                            </Link>
                        </span>
                    </template>

                    <button
                        type="button"
                        class="flex items-center justify-center w-10 h-10 text-white border lg:hidden border-white/15"
                        :aria-expanded="menuAbierto ? 'true' : 'false'"
                        aria-label="Abrir menú"
                        @click="menuAbierto = !menuAbierto"
                    >
                        <i class="text-xl ph" :class="menuAbierto ? 'ph-x' : 'ph-list'"></i>
                    </button>
                </div>
            </div>

            <!-- Menú móvil -->
            <div v-show="menuAbierto" class="border-t lg:hidden border-white/10 bg-[#05020c]">
                <div class="flex flex-col px-4 py-4 mx-auto text-sm font-bold tracking-widest uppercase max-w-7xl sm:px-6">
                    <a href="/" class="py-3 text-gray-300 border-b border-white/5 hover:text-white">Inicio</a>
                    <a href="/league" class="py-3 text-gray-300 border-b border-white/5 hover:text-white">League</a>
                    <a href="/mundial" class="py-3 text-gray-300 border-b border-white/5 hover:text-white">Mundial</a>
                    <a href="/tournaments" class="py-3 text-gray-300 border-b border-white/5 hover:text-white">Torneos</a>
                    <a href="/semanales" class="py-3 border-b text-fnNeonCyan border-white/5">Semanales</a>
                    <Link v-if="usuario" href="/dashboard" class="py-3 text-white">Lobby</Link>
                    <template v-else>
                        <Link href="/login" class="py-3 text-gray-300 border-b border-white/5">Ingresar</Link>
                        <Link href="/register" class="py-3 text-fnMagenta">Crear cuenta</Link>
                    </template>
                </div>
            </div>
        </nav>

        <!-- ============================== HERO ============================== -->
        <header class="relative overflow-hidden border-b border-white/5">
            <div class="absolute inset-0 bg-tech-grid-dark bg-[length:44px_44px] opacity-70"></div>
            <div class="absolute -top-24 -left-24 w-[26rem] h-[26rem] rounded-full bg-[var(--rankit-neon)]/25 blur-[120px]"></div>
            <div class="absolute -bottom-32 right-0 w-[24rem] h-[24rem] rounded-full bg-fnNeonCyan/15 blur-[130px]"></div>
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-[#030108]/40 to-[#030108]"></div>

            <div class="relative z-10 px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="grid items-center grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-10">
                    <!-- Columna texto -->
                    <div class="lg:col-span-7">
                        <div
                            class="inline-flex items-center max-w-full gap-2 px-3 py-1.5 mb-6 text-[10px] sm:text-xs font-bold tracking-[0.18em] uppercase border rounded-none border-fnNeonCyan/60 text-fnNeonCyan bg-fnNeonCyan/5"
                        >
                            <span class="w-2 h-2 rounded-full shrink-0 bg-fnNeonCyan animate-pulse"></span>
                            <span class="break-words">Evento promocional · Independiente</span>
                        </div>

                        <h1
                            class="font-display font-black italic uppercase leading-[0.85] tracking-tight text-white text-[3.25rem] sm:text-7xl lg:text-[7.5rem] mb-6 break-words"
                        >
                            <span class="block sem-titulo">Semanales</span>
                        </h1>

                        <p class="max-w-2xl pl-5 mb-8 text-lg font-light leading-relaxed text-gray-300 border-l-4 sm:text-xl border-fnNeonCyan">
                            Cada semana abrimos un evento con
                            <strong class="font-semibold text-white">premios en metálico</strong>, entrada
                            <strong class="font-semibold text-fnNeonCyan">totalmente gratis</strong> y cupo abierto para
                            todos. Te inscribes, te sumamos a los canales oficiales y compites por la bolsa semanal.
                        </p>

                        <!-- Chips -->
                        <div class="flex flex-wrap gap-2.5 mb-9">
                            <span class="sem-chip border-fnEmerald/50 text-fnEmerald bg-fnEmerald/10">
                                <i class="ph-fill ph-currency-circle-dollar"></i> Gratis
                            </span>
                            <span class="sem-chip border-white/20 text-gray-200 bg-white/5">
                                <i class="ph-fill ph-globe-hemisphere-west"></i> Público
                            </span>
                            <span class="sem-chip border-fnBrightPurple/50 text-fnBrightPurple bg-fnBrightPurple/10">
                                <i class="ph-fill ph-game-controller"></i> Epic ID + WhatsApp
                            </span>
                            <span class="sem-chip border-fnGold/50 text-fnGold bg-fnGold/10">
                                <i class="ph-fill ph-calendar-blank"></i> Fechas: Próximamente
                            </span>
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <button type="button" class="sem-btn sem-btn-primary" @click="irA('eventos')">
                                <span class="relative z-10 flex items-center justify-center gap-2">
                                    Ver los semanales
                                    <i class="ph-bold ph-arrow-down"></i>
                                </span>
                            </button>
                            <button type="button" class="sem-btn sem-btn-ghost" @click="irA('como-funciona')">
                                <span class="flex items-center justify-center gap-2">
                                    Cómo funciona
                                    <i class="ph-bold ph-arrow-right"></i>
                                </span>
                            </button>
                        </div>

                        <p v-if="!canRegister && hayEventos" class="mt-5 text-xs font-bold tracking-widest uppercase text-fnGold">
                            <i class="ph-fill ph-warning-circle"></i>
                            Las inscripciones no están abiertas en este momento.
                        </p>
                    </div>

                    <!-- Columna panel -->
                    <div class="lg:col-span-5">
                        <div class="relative p-6 sm:p-8 sem-panel">
                            <div class="absolute top-0 right-0 w-32 h-32 bg-fnNeonCyan/15 blur-[60px] pointer-events-none"></div>

                            <h2 class="pb-4 mb-6 text-sm font-bold tracking-[0.2em] text-white uppercase border-b font-display border-white/10">
                                Resumen del circuito
                            </h2>

                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="p-4 border border-white/10 bg-white/5">
                                    <div class="text-3xl font-black text-white font-display">{{ listaSemanales.length }}</div>
                                    <div class="mt-1 text-[10px] font-bold tracking-widest text-gray-400 uppercase">Eventos</div>
                                </div>
                                <div class="p-4 border border-white/10 bg-white/5">
                                    <div class="text-3xl font-black text-fnNeonCyan font-display">{{ totalInscritos }}</div>
                                    <div class="mt-1 text-[10px] font-bold tracking-widest text-gray-400 uppercase">Inscritos</div>
                                </div>
                                <div class="p-4 border border-white/10 bg-white/5">
                                    <div class="text-3xl font-black text-fnEmerald font-display">$0</div>
                                    <div class="mt-1 text-[10px] font-bold tracking-widest text-gray-400 uppercase">Entrada</div>
                                </div>
                                <div class="p-4 border border-white/10 bg-white/5">
                                    <div class="text-base font-black leading-tight text-fnGold font-display">POR ANUNCIAR</div>
                                    <div class="mt-1 text-[10px] font-bold tracking-widest text-gray-400 uppercase">Bolsa semanal</div>
                                </div>
                            </div>

                            <ul class="space-y-3 text-sm text-gray-300">
                                <li class="flex items-start gap-3">
                                    <i class="mt-0.5 text-lg ph-fill ph-check-circle text-fnEmerald shrink-0"></i>
                                    <span>Inscripción en línea, sin pagos ni suscripción.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="mt-0.5 text-lg ph-fill ph-check-circle text-fnEmerald shrink-0"></i>
                                    <span>Premios en metálico, anunciados por evento.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="mt-0.5 text-lg ph-fill ph-check-circle text-fnEmerald shrink-0"></i>
                                    <span>Aviso por mensaje y códigos de partida por correo.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- ======================== AVISO INDEPENDENCIA ===================== -->
        <div class="border-b border-fnGold/25 bg-fnGold/[0.06]">
            <div class="flex items-start gap-3 px-4 py-4 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:items-center">
                <i class="text-xl ph-fill ph-warning-circle text-fnGold shrink-0"></i>
                <p class="text-xs leading-relaxed text-gray-200 sm:text-sm">
                    <strong class="font-bold tracking-wide text-fnGold uppercase">Aviso:</strong>
                    Los Semanales son eventos promocionales de Rankit.
                    <strong class="font-semibold text-white">No forman parte de Rankit League ni otorgan puntos de la liga.</strong>
                </p>
            </div>
        </div>

        <!-- ============================= EVENTOS ============================ -->
        <section id="eventos" class="relative px-4 py-20 mx-auto scroll-mt-24 max-w-7xl sm:px-6 lg:px-8 sm:py-24">
            <div class="mb-12">
                <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnNeonCyan">Calendario</span>
                <h2 class="mb-4 text-4xl font-black italic tracking-tight text-white uppercase font-display sm:text-6xl">
                    Los <span class="text-transparent bg-clip-text bg-gradient-to-r from-fnNeonCyan to-fnBrightPurple">Semanales</span>
                </h2>
                <p class="max-w-2xl text-base font-light text-gray-400">
                    Dos eventos, misma promesa: entrada gratis y premios en metálico. Reserva tu lugar ahora y te
                    avisamos en cuanto se confirme la fecha.
                </p>
            </div>

            <!-- Estado vacío -->
            <div v-if="!hayEventos" class="p-8 text-center sem-panel sm:p-14">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-6 border rounded-full border-white/15 bg-white/5">
                    <i class="text-3xl ph ph-calendar-x text-fnMagenta"></i>
                </div>
                <h3 class="mb-3 text-2xl font-black text-white uppercase font-display">Todavía no hay semanales publicados</h3>
                <p class="max-w-xl mx-auto mb-6 text-sm leading-relaxed text-gray-400">
                    {{ errorMessage || 'Estamos preparando los próximos eventos. Vuelve en unos días o síguenos para enterarte en cuanto se publiquen.' }}
                </p>
                <div class="flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="/tournaments" class="sem-btn sem-btn-ghost">
                        <span class="flex items-center justify-center gap-2">Ver otros torneos <i class="ph-bold ph-arrow-right"></i></span>
                    </a>
                    <a v-if="ownerEmail" :href="'mailto:' + ownerEmail" class="text-xs font-bold tracking-widest uppercase text-fnNeonCyan hover:text-white">
                        Escribir a {{ ownerEmail }}
                    </a>
                </div>
            </div>

            <!-- Mensaje de error con eventos presentes -->
            <div
                v-else-if="errorMessage"
                class="flex items-start gap-3 p-4 mb-8 border border-fnCrimson/40 bg-fnCrimson/10"
            >
                <i class="text-lg ph-fill ph-warning-octagon text-fnCrimson shrink-0"></i>
                <p class="text-sm text-gray-200">{{ errorMessage }}</p>
            </div>

            <!-- Grid de eventos -->
            <div v-if="hayEventos" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <article
                    v-for="ev in listaSemanales"
                    :key="ev.id"
                    class="flex flex-col p-6 sem-card sm:p-8"
                >
                    <!-- Cabecera -->
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                        <span class="px-3 py-1 text-[10px] font-bold tracking-[0.22em] uppercase border border-white/15 text-gray-300 bg-white/5">
                            Semana {{ dosDigitos(ev.week_number) }}
                        </span>
                        <span class="px-3 py-1 text-[10px] font-bold tracking-[0.18em] uppercase border" :class="claseEstado(ev)">
                            {{ textoEstado(ev) }}
                        </span>
                    </div>

                    <h3 class="mb-1 text-3xl font-black italic text-white uppercase break-words font-display sm:text-4xl">
                        {{ ev.name || 'Semanal' }}
                    </h3>
                    <p class="mb-6 font-mono text-[11px] tracking-widest text-gray-500 uppercase break-all">
                        /{{ ev.slug || 'semanal' }}
                    </p>

                    <!-- Chip grande de fecha -->
                    <div class="flex items-center gap-4 p-4 mb-6 border border-fnGold/30 bg-fnGold/[0.07]">
                        <i class="text-2xl ph-fill ph-calendar-blank text-fnGold shrink-0"></i>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold tracking-[0.2em] text-gray-400 uppercase">Fecha del evento</div>
                            <div class="text-xl font-black tracking-tight break-words text-fnGold font-display sm:text-2xl">
                                {{ etiquetaFecha(ev) }}
                            </div>
                        </div>
                    </div>

                    <!-- Datos -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="p-4 border border-white/10 bg-white/5">
                            <div class="text-[10px] font-bold tracking-[0.2em] text-gray-400 uppercase mb-1">Entrada</div>
                            <div class="text-lg font-black uppercase text-fnEmerald font-display">{{ etiquetaEntrada(ev) }}</div>
                        </div>
                        <div class="p-4 border border-white/10 bg-white/5">
                            <div class="text-[10px] font-bold tracking-[0.2em] text-gray-400 uppercase mb-1">Inscritos</div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-lg font-black text-white font-display">{{ ev.players_count || 0 }}</span>
                                <span class="text-[10px] text-gray-500 uppercase tracking-widest">jugadores</span>
                            </div>
                        </div>
                    </div>

                    <!-- Requisitos -->
                    <div class="mb-6">
                        <div class="text-[10px] font-bold tracking-[0.2em] text-gray-400 uppercase mb-3">Requisitos</div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-if="ev.requires_epic !== false"
                                class="sem-chip border-fnNeonCyan/40 text-fnNeonCyan bg-fnNeonCyan/10"
                            >
                                <i class="ph-fill ph-game-controller"></i> Epic ID obligatorio
                            </span>
                            <span
                                v-if="ev.requires_whatsapp !== false"
                                class="sem-chip border-fnEmerald/40 text-fnEmerald bg-fnEmerald/10"
                            >
                                <i class="ph-fill ph-whatsapp-logo"></i> WhatsApp obligatorio
                            </span>
                            <span class="sem-chip border-fnBrightPurple/40 text-fnBrightPurple bg-fnBrightPurple/10">
                                <i class="ph-fill ph-discord-logo"></i> Discord opcional
                            </span>
                            <span v-if="ev.is_public" class="sem-chip border-white/20 text-gray-200 bg-white/5">
                                <i class="ph-fill ph-globe-hemisphere-west"></i> Evento público
                            </span>
                        </div>

                        <!-- Qué pasa después de inscribirte -->
                        <ul class="mt-4 space-y-1.5 text-xs text-gray-400">
                            <li class="flex items-start gap-2">
                                <i class="ph-fill ph-chat-circle-text text-fnEmerald mt-0.5"></i>
                                <span>Te notificamos por mensaje cuando se confirme la hora del lobby.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="ph-fill ph-envelope-simple text-fnNeonCyan mt-0.5"></i>
                                <span>Ya inscrito, los códigos de partida te llegan por correo.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="ph-fill ph-chart-line-up text-fnGold mt-0.5"></i>
                                <span>Entra al evento desde la app para ver las tablas y cómo vas.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Premios y reglas -->
                    <div class="mb-6 space-y-4">
                        <div>
                            <div class="text-[10px] font-bold tracking-[0.2em] text-gray-400 uppercase mb-2">
                                <i class="ph-fill ph-trophy text-fnGold"></i> Premios
                            </div>
                            <p class="text-sm leading-relaxed text-gray-300 whitespace-pre-line">
                                {{ ev.prizes || 'Bolsa en metálico. Monto POR ANUNCIAR.' }}
                            </p>
                        </div>
                        <!-- Las reglas van plegadas: si no, empujan el botón de inscripción fuera de pantalla. -->
                        <details v-if="ev.rules" class="border sem-reglas border-white/10 bg-white/[0.02]">
                            <summary
                                class="flex items-center justify-between gap-2 px-4 py-3 text-[10px] font-bold tracking-[0.2em] text-gray-300 uppercase cursor-pointer select-none hover:text-fnNeonCyan"
                            >
                                <span><i class="ph-fill ph-list-checks text-fnNeonCyan"></i> Reglas del evento</span>
                                <i class="transition-transform ph-bold ph-caret-down sem-reglas-caret"></i>
                            </summary>
                            <p class="px-4 pb-4 text-sm leading-relaxed text-gray-400 whitespace-pre-line">{{ ev.rules }}</p>
                        </details>
                    </div>

                    <!-- Acciones -->
                    <div class="flex flex-col gap-3 pt-6 mt-auto border-t border-white/10 sm:flex-row sm:items-center">
                        <div
                            v-if="estaInscrito(ev)"
                            class="flex items-center justify-center flex-1 gap-2 px-6 py-4 text-sm font-bold tracking-widest uppercase border border-fnEmerald/60 text-fnEmerald bg-fnEmerald/10"
                        >
                            <i class="text-lg ph-fill ph-check-circle"></i>
                            Ya estás inscrito
                        </div>
                        <button
                            v-else
                            type="button"
                            class="flex-1 sem-btn"
                            :class="registroAbierto(ev) ? 'sem-btn-primary' : 'sem-btn-disabled'"
                            :disabled="botonDeshabilitado(ev)"
                            @click="abrirModal(ev)"
                        >
                            <span class="relative z-10 flex items-center justify-center gap-2">
                                <i class="ph-bold" :class="registroAbierto(ev) ? iconoBoton(ev) : 'ph-lock-simple'"></i>
                                {{ textoBoton(ev) }}
                            </span>
                        </button>

                        <a
                            :href="ev.public_url || '/t/' + (ev.slug || '')"
                            class="inline-flex items-center justify-center gap-2 px-5 py-4 text-xs font-bold tracking-widest text-gray-300 uppercase transition-colors border border-white/15 hover:border-fnNeonCyan hover:text-fnNeonCyan"
                        >
                            Ver evento <i class="ph-bold ph-arrow-up-right"></i>
                        </a>
                    </div>

                    <!-- Acciones de administración (sólo admin) -->
                    <div v-if="esAdmin" class="pt-4 mt-4 border-t border-dashed border-fnGold/25">
                        <div class="flex items-center gap-2 mb-2 text-[10px] font-bold tracking-[0.2em] text-fnGold uppercase">
                            <i class="ph-fill ph-shield-star"></i> Administración
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center w-full gap-2 px-5 py-3 text-xs font-bold tracking-widest uppercase transition-colors border text-fnGold border-fnGold/40 bg-fnGold/5 hover:bg-fnGold/15 hover:border-fnGold disabled:opacity-40"
                            :disabled="(Number(ev.players_count) || 0) === 0"
                            @click="abrirAviso(ev)"
                        >
                            <i class="ph-bold ph-calendar-x"></i>
                            Avisar que se recorrió
                        </button>
                        <p class="mt-2 text-[11px] text-gray-500">
                            <template v-if="(Number(ev.players_count) || 0) === 0">
                                Todavía no hay inscritos a quienes avisar.
                            </template>
                            <template v-else>
                                Manda un correo a los {{ ev.players_count }} inscrito(s) de este Semanal con la nueva
                                fecha.
                            </template>
                        </p>
                    </div>

                    <!-- Sin sesión: la inscripción exige cuenta (el correo se toma de ahí) -->
                    <p
                        v-if="!usuario && registroAbierto(ev) && !estaInscrito(ev)"
                        class="mt-3 text-xs text-gray-500"
                    >
                        <i class="ph-fill ph-info text-fnNeonCyan"></i>
                        Necesitas una cuenta de Rankit para inscribirte: así usamos el correo de tu cuenta y te
                        confirmamos al instante.
                        <Link href="/register" class="font-bold underline text-fnNeonCyan hover:text-white">Crear cuenta gratis</Link>
                    </p>
                </article>
            </div>
        </section>

        <!-- ========================== CÓMO FUNCIONA ========================= -->
        <section id="como-funciona" class="relative border-y border-white/5 bg-[#05020c] scroll-mt-24">
            <div class="px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="max-w-2xl mb-14">
                    <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnMagenta">Paso a paso</span>
                    <h2 class="mb-4 text-4xl font-black italic tracking-tight text-white uppercase font-display sm:text-5xl">
                        Cómo funciona
                    </h2>
                    <p class="text-base font-light text-gray-400">
                        Del registro al lobby en cuatro pasos. Sin trámites raros y sin pagar nada.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(paso, i) in pasos" :key="i" class="relative p-6 sem-card">
                        <div class="absolute text-5xl italic font-black top-4 right-5 font-display text-white/5">
                            {{ dosDigitos(i + 1) }}
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 mb-5 border border-fnNeonCyan/30 bg-fnNeonCyan/10 text-fnNeonCyan">
                            <i class="text-2xl ph-fill" :class="paso.icono"></i>
                        </div>
                        <h3 class="mb-2 text-lg font-bold text-white uppercase font-display">{{ paso.titulo }}</h3>
                        <p class="text-sm leading-relaxed text-gray-400">{{ paso.texto }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================= PREMIOS ============================ -->
        <section id="premios" class="relative overflow-hidden scroll-mt-24">
            <div class="absolute inset-0 bg-tech-grid-dark bg-[length:36px_36px] opacity-40"></div>
            <div class="absolute w-full h-64 -translate-x-1/2 rounded-full left-1/2 top-10 bg-fnGold/10 blur-[120px] pointer-events-none"></div>

            <div class="relative z-10 px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="max-w-3xl mb-14">
                    <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnGold">Bolsa semanal</span>
                    <h2 class="mb-4 text-4xl font-black italic tracking-tight text-white uppercase font-display sm:text-5xl">
                        Premios en <span class="text-fnGold">metálico</span>
                    </h2>
                    <p class="text-base font-light leading-relaxed text-gray-400">
                        Cada Semanal reparte dinero real entre los mejores del lobby. No son skins, ni cupones, ni
                        promesas: es bolsa en metálico. El monto exacto y el reparto se anuncian junto con la fecha de
                        cada evento.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-6 mb-10 sm:grid-cols-3">
                    <div class="p-8 text-center sem-card border-fnGold/30">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-5 border rounded-full border-fnGold/40 bg-fnGold/10">
                            <i class="text-2xl ph-fill ph-crown-simple text-fnGold"></i>
                        </div>
                        <div class="text-[10px] font-bold tracking-[0.25em] text-gray-400 uppercase mb-2">1er lugar</div>
                        <div class="text-2xl font-black tracking-tight text-fnGold font-display">POR ANUNCIAR</div>
                    </div>
                    <div class="p-8 text-center sem-card">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-5 border rounded-full border-white/20 bg-white/5">
                            <i class="text-2xl ph-fill ph-medal text-gray-300"></i>
                        </div>
                        <div class="text-[10px] font-bold tracking-[0.25em] text-gray-400 uppercase mb-2">2do lugar</div>
                        <div class="text-2xl font-black tracking-tight text-white font-display">POR ANUNCIAR</div>
                    </div>
                    <div class="p-8 text-center sem-card">
                        <div class="flex items-center justify-center w-14 h-14 mx-auto mb-5 border rounded-full border-fnBrightPurple/30 bg-fnBrightPurple/10">
                            <i class="text-2xl ph-fill ph-medal text-fnBrightPurple"></i>
                        </div>
                        <div class="text-[10px] font-bold tracking-[0.25em] text-gray-400 uppercase mb-2">3er lugar</div>
                        <div class="text-2xl font-black tracking-tight text-fnBrightPurple font-display">POR ANUNCIAR</div>
                    </div>
                </div>

                <div class="flex flex-col items-start gap-4 p-6 sem-panel sm:flex-row sm:items-center sm:p-8">
                    <i class="text-3xl ph-fill ph-info text-fnNeonCyan shrink-0"></i>
                    <p class="text-sm leading-relaxed text-gray-300">
                        <strong class="font-bold text-white">Transparencia primero:</strong>
                        no publicamos cantidades que todavía no están confirmadas. En cuanto la bolsa y las fechas queden
                        cerradas, las anunciamos aquí y en los canales oficiales del evento. Si ya estás inscrito, te
                        llega el aviso por mensaje.
                    </p>
                </div>
            </div>
        </section>

        <!-- ====================== REQUISITOS Y REGLAS ======================= -->
        <section id="reglas" class="border-y border-white/5 bg-[#05020c] scroll-mt-24">
            <div class="px-4 py-20 mx-auto max-w-7xl sm:px-6 lg:px-8 sm:py-24">
                <div class="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                    <!-- Requisitos -->
                    <div>
                        <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnEmerald">Antes de inscribirte</span>
                        <h2 class="mb-6 text-3xl font-black italic tracking-tight text-white uppercase font-display sm:text-4xl">
                            Requisitos y reglas
                        </h2>
                        <ul class="space-y-4">
                            <li v-for="(req, i) in requisitos" :key="i" class="flex items-start gap-4 p-4 border border-white/10 bg-white/[0.03]">
                                <i class="text-xl ph-fill text-fnNeonCyan shrink-0 mt-0.5" :class="req.icono"></i>
                                <span class="text-sm leading-relaxed text-gray-300">{{ req.texto }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- FAQ -->
                    <div>
                        <span class="block mb-3 text-[11px] font-bold tracking-[0.3em] uppercase text-fnMagenta">Dudas frecuentes</span>
                        <h2 class="mb-6 text-3xl font-black italic tracking-tight text-white uppercase font-display sm:text-4xl">
                            Preguntas rápidas
                        </h2>

                        <div class="border divide-y border-white/10 divide-white/10">
                            <div v-for="(faq, i) in faqs" :key="i" class="bg-white/[0.02]">
                                <button
                                    type="button"
                                    class="flex items-center justify-between w-full gap-4 px-5 py-4 text-left transition-colors hover:bg-white/5"
                                    :aria-expanded="faqAbierta === i ? 'true' : 'false'"
                                    @click="alternarFaq(i)"
                                >
                                    <span class="text-sm font-bold text-white">{{ faq.p }}</span>
                                    <i
                                        class="text-lg transition-transform ph-bold ph-caret-down shrink-0 text-fnNeonCyan"
                                        :class="faqAbierta === i ? 'rotate-180' : ''"
                                    ></i>
                                </button>
                                <div v-show="faqAbierta === i" class="px-5 pb-5 -mt-1">
                                    <p class="text-sm leading-relaxed text-gray-400">{{ faq.r }}</p>
                                </div>
                            </div>
                        </div>

                        <p v-if="ownerEmail" class="mt-6 text-sm text-gray-400">
                            ¿Otra duda? Escríbenos a
                            <a :href="'mailto:' + ownerEmail" class="font-bold break-all text-fnNeonCyan hover:text-white">{{ ownerEmail }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================= AVISO DESTACADO ======================== -->
        <section class="px-4 py-16 mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="relative p-6 overflow-hidden border-2 border-fnGold/40 bg-fnGold/[0.06] sm:p-10">
                <div class="absolute top-0 right-0 w-40 h-40 bg-fnGold/10 blur-[70px] pointer-events-none"></div>
                <div class="relative z-10 flex flex-col items-start gap-5 sm:flex-row">
                    <div class="flex items-center justify-center border w-14 h-14 shrink-0 border-fnGold/40 bg-fnGold/10">
                        <i class="text-3xl ph-fill ph-seal-warning text-fnGold"></i>
                    </div>
                    <div>
                        <h2 class="mb-3 text-2xl font-black italic text-white uppercase font-display sm:text-3xl">
                            Aviso importante
                        </h2>
                        <p class="max-w-3xl text-base leading-relaxed text-gray-200">
                            Los Semanales son
                            <strong class="font-bold text-fnGold">eventos promocionales de Rankit</strong>.
                            <strong class="font-bold text-white">
                                No forman parte de Rankit League ni otorgan puntos de la liga.
                            </strong>
                            Participar (o no participar) en un Semanal no afecta en nada tu posición, tus puntos ni tu
                            clasificación dentro de Rankit League.
                        </p>
                        <div class="flex flex-col gap-3 mt-6 sm:flex-row">
                            <button type="button" class="sem-btn sem-btn-primary" @click="irA('eventos')">
                                <span class="relative z-10 flex items-center justify-center gap-2">
                                    Inscribirme gratis <i class="ph-bold ph-arrow-up"></i>
                                </span>
                            </button>
                            <a href="/league" class="sem-btn sem-btn-ghost">
                                <span class="flex items-center justify-center gap-2">
                                    Conocer Rankit League <i class="ph-bold ph-arrow-right"></i>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================== FOOTER =========================== -->
        <footer class="border-t border-white/10 bg-[#05020c]">
            <div class="flex flex-col items-center justify-between gap-6 px-4 py-10 mx-auto max-w-7xl sm:px-6 lg:px-8 md:flex-row">
                <div class="flex items-center gap-3">
                    <svg class="w-8 h-8 text-gray-500" viewBox="0 0 100 100" fill="none">
                        <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
                        <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
                        <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="currentColor" />
                    </svg>
                    <div class="flex flex-col">
                        <span class="text-lg font-bold text-white uppercase font-display">Rankit</span>
                        <span class="text-[10px] font-mono uppercase tracking-widest text-gray-500">Semanales</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-5 text-[11px] font-bold tracking-widest uppercase text-gray-500">
                    <a href="/" class="transition-colors hover:text-white">Inicio</a>
                    <a href="/league" class="transition-colors hover:text-white">League</a>
                    <a href="/tournaments" class="transition-colors hover:text-white">Torneos</a>
                    <a href="https://rankit.pro" target="_blank" rel="noopener" class="transition-colors text-fnNeonCyan hover:text-white">
                        Rankit.pro
                    </a>
                </div>

                <p class="text-[11px] text-center text-gray-600 md:text-right">
                    Evento promocional independiente de Rankit League.
                </p>
            </div>
        </footer>

        <!-- ====================== MODAL DE INSCRIPCIÓN ====================== -->
        <Teleport to="body">
            <div v-if="modalAbierto" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-6">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="cerrarModal"></div>

                <!-- Contenido -->
                <div
                    class="relative z-10 w-full sm:max-w-lg max-h-[92vh] overflow-y-auto sem-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="titulo-modal-semanal"
                >
                    <div class="sticky top-0 z-10 flex items-start justify-between gap-4 px-6 py-5 border-b border-white/10 bg-[#0f081c]">
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold tracking-[0.25em] text-fnNeonCyan uppercase mb-1">
                                Inscripción gratuita
                            </div>
                            <h3 id="titulo-modal-semanal" class="text-xl font-black italic text-white uppercase break-words font-display">
                                {{ eventoActivo?.name || 'Semanal' }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            class="flex items-center justify-center w-9 h-9 text-gray-400 transition-colors border shrink-0 border-white/15 hover:text-white hover:border-white/40"
                            aria-label="Cerrar"
                            @click="cerrarModal"
                        >
                            <i class="text-lg ph-bold ph-x"></i>
                        </button>
                    </div>

                    <form class="px-6 py-6 space-y-5" @submit.prevent="enviarInscripcion">
                        <div class="flex items-center gap-3 p-3 border border-fnGold/30 bg-fnGold/[0.07]">
                            <i class="text-lg ph-fill ph-calendar-blank text-fnGold shrink-0"></i>
                            <span class="text-xs font-bold tracking-widest uppercase text-fnGold">
                                Fecha: {{ etiquetaFecha(eventoActivo) }}
                            </span>
                        </div>

                        <!-- Nombre / Gamertag -->
                        <div>
                            <label for="sem-nombre" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                Nombre o gamertag <span class="text-fnCrimson">*</span>
                            </label>
                            <input
                                id="sem-nombre"
                                ref="inputNombre"
                                v-model="formulario.player_name"
                                type="text"
                                maxlength="60"
                                autocomplete="nickname"
                                placeholder="Ej. ShadowMX"
                                class="sem-input"
                                :class="errores.player_name ? 'sem-input-error' : ''"
                            />
                            <p v-if="errores.player_name" class="mt-1.5 text-xs text-fnCrimson">{{ errores.player_name }}</p>
                        </div>

                        <!-- Epic ID (obligatorio) -->
                        <div>
                            <label for="sem-epic" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                Epic ID <span class="text-fnCrimson">*</span>
                            </label>
                            <input
                                id="sem-epic"
                                v-model="formulario.epic_id"
                                type="text"
                                maxlength="60"
                                placeholder="Tu nombre de usuario de Epic Games"
                                class="sem-input"
                                :class="errores.epic_id ? 'sem-input-error' : ''"
                            />
                            <p v-if="errores.epic_id" class="mt-1.5 text-xs text-fnCrimson">{{ errores.epic_id }}</p>
                            <p v-else class="mt-1.5 text-xs text-gray-500">Con este ID te identificamos dentro de la partida y en las tablas.</p>
                        </div>

                        <!-- WhatsApp -->
                        <div>
                            <label for="sem-whatsapp" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                WhatsApp <span class="text-fnCrimson">*</span>
                            </label>
                            <input
                                id="sem-whatsapp"
                                v-model="formulario.whatsapp"
                                type="tel"
                                maxlength="25"
                                autocomplete="tel"
                                placeholder="+52 55 1234 5678"
                                class="sem-input"
                                :class="errores.whatsapp ? 'sem-input-error' : ''"
                            />
                            <p v-if="errores.whatsapp" class="mt-1.5 text-xs text-fnCrimson">{{ errores.whatsapp }}</p>
                            <p v-else class="mt-1.5 text-xs text-gray-500">Incluye la lada del país. Ahí te llega el mensaje con la fecha.</p>
                        </div>

                        <!-- Discord (opcional) -->
                        <div>
                            <label for="sem-discord" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                Discord <span class="font-normal normal-case tracking-normal text-gray-500">(opcional)</span>
                            </label>
                            <input
                                id="sem-discord"
                                v-model="formulario.discord"
                                type="text"
                                maxlength="60"
                                placeholder="@usuario o usuario#0000"
                                class="sem-input"
                                :class="errores.discord ? 'sem-input-error' : ''"
                            />
                            <p v-if="errores.discord" class="mt-1.5 text-xs text-fnCrimson">{{ errores.discord }}</p>
                            <p v-else class="mt-1.5 text-xs text-gray-500">Si lo dejas, te sumamos a la comunidad. Puedes omitirlo.</p>
                        </div>

                        <!-- Correo: se autocompleta con el de la cuenta y no se puede editar -->
                        <div v-if="usuario">
                            <label for="sem-email" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                Correo de tu cuenta
                            </label>
                            <div class="relative">
                                <i class="absolute -translate-y-1/2 ph-fill ph-envelope-simple text-fnNeonCyan left-3 top-1/2"></i>
                                <input
                                    id="sem-email"
                                    :value="usuario.email"
                                    type="email"
                                    readonly
                                    tabindex="-1"
                                    autocomplete="email"
                                    class="pl-9 sem-input opacity-80 cursor-not-allowed !text-gray-300"
                                />
                                <i class="absolute -translate-y-1/2 ph-fill ph-lock-simple right-3 top-1/2 text-gray-500 text-sm"></i>
                            </div>
                            <p class="mt-1.5 text-xs text-gray-500">Se toma de tu sesión de Rankit. Ahí te llega la confirmación.</p>
                        </div>

                        <!-- Mensaje -->
                        <div
                            v-if="mensaje"
                            class="flex items-start gap-3 p-3 text-sm"
                            :class="mensajeOk ? 'border border-fnEmerald/50 bg-fnEmerald/10 text-fnEmerald' : 'border border-fnCrimson/50 bg-fnCrimson/10 text-fnCrimson'"
                        >
                            <i class="text-lg ph-fill shrink-0" :class="mensajeOk ? 'ph-check-circle' : 'ph-warning-circle'"></i>
                            <span class="break-words">{{ mensaje }}</span>
                        </div>

                        <!-- Acciones -->
                        <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                            <button
                                type="submit"
                                class="flex-1 sem-btn"
                                :class="enviando ? 'sem-btn-disabled' : 'sem-btn-primary'"
                                :disabled="enviando"
                            >
                                <span class="relative z-10 flex items-center justify-center gap-2">
                                    <span v-if="enviando" class="sem-spinner"></span>
                                    <i v-else class="ph-bold ph-paper-plane-tilt"></i>
                                    {{ enviando ? 'Enviando…' : 'Confirmar inscripción' }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="px-6 py-4 text-xs font-bold tracking-widest text-gray-400 uppercase transition-colors border border-white/15 hover:text-white hover:border-white/40 disabled:opacity-40"
                                :disabled="enviando"
                                @click="cerrarModal"
                            >
                                Cancelar
                            </button>
                        </div>

                        <p class="text-[11px] leading-relaxed text-gray-500">
                            Al inscribirte aceptas que te contactemos por WhatsApp y por correo con la información del
                            evento y los códigos de partida. Los Semanales son eventos promocionales y no otorgan puntos
                            de Rankit League.
                        </p>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ============ MODAL ADMIN · AVISO DE CAMBIO DE FECHA ============= -->
        <Teleport to="body">
            <div v-if="avisoAbierto" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-0 sm:p-6">
                <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="cerrarAviso"></div>

                <div
                    class="relative z-10 w-full sm:max-w-lg max-h-[92vh] overflow-y-auto sem-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="titulo-modal-aviso"
                >
                    <div class="sticky top-0 z-10 flex items-start justify-between gap-4 px-6 py-5 border-b border-white/10 bg-[#0f081c]">
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold tracking-[0.25em] text-fnGold uppercase mb-1">
                                Aviso a los inscritos
                            </div>
                            <h3 id="titulo-modal-aviso" class="text-xl font-black italic text-white uppercase break-words font-display">
                                {{ avisoEvento?.name || 'Semanal' }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            class="flex items-center justify-center w-9 h-9 text-gray-400 transition-colors border shrink-0 border-white/15 hover:text-white hover:border-white/40"
                            aria-label="Cerrar"
                            @click="cerrarAviso"
                        >
                            <i class="text-lg ph-bold ph-x"></i>
                        </button>
                    </div>

                    <form class="px-6 py-6 space-y-5" @submit.prevent="enviarAviso">
                        <div class="flex items-start gap-3 p-3 border border-fnGold/30 bg-fnGold/[0.07]">
                            <i class="text-lg ph-fill ph-envelope-simple text-fnGold shrink-0"></i>
                            <span class="text-xs leading-relaxed text-gray-200">
                                Se enviará un correo a
                                <strong class="text-fnGold">{{ avisoDestinatarios }} inscrito(s)</strong>
                                avisando que el evento se recorrió. Esta acción no se puede deshacer.
                            </span>
                        </div>

                        <div>
                            <label for="aviso-fecha" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                Nueva fecha <span class="text-fnCrimson">*</span>
                            </label>
                            <input
                                id="aviso-fecha"
                                v-model="avisoForm.date_label"
                                type="text"
                                maxlength="120"
                                placeholder="Ej. SÁBADO 15 DE AGOSTO"
                                class="sem-input"
                            />
                            <p class="mt-1.5 text-xs text-gray-500">
                                Es el texto que verán en el correo. Si lo dejas vacío se manda la fecha que ya tiene la
                                tarjeta. Ojo: esto <strong>no cambia</strong> la fecha del evento, sólo avisa.
                            </p>
                        </div>

                        <div>
                            <label for="aviso-nota" class="block mb-2 text-[11px] font-bold tracking-[0.2em] text-gray-300 uppercase">
                                Nota <span class="font-normal normal-case tracking-normal text-gray-500">(opcional)</span>
                            </label>
                            <textarea
                                id="aviso-nota"
                                v-model="avisoForm.note"
                                rows="3"
                                maxlength="600"
                                placeholder="Ej. Se recorrió por mantenimiento de los servidores de Epic. Tu lugar sigue apartado."
                                class="sem-input"
                            ></textarea>
                            <p class="mt-1.5 text-xs text-gray-500">Aparece dentro del correo, debajo de la fecha.</p>
                        </div>

                        <label class="flex items-start gap-3 p-3 text-xs text-gray-300 border cursor-pointer border-white/15 bg-white/[0.03]">
                            <input v-model="avisoConfirmado" type="checkbox" class="mt-0.5 accent-fnGold" />
                            <span>
                                Confirmo que quiero mandar este correo a los {{ avisoDestinatarios }} inscrito(s) de
                                {{ avisoEvento?.name }}.
                            </span>
                        </label>

                        <div
                            v-if="avisoMensaje"
                            class="flex items-start gap-3 p-3 text-sm"
                            :class="avisoOk ? 'border border-fnEmerald/50 bg-fnEmerald/10 text-fnEmerald' : 'border border-fnCrimson/50 bg-fnCrimson/10 text-fnCrimson'"
                        >
                            <i class="text-lg ph-fill shrink-0" :class="avisoOk ? 'ph-check-circle' : 'ph-warning-circle'"></i>
                            <span class="break-words">{{ avisoMensaje }}</span>
                        </div>

                        <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                            <button
                                type="submit"
                                class="flex-1 sem-btn"
                                :class="avisoEnviando || !avisoConfirmado ? 'sem-btn-disabled' : 'sem-btn-primary'"
                                :disabled="avisoEnviando || !avisoConfirmado"
                            >
                                <span class="relative z-10 flex items-center justify-center gap-2">
                                    <span v-if="avisoEnviando" class="sem-spinner"></span>
                                    <i v-else class="ph-bold ph-paper-plane-tilt"></i>
                                    {{ avisoEnviando ? 'Enviando correos…' : 'Enviar aviso' }}
                                </span>
                            </button>
                            <button
                                type="button"
                                class="px-6 py-4 text-xs font-bold tracking-widest text-gray-400 uppercase transition-colors border border-white/15 hover:text-white hover:border-white/40 disabled:opacity-40"
                                :disabled="avisoEnviando"
                                @click="cerrarAviso"
                            >
                                Cerrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <RankitContactWidget origen="Landing Semanales" />
</div>
</template>

<style scoped>
/* --- Tipografía del hero --- */
.sem-titulo {
    background: linear-gradient(120deg, #ffffff 0%, #ffffff 35%, #00e5ff 65%, #bf00ff 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    filter: drop-shadow(0 12px 30px rgba(0, 229, 255, 0.18));
}

/* --- Chips --- */
.sem-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    border-width: 1px;
    border-style: solid;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    line-height: 1.1;
    max-width: 100%;
}

/* --- Tarjetas --- */
.sem-card {
    position: relative;
    background: linear-gradient(160deg, rgba(15, 8, 28, 0.95) 0%, rgba(5, 2, 12, 0.95) 100%);
    border: 1px solid rgba(255, 255, 255, 0.08);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.3s ease, box-shadow 0.3s ease;
}
.sem-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0, 229, 255, 0.45);
    box-shadow: 0 24px 60px -28px rgba(0, 229, 255, 0.45);
}

.sem-panel {
    background: rgba(10, 5, 20, 0.7);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

/* --- Botones --- */
.sem-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 1rem 2rem;
    font-family: 'Chakra Petch', ui-sans-serif, system-ui;
    font-weight: 700;
    font-size: 0.8rem;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    clip-path: polygon(14px 0, 100% 0, 100% calc(100% - 14px), calc(100% - 14px) 100%, 0 100%, 0 14px);
    text-align: center;
}

.sem-btn-primary {
    background: linear-gradient(100deg, #00e5ff 0%, #9b30ff 100%);
    color: #05020c;
    box-shadow: 0 0 34px -12px rgba(0, 229, 255, 0.8);
}
.sem-btn-primary:hover {
    filter: brightness(1.12);
    transform: translateY(-2px);
    box-shadow: 0 0 44px -8px rgba(155, 48, 255, 0.9);
}

.sem-btn-ghost {
    background: transparent;
    color: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.22);
}
.sem-btn-ghost:hover {
    border-color: #00e5ff;
    color: #00e5ff;
    transform: translateY(-2px);
}

.sem-btn-disabled {
    background: rgba(255, 255, 255, 0.05);
    color: #8b8b96;
    border: 1px solid rgba(255, 255, 255, 0.12);
    cursor: not-allowed;
}
.sem-btn-disabled:hover {
    transform: none;
    filter: none;
}
.sem-btn:disabled {
    cursor: not-allowed;
    opacity: 0.75;
}

/* --- Modal --- */
.sem-modal {
    background: #0f081c;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 40px 120px -30px rgba(0, 0, 0, 0.9);
    animation: semSubir 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes semSubir {
    from {
        opacity: 0;
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* --- Inputs --- */
.sem-input {
    width: 100%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.14);
    color: #ffffff;
    padding: 0.8rem 0.9rem;
    font-family: 'Archivo', ui-sans-serif, system-ui;
    font-size: 0.95rem;
    outline: none;
    transition: all 0.2s ease;
}
.sem-input::placeholder {
    color: #6b6b78;
}
.sem-input:focus {
    border-color: #00e5ff;
    background: rgba(0, 229, 255, 0.06);
    box-shadow: 0 0 0 1px rgba(0, 229, 255, 0.35);
}
.sem-input-error {
    border-color: #d7263d;
    background: rgba(215, 38, 61, 0.08);
}
/* Correo bloqueado: se ve claramente como campo de solo lectura */
.sem-input[readonly] {
    background: rgba(255, 255, 255, 0.02);
    border-style: dashed;
}
.sem-input[readonly]:focus {
    border-color: rgba(255, 255, 255, 0.14);
    background: rgba(255, 255, 255, 0.02);
    box-shadow: none;
}

/* --- Acordeón de reglas --- */
.sem-reglas summary::-webkit-details-marker {
    display: none;
}
.sem-reglas summary {
    list-style: none;
}
.sem-reglas[open] .sem-reglas-caret {
    transform: rotate(180deg);
}

/* --- Spinner --- */
.sem-spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(5, 2, 12, 0.35);
    border-top-color: #05020c;
    border-radius: 9999px;
    animation: semGirar 0.7s linear infinite;
    display: inline-block;
}

@keyframes semGirar {
    to {
        transform: rotate(360deg);
    }
}
</style>
