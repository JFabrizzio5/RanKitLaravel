<script setup>
/**
 * WIDGET DE CONTACTO RANKIT
 * -------------------------------------------------------------------------
 * Botón flotante de WhatsApp + chatbot de palabras clave (sin backend ni IA),
 * al estilo del que ya usamos en la landing de CometaX.
 *
 * La idea es que el visitante no tenga que llenar un formulario largo ni
 * esperar un correo: resuelve la duda en el chat y termina en WhatsApp con el
 * mensaje ya redactado.
 */
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'

const props = defineProps({
    lang: { type: String, default: 'es' },
    waNumber: { type: String, default: '525532351392' },
    instagram: { type: String, default: 'https://instagram.com/rankit.pro' },
    // Contexto de la página (se manda en el mensaje para que ventas ubique de dónde viene)
    origen: { type: String, default: 'Landing Rankit' },
})

const es = computed(() => props.lang !== 'en')

/* ------------------------------------------------------------------ */
/* Textos                                                               */
/* ------------------------------------------------------------------ */

const txt = computed(() =>
    es.value
        ? {
              waLabel: 'Hablar con ventas',
              chatOpen: 'Abrir chat',
              chatClose: 'Cerrar chat',
              title: 'Asistente Rankit',
              subtitle: 'Respuesta al instante',
              greeting:
                  '¡Hola! 👋 Soy el asistente de Rankit. Organizamos y gestionamos <b>torneos, ligas y eventos</b> de esports y deportes. Pregúntame por <b>planes</b>, <b>torneos</b>, <b>semanales</b>, <b>1v1</b> o <b>contacto</b>.',
              placeholder: 'Escribe tu duda…',
              quick: ['Planes', 'Torneos', 'Semanales', '1v1', 'Contacto'],
              formTitle: 'Cuéntanos tu idea',
              formHint: 'Te contestamos por WhatsApp, sin correos ni esperas.',
              fName: 'Tu nombre',
              fType: '¿Qué eres?',
              fMsg: '¿Qué quieres organizar?',
              fSend: 'Enviar por WhatsApp',
              fOpen: 'Quiero que me contacten',
              fBack: 'Volver al chat',
              types: ['Organizador de torneos', 'Streamer / creador', 'Equipo o club', 'Negocio / cancha', 'Jugador'],
              errName: 'Escribe tu nombre.',
              errMsg: 'Cuéntanos brevemente qué necesitas.',
              fallback:
                  'De eso no estoy seguro 🤔. Prueba con <b>planes</b>, <b>torneos</b>, <b>semanales</b>, <b>1v1</b> o <b>contacto</b>, o escríbenos directo por WhatsApp.',
          }
        : {
              waLabel: 'Talk to sales',
              chatOpen: 'Open chat',
              chatClose: 'Close chat',
              title: 'Rankit Assistant',
              subtitle: 'Instant reply',
              greeting:
                  'Hi! 👋 I\'m the Rankit assistant. We run and manage <b>tournaments, leagues and events</b> for esports and sports. Ask me about <b>plans</b>, <b>tournaments</b>, <b>weeklies</b>, <b>1v1</b> or <b>contact</b>.',
              placeholder: 'Type your question…',
              quick: ['Plans', 'Tournaments', 'Weeklies', '1v1', 'Contact'],
              formTitle: 'Tell us your idea',
              formHint: 'We reply on WhatsApp — no emails, no waiting.',
              fName: 'Your name',
              fType: 'What are you?',
              fMsg: 'What do you want to run?',
              fSend: 'Send via WhatsApp',
              fOpen: 'I want to be contacted',
              fBack: 'Back to chat',
              types: ['Tournament organizer', 'Streamer / creator', 'Team or club', 'Business / venue', 'Player'],
              errName: 'Please write your name.',
              errMsg: 'Tell us briefly what you need.',
              fallback:
                  'Not sure about that 🤔. Try <b>plans</b>, <b>tournaments</b>, <b>weeklies</b>, <b>1v1</b> or <b>contact</b>, or message us directly on WhatsApp.',
          },
)

/* Base de conocimiento: coincidencia por palabras clave, igual que en CometaX. */
const faq = [
    {
        k: ['plan', 'planes', 'precio', 'precios', 'costo', 'cuanto', 'cuánto', 'tarifa', 'price', 'plans', 'cost', 'pricing'],
        es: 'Tenemos <b>Base $250 MXN/mes</b> (torneos, brackets y página pública), <b>Gestor Pro $800 MXN/mes</b> (torneos ilimitados, widgets de OBS y estadísticas) y <b>Empresas $5,000 MXN/mes</b> (marca blanca y multiusuario). El <b>staff extra</b> y el <b>soporte prioritario</b> se contratan aparte, así pagas sólo lo que usas.',
        en: 'We offer <b>Base $250 MXN/mo</b> (tournaments, brackets and public page), <b>Pro Manager $800 MXN/mo</b> (unlimited tournaments, OBS widgets and stats) and <b>Enterprise $5,000 MXN/mo</b> (white label and multi-user). <b>Extra staff</b> and <b>priority support</b> are separate add-ons, so you only pay for what you use.',
    },
    {
        k: ['contratar', 'pagar', 'comprar', 'suscri', 'hire', 'buy', 'subscribe', 'pago'],
        es: 'Para contratar escríbenos por <b>WhatsApp</b> o mándanos DM al Instagram <b>@rankit.pro</b>. También puedes entrar a <b>cometax.click</b>, que es la empresa detrás de Rankit.',
        en: 'To get started message us on <b>WhatsApp</b> or DM us on Instagram <b>@rankit.pro</b>. You can also visit <b>cometax.click</b>, the company behind Rankit.',
    },
    {
        k: ['torneo', 'torneos', 'liga', 'ligas', 'bracket', 'brackets', 'tournament', 'league', 'organiz'],
        es: 'Organizamos y gestionamos torneos y ligas: inscripciones, brackets automáticos, calendario, resultados, tablas en vivo y <b>widgets para OBS</b> si transmites. Sirve igual para esports (Fortnite, Valorant, LoL) que para deportes (fútbol 7, pádel, básquet).',
        en: 'We run and manage tournaments and leagues: registrations, automatic brackets, schedule, results, live standings and <b>OBS widgets</b> if you stream. Works for esports (Fortnite, Valorant, LoL) and sports (7-a-side, padel, basketball).',
    },
    {
        k: ['semanal', 'semanales', 'weekly', 'weeklies', 'gratis', 'free'],
        es: 'Los <b>Semanales</b> son eventos promocionales con <b>entrada gratis</b> y premios en metálico cada semana. Te inscribes con tu cuenta, te avisamos por mensaje y los códigos llegan por correo. Míralos en <a class="underline" href="/semanales">/semanales</a>.',
        en: 'The <b>Weeklies</b> are promo events with <b>free entry</b> and weekly cash prizes. Sign up with your account, we notify you by message and codes arrive by email. See them at <a class="underline" href="/semanales">/semanales</a>.',
    },
    {
        k: ['1v1', '1 vs 1', 'duel', 'duelo', 'wager', 'wagers', 'apuesta', 'reto', 'retos'],
        es: 'Estamos construyendo <b>1v1 y Wagers</b>: retos entre jugadores o equipos con bolsa, y <b>cobro de inscripciones dentro de la plataforma</b>. Va en camino — mira el detalle en <a class="underline" href="/duels">/duels</a> y te avisamos cuando abra.',
        en: 'We\'re building <b>1v1 and Wagers</b>: player or team challenges with a prize pot, plus <b>entry fee collection inside the platform</b>. Coming soon — details at <a class="underline" href="/duels">/duels</a> and we\'ll ping you at launch.',
    },
    {
        k: ['inscrip', 'cobro', 'cobrar', 'pagos', 'entrada', 'entradas', 'registration', 'payment', 'charge'],
        es: 'Ya puedes abrir inscripciones y llevar el control de quién pagó. El <b>cobro en línea dentro de la plataforma</b> está en desarrollo: la idea es que el dinero de las inscripciones entre directo, sin hojas de cálculo.',
        en: 'You can already open registrations and track who paid. <b>Online payment inside the platform</b> is in development: entry money will land directly, no spreadsheets.',
    },
    {
        k: ['contacto', 'contactar', 'whatsapp', 'correo', 'email', 'telefono', 'teléfono', 'hablar', 'contact', 'phone', 'ventas', 'sales'],
        es: 'Escríbenos por <b>WhatsApp</b> con el botón verde, o por Instagram <b>@rankit.pro</b>. Contestamos rápido y te armamos una propuesta.',
        en: 'Message us on <b>WhatsApp</b> with the green button, or on Instagram <b>@rankit.pro</b>. We reply fast and put a proposal together.',
    },
    {
        k: ['stream', 'streamer', 'obs', 'twitch', 'transmi', 'overlay', 'widget'],
        es: 'Si transmites, tienes <b>widgets para OBS</b> con tablas y estadísticas en vivo, y página pública del torneo para compartir con tu comunidad. Muchos creadores lo usan para sus torneos comunitarios.',
        en: 'If you stream, you get <b>OBS widgets</b> with live standings and stats, plus a public tournament page to share with your community. Many creators use it for community tournaments.',
    },
    {
        k: ['cometax', 'empresa', 'quienes', 'quiénes', 'company', 'who are you'],
        es: 'Rankit es un producto de <b>CometaX</b>, la casa de software que lo desarrolla y da el soporte. Puedes ver más en <a class="underline" href="https://cometax.click" target="_blank" rel="noopener">cometax.click</a>.',
        en: 'Rankit is a product by <b>CometaX</b>, the software company that builds and supports it. More at <a class="underline" href="https://cometax.click" target="_blank" rel="noopener">cometax.click</a>.',
    },
]

/* ------------------------------------------------------------------ */
/* Estado                                                               */
/* ------------------------------------------------------------------ */

const abierto = ref(false)
const vistaFormulario = ref(false)
const mensajes = ref([])
const entrada = ref('')
const inputRef = ref(null)
const logRef = ref(null)
let saludado = false

const form = ref({ nombre: '', tipo: '', mensaje: '' })
const errorForm = ref('')

const enlaceWhatsApp = computed(() => {
    const saludo = es.value
        ? 'Hola Rankit, vengo de la página web y quiero información para organizar mi torneo.'
        : 'Hi Rankit, I\'m coming from your website and I\'d like info to run my tournament.'
    return 'https://wa.me/' + props.waNumber + '?text=' + encodeURIComponent(saludo)
})

function agregar(quien, html) {
    mensajes.value.push({ id: mensajes.value.length + 1, quien, html })
    nextTick(() => {
        if (logRef.value) logRef.value.scrollTop = logRef.value.scrollHeight
    })
}

function responder(texto) {
    const t = texto.toLowerCase()
    const hit = faq.find((f) => f.k.some((kw) => t.includes(kw)))
    if (hit) return es.value ? hit.es : hit.en
    return txt.value.fallback
}

function alternar() {
    abierto.value = !abierto.value
    if (abierto.value) {
        vistaFormulario.value = false
        if (!saludado) {
            saludado = true
            agregar('bot', txt.value.greeting)
        }
        nextTick(() => inputRef.value?.focus())
    }
}

function enviar() {
    const texto = (entrada.value || '').trim()
    if (!texto) return
    agregar('yo', texto.replace(/</g, '&lt;'))
    entrada.value = ''
    setTimeout(() => agregar('bot', responder(texto)), 250)
}

function preguntaRapida(q) {
    entrada.value = q
    enviar()
}

/* Formulario corto -> abre WhatsApp con el mensaje ya escrito. */
function enviarFormulario() {
    const nombre = form.value.nombre.trim()
    const mensaje = form.value.mensaje.trim()

    if (!nombre) {
        errorForm.value = txt.value.errName
        return
    }
    if (!mensaje) {
        errorForm.value = txt.value.errMsg
        return
    }
    errorForm.value = ''

    const tipo = form.value.tipo || txt.value.types[0]
    const cuerpo = es.value
        ? ['Hola Rankit, les escribo desde la web.', '', 'Nombre: ' + nombre, 'Perfil: ' + tipo, 'Origen: ' + props.origen, '', 'Lo que quiero organizar:', mensaje]
        : ['Hi Rankit, writing from your website.', '', 'Name: ' + nombre, 'Profile: ' + tipo, 'Source: ' + props.origen, '', 'What I want to run:', mensaje]

    window.open('https://wa.me/' + props.waNumber + '?text=' + encodeURIComponent(cuerpo.join('\n')), '_blank', 'noopener')
}

function alPresionar(e) {
    if (e.key === 'Escape' && abierto.value) abierto.value = false
}

onMounted(() => window.addEventListener('keydown', alPresionar))
onUnmounted(() => window.removeEventListener('keydown', alPresionar))
</script>

<template>
    <div class="fixed z-[60] bottom-4 right-4 flex flex-col items-end gap-3 print:hidden">
        <!-- Panel del chat -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-3"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0 translate-y-3"
        >
            <div
                v-show="abierto"
                class="w-[min(92vw,22rem)] overflow-hidden border border-white/10 bg-[#0a0a0a] shadow-2xl rounded-2xl"
            >
                <!-- Cabecera -->
                <div class="flex items-center gap-3 px-4 py-3 border-b border-white/10 bg-gradient-to-r from-[var(--rankit-neon)]/20 to-transparent">
                    <span class="flex items-center justify-center w-9 h-9 rounded-full bg-[var(--rankit-neon)]">
                        <i class="text-lg text-white ph-fill ph-chats-circle"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold tracking-wide text-white uppercase font-display">{{ txt.title }}</p>
                        <p class="flex items-center gap-1.5 text-[10px] text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>{{ txt.subtitle }}
                        </p>
                    </div>
                    <button class="ml-auto text-gray-500 hover:text-white" :aria-label="txt.chatClose" @click="abierto = false">
                        <i class="text-lg ph-bold ph-x"></i>
                    </button>
                </div>

                <!-- Conversación -->
                <div v-show="!vistaFormulario">
                    <div ref="logRef" class="h-64 px-4 py-4 space-y-3 overflow-y-auto">
                        <div v-for="m in mensajes" :key="m.id" class="flex" :class="m.quien === 'yo' ? 'justify-end' : 'justify-start'">
                            <div
                                class="px-3 py-2 max-w-[85%] text-sm leading-relaxed rounded-2xl"
                                :class="m.quien === 'yo'
                                    ? 'bg-[var(--rankit-neon)] text-white rounded-br-sm'
                                    : 'bg-white/5 text-gray-200 border border-white/10 rounded-bl-sm'"
                                v-html="m.html"
                            ></div>
                        </div>
                    </div>

                    <!-- Atajos -->
                    <div class="flex flex-wrap gap-1.5 px-4 pb-3">
                        <button
                            v-for="q in txt.quick"
                            :key="q"
                            class="px-2.5 py-1 text-[10px] font-bold tracking-wider text-gray-300 uppercase transition-colors border rounded-full border-white/15 hover:border-[var(--rankit-neon)] hover:text-white"
                            @click="preguntaRapida(q)"
                        >
                            {{ q }}
                        </button>
                    </div>

                    <form class="flex gap-2 px-4 pb-3" @submit.prevent="enviar">
                        <input
                            ref="inputRef"
                            v-model="entrada"
                            type="text"
                            autocomplete="off"
                            :placeholder="txt.placeholder"
                            class="flex-1 px-4 py-2 text-sm text-white border rounded-full outline-none bg-white/5 border-white/15 placeholder:text-gray-600 focus:border-[var(--rankit-neon)]"
                        />
                        <button class="shrink-0 rounded-full bg-[var(--rankit-neon)] px-4 text-sm font-bold text-white transition hover:brightness-125">
                            <i class="ph-bold ph-paper-plane-right"></i>
                        </button>
                    </form>

                    <button
                        class="w-full px-4 py-3 text-[11px] font-bold tracking-widest text-black uppercase transition-colors bg-[#25D366] hover:brightness-110"
                        @click="vistaFormulario = true"
                    >
                        <i class="ph-fill ph-whatsapp-logo"></i> {{ txt.fOpen }}
                    </button>
                </div>

                <!-- Formulario corto -->
                <div v-show="vistaFormulario" class="px-4 py-4 space-y-3">
                    <div>
                        <p class="text-sm font-bold tracking-wide text-white uppercase font-display">{{ txt.formTitle }}</p>
                        <p class="mt-1 text-[11px] text-gray-500">{{ txt.formHint }}</p>
                    </div>

                    <input
                        v-model="form.nombre"
                        type="text"
                        maxlength="60"
                        :placeholder="txt.fName"
                        class="w-full px-3 py-2 text-sm text-white border rounded-lg outline-none bg-white/5 border-white/15 placeholder:text-gray-600 focus:border-[var(--rankit-neon)]"
                    />

                    <select
                        v-model="form.tipo"
                        class="w-full px-3 py-2 text-sm text-white border rounded-lg outline-none bg-white/5 border-white/15 focus:border-[var(--rankit-neon)]"
                    >
                        <option value="" disabled>{{ txt.fType }}</option>
                        <option v-for="tipo in txt.types" :key="tipo" :value="tipo" class="text-black">{{ tipo }}</option>
                    </select>

                    <textarea
                        v-model="form.mensaje"
                        rows="3"
                        maxlength="400"
                        :placeholder="txt.fMsg"
                        class="w-full px-3 py-2 text-sm text-white border rounded-lg outline-none resize-none bg-white/5 border-white/15 placeholder:text-gray-600 focus:border-[var(--rankit-neon)]"
                    ></textarea>

                    <p v-if="errorForm" class="text-xs text-red-400">{{ errorForm }}</p>

                    <button
                        class="w-full py-3 text-[11px] font-bold tracking-widest text-black uppercase transition-colors bg-[#25D366] hover:brightness-110 rounded-lg"
                        @click="enviarFormulario"
                    >
                        <i class="ph-fill ph-whatsapp-logo"></i> {{ txt.fSend }}
                    </button>

                    <button class="w-full text-[11px] tracking-wider text-gray-500 uppercase hover:text-white" @click="vistaFormulario = false">
                        {{ txt.fBack }}
                    </button>
                </div>
            </div>
        </transition>

        <!-- Botones flotantes -->
        <div class="flex items-center gap-2">
            <a
                :href="enlaceWhatsApp"
                target="_blank"
                rel="noopener"
                :aria-label="txt.waLabel"
                class="flex items-center gap-2 px-4 py-3 text-xs font-bold tracking-wider text-black uppercase transition-transform bg-[#25D366] rounded-full shadow-[0_8px_30px_rgba(37,211,102,0.35)] hover:scale-105"
            >
                <i class="text-xl ph-fill ph-whatsapp-logo"></i>
                <span class="hidden sm:inline">{{ txt.waLabel }}</span>
            </a>

            <button
                type="button"
                :aria-label="abierto ? txt.chatClose : txt.chatOpen"
                class="flex items-center justify-center text-white transition-transform rounded-full shadow-lg h-14 w-14 bg-[var(--rankit-neon)] hover:scale-105"
                @click="alternar"
            >
                <i class="text-2xl ph-fill" :class="abierto ? 'ph-x' : 'ph-chat-teardrop-dots'"></i>
            </button>
        </div>
    </div>
</template>
