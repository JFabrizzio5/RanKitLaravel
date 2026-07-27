<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import RankitContactWidget from '@/Components/RankitContactWidget.vue'

const props = defineProps<{
  canLogin?: boolean
  canRegister?: boolean
  laravelVersion: string
  phpVersion: string
}>()

/**
 * THEME MANAGEMENT
 * Sincronizado con la clase 'dark' en el HTML
 */
const isDark = ref(true)

function applyTheme(nextDark: boolean) {
  isDark.value = nextDark
  const html = document.documentElement
  if (nextDark) {
    html.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    html.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

function toggleTheme() {
  applyTheme(!isDark.value)
}

/**
 * IDIOMA (ES/EN)
 * Actualizado con todos los textos del nuevo HTML
 */
type Lang = 'es' | 'en'
const lang = ref<Lang>('es')

function toggleLanguage() {
  lang.value = lang.value === 'es' ? 'en' : 'es'
}

const t = computed(() => {
  const translations = {
    es: {
      nav: { about: 'Plataforma', tournaments: 'Torneos', pricing: 'Precios', custom: 'Personalizado', partners: 'Partners', weekly: 'Semanales', login: 'Ingresar', create: 'Crear Cuenta', dashboard: 'Dashboard' },
      // Menú "Competencias": agrupa las páginas de juego para no saturar la barra
      menu: {
        compete: 'Competencias',
        competeDesc: 'Todo lo que puedes jugar en Rankit',
        league: 'Rankit League',
        leagueDesc: 'La liga oficial por temporadas',
        weekly: 'Semanales',
        weeklyDesc: 'Gratis, con premios en metálico',
        worldcup: 'Mundial 2026',
        worldcupDesc: 'Campeón, bracket y estadísticas',
        live: 'Torneos en curso',
        liveDesc: 'Lo que se está jugando ahora',
        duels: '1v1 y Wagers',
        duelsDesc: 'Retos con bolsa · Próximamente',
        soon: 'Pronto',
        badgeNew: 'Nuevo',
        open: 'Abrir menú',
        close: 'Cerrar menú',
        admin: 'Admin',
        adminFull: 'Admin Eventos',
        theme: 'Cambiar tema',
        language: 'Cambiar idioma',
      },
      hero: {
        powered: 'Powered by',
        badge: 'Plataforma de Torneos v2.0',
        title1: 'Gestiona.',
        title2: 'Compite.',
        title3: 'Escala.',
        desc: 'Desde canchas de fútbol 7 hasta arenas de esports. Rankit es el sistema operativo para organizadores que buscan profesionalismo.',
        btnOrganize: 'Lobby',
        btnDemo: 'Ver Demo',
      },
      tournaments: { title: 'Torneos', titleSub: 'En Curso', desc: 'Únete a la competencia. Demuestra tu nivel.', viewAll: 'Ver Todos', cardCreate: 'Crea tu Torneo' },
      about: {
        tag: 'Qué es Rankit',
        title: 'El sistema operativo de',
        titleSub: 'la competencia',
        lead: 'Rankit es la plataforma donde se arma, se juega y se sigue una competencia completa: desde que abres inscripciones hasta que publicas al campeón. Da igual si es un torneo de Fortnite en Discord o una liga de fútbol 7 los sábados.',
        body: 'Nació de organizar eventos reales y toparse siempre con lo mismo: pagos por transferencia que nadie cuadra, brackets en hojas de cálculo, resultados en capturas de pantalla y una comunidad preguntando a qué hora juega. Rankit junta todo eso en un solo lugar, con página pública para tus jugadores y datos en vivo para tu transmisión.',
        f1Title: 'Inscripciones y control',
        f1Desc: 'Abres el registro, recibes jugadores o equipos y llevas quién pagó, quién está confirmado y quién quedó en lista de espera.',
        f2Title: 'Brackets y calendario',
        f2Desc: 'Eliminación, grupos o liga: el sistema arma los cruces, agenda las partidas y recalcula la tabla en cuanto cargas un resultado.',
        f3Title: 'Resultados y estadísticas',
        f3Desc: 'Puntos por posición y por kills, ajustes manuales con bitácora, clasificados por etapa y tablas que se actualizan solas.',
        f4Title: 'Widgets para tu stream',
        f4Desc: 'Overlays de OBS con la tabla en vivo y la ficha de cada jugador, más una página pública que puedes compartir con tu comunidad.',
        forTag: 'Para quién es',
        for1: 'Organizadores de torneos y ligas',
        for2: 'Streamers y creadores de contenido',
        for3: 'Canchas, gimnasios y centros de gaming',
        for4: 'Equipos, clubes y comunidades',
        byline: 'Rankit es un producto de CometaX, la casa de software que lo desarrolla y le da soporte.',
        bylineCta: 'Conocer CometaX',
      },
      compete: {
        tag: 'Compite con nosotros',
        title: 'Nuestras',
        titleSub: 'competencias',
        desc: 'Antes de venderte la plataforma, la usamos nosotros. Estas son las competencias que corremos en Rankit.',
        ownTitle: '¿Y lo tuyo?',
        ownDesc: 'Monta tu torneo o tu liga con las mismas herramientas. Si prefieres que te lo expliquemos, escríbenos y lo vemos en 5 minutos.',
        ownCta: 'Crear cuenta gratis',
        ownCta2: 'Hablar con ventas',
        soonBadge: 'Pronto',
      },
      contact: {
        rankitTitle: 'Torneos y ligas',
        rankitDesc: 'Escríbenos por DM y te armamos una demo o una propuesta para tu evento.',
        rankitCta: 'DM a @rankit.pro',
        cometaxTitle: 'Software a medida',
        cometaxDesc: 'Si necesitas un sistema propio, integraciones o algo fuera de Rankit, lo construye CometaX.',
        cometaxCta: 'Ver CometaX',
      },
      league: {
        tag: 'Competencia insignia',
        title: 'Rankit',
        titleSub: 'League',
        desc: 'Nuestra liga propia por temporadas: clasificatorios, repechaje y gran final, con tabla acumulada y bolsa en juego. Es la vitrina de lo que la plataforma puede hacer con tu evento.',
        p1: 'Formato por etapas con clasificación acumulada',
        p2: 'Tabla en vivo y widgets en transmisión',
        p3: 'Premios y clasificados por corte',
        cta: 'Ver Rankit League',
        weeklyBridge: '¿Vas empezando? Los Semanales son la puerta de entrada: gratis, cada semana y con premio en metálico.',
        weeklyCta: 'Ver Semanales',
      },
      semanales: {
        tag: 'Nuevo · Eventos promocionales',
        title: 'Torneos',
        titleSub: 'Semanales',
        desc: 'Cada semana lanzamos torneos promocionales con entrada gratuita y premios en metálico. Sin cuota, sin excusas.',
        dateLabel: 'PRÓXIMAMENTE',
        free: 'ENTRADA GRATIS',
        prize: 'PREMIOS EN METÁLICO SEMANALES',
        requirements: 'Epic ID + WhatsApp · Discord opcional',
        cta: 'Ver Semanales',
        note: 'Los Semanales son eventos promocionales gratuitos y NO forman parte de Rankit League: no otorgan puntos ni plazas en la liga.',
        card1: 'Semanal 1',
        card2: 'Semanal 2',
      },
      pricing: {
        title: 'Elige tu',
        titleSub: 'Nivel',
        desc: 'Planes diseñados para cada etapa de tu organización. Cancela cuando quieras.',
        period: 'mes',
        btnStart: 'Comenzar',
        btnPro: 'Obtener Pro',
        btnContact: 'Contactar',
        recommended: 'Recomendado',
        planEnterprise: 'Empresas',
        feat: {
          tournaments: '2 Torneos al mes',
          brackets: 'Brackets Automáticos',
          publicPage: 'Página pública del torneo',
          unlimited: 'Torneos Ilimitados',
          obs: 'OBS Widgets (Streaming)',
          payments: 'Inscripciones y control de pagos',
          stats: 'Estadísticas y tablas en vivo',
          multiuser: 'Multiusuario',
          whitelabel: 'Marca Blanca',
          priority: 'Soporte prioritario incluido',
          noPriority: 'Sin soporte prioritario (se contrata aparte)',
        },
        addonsTag: 'Complementos',
        addonsTitle: 'Arma tu plan',
        addonsDesc: 'Cada organización pesa distinto. En vez de inflar el plan base, agregas sólo lo que necesitas y el ticket se ajusta a tu tamaño.',
        addonStaffTitle: 'Staff adicional',
        addonStaffDesc: 'Suma jueces, moderadores o coorganizadores a tu panel, cada uno con su acceso.',
        addonSupportTitle: 'Soporte prioritario',
        addonSupportDesc: 'Atención en horario de evento y respuesta preferente cuando algo se cae en plena final.',
        addonSetupTitle: 'Puesta en marcha',
        addonSetupDesc: 'Te dejamos configurado el primer torneo, los widgets y el formato de puntos.',
        addonPrice: 'Se cotiza según tu evento',
        soonTag: 'En desarrollo',
        soonTitle: 'Lo que viene',
        soonDesc: 'Estamos construyendo el siguiente paso del ecosistema. Nada de esto está disponible todavía y lo decimos antes, no después.',
        soon1: 'Cobro de inscripciones dentro de la plataforma',
        soon1Desc: 'Que el dinero de las entradas te llegue directo, sin transferencias sueltas ni hojas de cálculo.',
        soon2: 'Wagers entre equipos',
        soon2Desc: 'Dos escuadras acuerdan la bolsa, juegan su serie y el sistema reparte según el resultado.',
        soon3: 'Duelos 1v1',
        soon3Desc: 'Retos directos entre jugadores con la bolsa resguardada hasta validar el resultado.',
        soonCta: 'Ver el detalle de 1v1 y Wagers',
      },
      hire: {
        title: 'Contratar',
        subtitle: 'Así lo activamos',
        intro: 'Todavía no cobramos en línea dentro de Rankit. Para contratar hablamos contigo, revisamos tu caso y activamos el plan a mano — normalmente el mismo día.',
        planLabel: 'Plan elegido',
        step1Title: 'Escríbenos por Instagram',
        step1Desc: 'La vía más rápida: DM a @rankit.pro. Ahí resolvemos dudas y te pasamos los datos de pago.',
        step1Cta: 'Abrir @rankit.pro',
        step2Title: 'O por WhatsApp',
        step2Desc: 'Si prefieres el teléfono, escribe al WhatsApp de ventas y te contestamos igual de rápido.',
        step2Cta: 'Escribir por WhatsApp',
        step3Title: 'O desde CometaX',
        step3Desc: 'Rankit es un producto de CometaX. En su sitio puedes agendar una reunión y ver el resto del ecosistema.',
        step3Cta: 'Ir a cometax.click',
        note: 'Al contactarnos dinos qué plan quieres y qué organizas: con eso te armamos la propuesta y activamos tu cuenta.',
        close: 'Cerrar',
      },
      alliance: {
        tag: 'Rankit Alliance',
        title: 'Únete al',
        titleSub: 'Sindicato',
        desc: 'Construye el futuro de los esports con nosotros. Monetiza tu influencia o tu red de contactos.',
        techTitle: 'Vendedores de Tecnología',
        techDesc: '¿Vendes software o hardware a canchas deportivas? Añade Rankit a tu portafolio y gana comisiones recurrentes.',
        techCta: 'Aplicar al Programa',
        creatorTitle: 'Creadores de Contenido',
        creatorDesc: 'Streamers y YouTubers: Usa Rankit para tus torneos comunitarios y obtén beneficios exclusivos y revenue share.',
        creatorCta: 'Unirse como Creador',
      },
      karma: {
        title: 'Competencia con',
        titleSub: 'Causa',
        descHtml:
          "El éxito sabe mejor compartido. El <span class='font-bold text-neon'>10% de tu suscripción</span> se va directo a causas sociales vía Fundación CometaX para apoyar el deporte joven.",
      },
      custom: {
        tag: 'Enterprise',
        title: '¿Necesitas algo',
        titleSub: 'a medida?',
        desc: 'Para organizaciones grandes, ligas nacionales o integraciones personalizadas. Cuéntanos tu proyecto.',
        feat1: 'Desarrollo de Features Exclusivos',
        feat2: 'Servidores Dedicados',
        feat3: 'Soporte de Ingeniería 24/7',
        inputName: 'NOMBRE COMPLETO',
        inputEmail: 'CORREO ELECTRÓNICO',
        inputDetails: 'DETALLES DEL PROYECTO',
        btnSend: 'Enviar Solicitud',
      },
    },
    en: {
      nav: { about: 'Platform', tournaments: 'Tournaments', pricing: 'Pricing', custom: 'Custom', partners: 'Partners', weekly: 'Weeklies', login: 'Login', create: 'Sign Up', dashboard: 'Dashboard' },
      // "Compete" menu: groups the game pages so the bar doesn't feel crowded
      menu: {
        compete: 'Compete',
        competeDesc: 'Everything you can play on Rankit',
        league: 'Rankit League',
        leagueDesc: 'The official seasonal league',
        weekly: 'Weeklies',
        weeklyDesc: 'Free, with cash prizes',
        worldcup: 'World Cup 2026',
        worldcupDesc: 'Champion, bracket and stats',
        live: 'Live tournaments',
        liveDesc: 'What is being played right now',
        duels: '1v1 & Wagers',
        duelsDesc: 'Challenges with a pot · Coming soon',
        soon: 'Soon',
        badgeNew: 'New',
        open: 'Open menu',
        close: 'Close menu',
        admin: 'Admin',
        adminFull: 'Events Admin',
        theme: 'Toggle theme',
        language: 'Change language',
      },
      hero: {
        powered: 'Powered by',
        badge: 'Tournament Platform v2.0',
        title1: 'Manage.',
        title2: 'Compete.',
        title3: 'Scale.',
        desc: 'From soccer fields to esports arenas. Rankit is the operating system for organizers seeking professionalism.',
        btnOrganize: 'Organize Tournament',
        btnDemo: 'Watch Demo',
      },
      tournaments: { title: 'Live', titleSub: 'Tournaments', desc: 'Join the competition. Show your skills.', viewAll: 'View All', cardCreate: 'Create Tournament' },
      about: {
        tag: 'What Rankit is',
        title: 'The operating system of',
        titleSub: 'competition',
        lead: 'Rankit is where a full competition gets built, played and followed: from opening registrations to publishing the champion. Works the same for a Fortnite tournament on Discord or a Saturday 7-a-side league.',
        body: 'It was born from running real events and always hitting the same wall: bank transfers nobody reconciles, brackets in spreadsheets, results in screenshots and a community asking when they play. Rankit puts all of that in one place, with a public page for your players and live data for your broadcast.',
        f1Title: 'Registrations and control',
        f1Desc: 'Open sign-ups, receive players or teams and track who paid, who is confirmed and who is on the waitlist.',
        f2Title: 'Brackets and schedule',
        f2Desc: 'Knockout, groups or league: the system builds the matchups, schedules games and recalculates the table as soon as you load a result.',
        f3Title: 'Results and stats',
        f3Desc: 'Points by placement and kills, manual adjustments with an audit log, qualifiers per stage and standings that update themselves.',
        f4Title: 'Widgets for your stream',
        f4Desc: 'OBS overlays with live standings and per-player cards, plus a public page you can share with your community.',
        forTag: 'Who it is for',
        for1: 'Tournament and league organizers',
        for2: 'Streamers and content creators',
        for3: 'Venues, gyms and gaming centers',
        for4: 'Teams, clubs and communities',
        byline: 'Rankit is a product by CometaX, the software company that builds and supports it.',
        bylineCta: 'Visit CometaX',
      },
      compete: {
        tag: 'Compete with us',
        title: 'Our',
        titleSub: 'competitions',
        desc: 'Before selling you the platform, we use it ourselves. These are the competitions we run on Rankit.',
        ownTitle: 'And yours?',
        ownDesc: 'Run your tournament or league with the same tools. If you would rather we walk you through it, message us and we cover it in 5 minutes.',
        ownCta: 'Create free account',
        ownCta2: 'Talk to sales',
        soonBadge: 'Soon',
      },
      contact: {
        rankitTitle: 'Tournaments and leagues',
        rankitDesc: 'DM us and we put together a demo or a proposal for your event.',
        rankitCta: 'DM @rankit.pro',
        cometaxTitle: 'Custom software',
        cometaxDesc: 'If you need your own system, integrations or something beyond Rankit, CometaX builds it.',
        cometaxCta: 'Visit CometaX',
      },
      league: {
        tag: 'Flagship competition',
        title: 'Rankit',
        titleSub: 'League',
        desc: 'Our own seasonal league: qualifiers, last chance and grand final, with a cumulative table and a prize pot. It is the showcase of what the platform can do with your event.',
        p1: 'Staged format with cumulative standings',
        p2: 'Live table and broadcast widgets',
        p3: 'Prizes and qualifiers per cut',
        cta: 'View Rankit League',
        weeklyBridge: 'Just starting out? The Weeklies are the way in: free, every week and with a cash prize.',
        weeklyCta: 'View Weeklies',
      },
      semanales: {
        tag: 'New · Promo events',
        title: 'Weekly',
        titleSub: 'Tournaments',
        desc: 'Every week we launch promotional tournaments with free entry and cash prizes. No fee, no excuses.',
        dateLabel: 'COMING SOON',
        free: 'FREE ENTRY',
        prize: 'WEEKLY CASH PRIZES',
        requirements: 'Epic ID + WhatsApp · Discord optional',
        cta: 'View Weeklies',
        note: 'Weeklies are free promotional events and are NOT part of Rankit League: they grant no league points or slots.',
        card1: 'Weekly 1',
        card2: 'Weekly 2',
      },
      pricing: {
        title: 'Choose your',
        titleSub: 'Level',
        desc: 'Plans designed for every stage of your organization. Cancel anytime.',
        period: 'mo',
        btnStart: 'Start Now',
        btnPro: 'Get Pro',
        btnContact: 'Contact Sales',
        recommended: 'Recommended',
        planEnterprise: 'Enterprise',
        feat: {
          tournaments: '2 Tournaments/mo',
          brackets: 'Auto Brackets',
          publicPage: 'Public tournament page',
          unlimited: 'Unlimited Tournaments',
          obs: 'OBS Widgets (Streaming)',
          payments: 'Registrations and payment tracking',
          stats: 'Live stats and standings',
          multiuser: 'Multi-user Access',
          whitelabel: 'White Label',
          priority: 'Priority support included',
          noPriority: 'No priority support (available as an add-on)',
        },
        addonsTag: 'Add-ons',
        addonsTitle: 'Build your plan',
        addonsDesc: 'Every organization is different. Instead of bloating the base plan, you add only what you need and the ticket fits your size.',
        addonStaffTitle: 'Extra staff',
        addonStaffDesc: 'Add referees, moderators or co-organizers to your panel, each with their own access.',
        addonSupportTitle: 'Priority support',
        addonSupportDesc: 'Event-hours coverage and preferred response when something breaks mid-final.',
        addonSetupTitle: 'Setup service',
        addonSetupDesc: 'We set up your first tournament, the widgets and the scoring format for you.',
        addonPrice: 'Quoted per event',
        soonTag: 'In development',
        soonTitle: 'What is coming',
        soonDesc: 'We are building the next step of the ecosystem. None of this is available yet and we say so upfront, not after.',
        soon1: 'Entry fee collection inside the platform',
        soon1Desc: 'Entry money lands directly with you — no scattered transfers, no spreadsheets.',
        soon2: 'Team wagers',
        soon2Desc: 'Two squads agree on the pot, play their series and the system splits it by result.',
        soon3: '1v1 duels',
        soon3Desc: 'Direct player challenges with the pot held until the result is validated.',
        soonCta: 'See the 1v1 & Wagers details',
      },
      hire: {
        title: 'Get started',
        subtitle: 'How we activate it',
        intro: 'We do not charge online inside Rankit yet. To sign up we talk to you, review your case and activate the plan manually — usually the same day.',
        planLabel: 'Selected plan',
        step1Title: 'Message us on Instagram',
        step1Desc: 'Fastest route: DM @rankit.pro. We answer questions there and send you the payment details.',
        step1Cta: 'Open @rankit.pro',
        step2Title: 'Or on WhatsApp',
        step2Desc: 'If you prefer the phone, message our sales WhatsApp and we reply just as fast.',
        step2Cta: 'Message on WhatsApp',
        step3Title: 'Or through CometaX',
        step3Desc: 'Rankit is a CometaX product. On their site you can book a call and see the rest of the ecosystem.',
        step3Cta: 'Go to cometax.click',
        note: 'When you reach out, tell us which plan you want and what you run: with that we build the proposal and activate your account.',
        close: 'Close',
      },
      alliance: {
        tag: 'Rankit Alliance',
        title: 'Join the',
        titleSub: 'Syndicate',
        desc: 'Build the future of esports with us. Monetize your influence or your network.',
        techTitle: 'Tech Sellers',
        techDesc: 'Selling hardware/software to venues? Add Rankit to your portfolio and earn recurring commissions.',
        techCta: 'Apply to Program',
        creatorTitle: 'Content Creators',
        creatorDesc: 'Streamers & YouTubers: Use Rankit for community tournaments and get exclusive perks plus revenue share.',
        creatorCta: 'Join as Creator',
      },
      karma: {
        title: 'Competition with',
        titleSub: 'Cause',
        descHtml:
          "Success tastes better shared. <span class='font-bold text-neon'>10% of your subscription</span> goes directly to social causes via CometaX Foundation to support young athletes.",
      },
      custom: {
        tag: 'Enterprise',
        title: 'Need something',
        titleSub: 'custom?',
        desc: 'For large organizations, national leagues, or custom integrations. Tell us about your project.',
        feat1: 'Exclusive Feature Development',
        feat2: 'Dedicated Servers',
        feat3: '24/7 Engineering Support',
        inputName: 'FULL NAME',
        inputEmail: 'EMAIL ADDRESS',
        inputDetails: 'PROJECT DETAILS',
        btnSend: 'Send Request',
      },
    },
  } as const

  return translations[lang.value]
})

/**
 * NAVEGACIÓN
 * La barra tenía 7 enlaces sueltos y se veía saturada. Ahora las páginas de
 * juego (League, Semanales, Mundial, Torneos) viven en un solo menú
 * "Competencias", y en móvil hay un panel desplegable en lugar de nada.
 */
const menuCompetencias = ref(false)
const menuMovil = ref(false)

const enlacesCompetencias = computed(() => [
  { href: '/league', icon: 'ph-trophy', title: t.value.menu.league, desc: t.value.menu.leagueDesc, nuevo: false },
  { href: '/semanales', icon: 'ph-calendar-star', title: t.value.menu.weekly, desc: t.value.menu.weeklyDesc, nuevo: true },
  { href: '/mundial', icon: 'ph-globe-hemisphere-west', title: t.value.menu.worldcup, desc: t.value.menu.worldcupDesc, nuevo: false },
  { href: '#competencias', icon: 'ph-broadcast', title: t.value.menu.live, desc: t.value.menu.liveDesc, nuevo: false },
  { href: '/duels', icon: 'ph-sword', title: t.value.menu.duels, desc: t.value.menu.duelsDesc, nuevo: false, pronto: true },
])

function alternarCompetencias() {
  menuCompetencias.value = !menuCompetencias.value
}

function cerrarMenus() {
  menuCompetencias.value = false
  menuMovil.value = false
}

function alternarMenuMovil() {
  menuMovil.value = !menuMovil.value
  if (menuMovil.value) menuCompetencias.value = false
}

function alClicFuera(e: MouseEvent) {
  const objetivo = e.target as HTMLElement | null
  if (objetivo && !objetivo.closest('[data-nav]')) cerrarMenus()
}

function alPresionarEscape(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    cerrarMenus()
    modalContratar.value = false
  }
}

/**
 * CONTRATACIÓN
 * Todavía no hay cobro en línea: el botón de cada plan abre un modal con las
 * tres vías reales para contratar (IG de Rankit, WhatsApp de ventas y CometaX).
 */
const WA_VENTAS = '525532351392'
const IG_RANKIT = 'https://instagram.com/rankit.pro'
const WEB_COMETAX = 'https://cometax.click'

const modalContratar = ref(false)
const planElegido = ref('Gestor Pro')

function abrirContratar(plan: string) {
  planElegido.value = plan
  modalContratar.value = true
}

const enlaceWhatsAppPlan = computed(() => {
  const msg =
    lang.value === 'es'
      ? `Hola Rankit, me interesa contratar el plan ${planElegido.value}. ¿Me pasan los detalles?`
      : `Hi Rankit, I'd like to get the ${planElegido.value} plan. Could you send me the details?`
  return 'https://wa.me/' + WA_VENTAS + '?text=' + encodeURIComponent(msg)
})

onMounted(() => {
  // Theme init
  const savedTheme = localStorage.getItem('theme')
  const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true
  
  if (savedTheme === 'light') applyTheme(false)
  else if (savedTheme === 'dark') applyTheme(true)
  else applyTheme(systemPrefersDark)

  // Load Phosphor Icons dynamically to avoid Vite template compiler warning
  if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
    const script = document.createElement('script')
    script.src = 'https://unpkg.com/@phosphor-icons/web'
    script.async = true
    document.head.appendChild(script)
  }

  document.addEventListener('click', alClicFuera)
  window.addEventListener('keydown', alPresionarEscape)
})

onUnmounted(() => {
  document.removeEventListener('click', alClicFuera)
  window.removeEventListener('keydown', alPresionarEscape)
})
</script>

<template>
  <Head title="Rankit - The Competitive Ecosystem">
    <!-- Meta Tags SEO -->
    <meta name="description" content="La plataforma definitiva para gestionar torneos de Esports y Deportes. Automatiza brackets, cobros y estadísticas en tiempo real." />
    <meta name="keywords" content="torneos, esports, brackets, league manager, futbol 7, padel, gaming, competencia, software deportivo" />
    <meta name="author" content="Rankit Systems" />
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
  </Head>

  <div class="overflow-x-hidden selection:bg-[var(--rankit-neon)] selection:text-white bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white font-sans transition-colors duration-300">
    
    <!-- Navbar -->
    <nav class="fixed w-full z-50 transition-colors duration-300 bg-white/90 border-b border-gray-200 dark:bg-[#050505]/95 dark:border-white/10 backdrop-blur-md h-20 flex items-center px-6 lg:px-12 justify-between">
      
      <!-- Logo -->
      <div class="flex items-center gap-3 cursor-pointer group">
        <svg class="w-10 h-10 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
        </svg>
        <span class="text-3xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">Rankit</span>
      </div>

      <!-- Links Desktop: 4 entradas en vez de 7. Lo jugable vive en "Competencias". -->
      <div class="items-center hidden gap-7 text-sm font-bold tracking-widest text-gray-500 uppercase md:flex dark:text-gray-400" data-nav>
        <!-- Competencias (desplegable) -->
        <div class="relative" @mouseenter="menuCompetencias = true" @mouseleave="menuCompetencias = false">
          <button
            type="button"
            class="flex items-center gap-1.5 transition hover:text-neon"
            :class="menuCompetencias ? 'text-neon' : ''"
            :aria-expanded="menuCompetencias"
            aria-haspopup="true"
            @click="alternarCompetencias"
          >
            <span>{{ t.menu.compete }}</span>
            <span class="w-1.5 h-1.5 rounded-full bg-neon animate-pulse"></span>
            <i class="text-xs transition-transform ph-bold ph-caret-down" :class="menuCompetencias ? 'rotate-180' : ''"></i>
          </button>

          <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
          >
            <div v-show="menuCompetencias" class="absolute left-0 z-50 pt-5 top-full w-80">
              <div class="p-2 bg-white border border-gray-200 shadow-2xl dark:bg-[#0a0a0a] dark:border-white/10">
                <component
                  :is="item.href.startsWith('#') ? 'a' : Link"
                  v-for="item in enlacesCompetencias"
                  :key="item.href"
                  :href="item.href"
                  class="flex items-start gap-3 p-3 transition-colors group hover:bg-gray-50 dark:hover:bg-white/5"
                  @click="cerrarMenus"
                >
                  <i
                    class="mt-0.5 text-xl text-gray-400 transition-colors ph-fill group-hover:text-neon"
                    :class="item.icon"
                  ></i>
                  <span class="min-w-0">
                    <span class="flex items-center gap-2">
                      <span class="text-xs font-bold tracking-wider text-black uppercase dark:text-white group-hover:text-neon">{{ item.title }}</span>
                      <span v-if="item.nuevo" class="px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-black uppercase bg-neon">
                        {{ t.menu.badgeNew }}
                      </span>
                      <span v-else-if="item.pronto" class="px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase border border-amber-400/50 text-amber-400">
                        {{ t.menu.soon }}
                      </span>
                    </span>
                    <span class="block mt-0.5 font-sans text-[11px] font-normal normal-case tracking-normal text-gray-500 dark:text-gray-400">
                      {{ item.desc }}
                    </span>
                  </span>
                </component>
              </div>
            </div>
          </transition>
        </div>

        <a href="#que-es" class="transition hover:text-neon">{{ t.nav.about }}</a>
        <a href="#pricing" class="transition hover:text-neon">{{ t.nav.pricing }}</a>
        <a href="#partners" class="transition hover:text-neon">{{ t.nav.partners }}</a>
        <a href="#contacto" class="transition hover:text-neon">{{ t.nav.custom }}</a>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 sm:gap-3" data-nav>
        <!-- Idioma + tema, juntos en un solo bloque para no dispersar la barra -->
        <div class="flex items-center overflow-hidden border border-gray-200 rounded-lg dark:border-white/10">
          <button
            :title="t.menu.language"
            :aria-label="t.menu.language"
            class="px-2.5 py-2 text-[11px] font-bold text-gray-500 transition-colors dark:text-gray-400 hover:text-neon"
            @click="toggleLanguage"
          >
            {{ lang.toUpperCase() }}
          </button>
          <span class="w-px h-5 bg-gray-200 dark:bg-white/10"></span>
          <button
            :title="t.menu.theme"
            :aria-label="t.menu.theme"
            class="px-2.5 py-2 text-gray-500 transition-colors dark:text-gray-400 hover:text-neon"
            @click="toggleTheme"
          >
            <i v-if="isDark" class="text-base ph-fill ph-sun"></i>
            <i v-else class="text-base ph-fill ph-moon"></i>
          </button>
        </div>

        <!-- AUTH LINKS (Conservando Lógica Vue/Inertia) -->
        <template v-if="props.canLogin">
          <template v-if="$page.props.auth?.user">
            <Link
              v-if="$page.props.auth.user.email === '18jangel18@gmail.com' || ['admin', 'superadmin'].includes($page.props.auth.user.role || '')"
              :href="route('jangel.indexdos')"
              :title="t.menu.adminFull"
              class="hidden items-center gap-1.5 px-3 py-2 text-xs font-bold tracking-wider text-[var(--rankit-neon)] uppercase border border-[var(--rankit-neon)]/40 hover:bg-[var(--rankit-neon)]/10 transition-colors lg:flex"
            >
              <i class="ph-bold ph-sliders"></i>
              <span>{{ t.menu.admin }}</span>
            </Link>
            <Link
              :href="route('dashboard')"
              class="hidden px-5 py-2 text-xs font-bold tracking-wider uppercase sm:inline-flex btn-skew"
            >
              <span class="btn-content">Lobby</span>
            </Link>
          </template>

          <template v-else>
            <Link
              :href="route('login')"
              class="hidden text-xs font-bold tracking-wider text-gray-600 uppercase sm:block dark:text-gray-300 hover:text-black dark:hover:text-white"
            >
              {{ t.nav.login }}
            </Link>

            <Link
              v-if="props.canRegister"
              :href="route('register')"
              class="hidden px-5 py-2 text-xs font-bold tracking-wider uppercase sm:inline-flex btn-skew"
            >
              <span class="btn-content">{{ t.nav.create }}</span>
            </Link>
          </template>
        </template>

        <!-- Hamburguesa (antes en móvil no había menú: los enlaces simplemente desaparecían) -->
        <button
          type="button"
          class="p-2 -mr-1 text-black transition-colors md:hidden dark:text-white hover:text-neon"
          :aria-label="menuMovil ? t.menu.close : t.menu.open"
          :aria-expanded="menuMovil"
          @click="alternarMenuMovil"
        >
          <i class="text-2xl ph-bold" :class="menuMovil ? 'ph-x' : 'ph-list'"></i>
        </button>
      </div>
    </nav>

    <!-- Panel móvil -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-show="menuMovil"
        data-nav
        class="fixed inset-x-0 z-40 border-b border-gray-200 top-20 md:hidden bg-white/98 dark:bg-[#050505]/98 dark:border-white/10 backdrop-blur-md max-h-[calc(100vh-5rem)] overflow-y-auto"
      >
        <div class="px-6 py-6 space-y-6">
          <div>
            <p class="mb-3 text-[10px] font-bold tracking-[0.25em] text-gray-400 uppercase">{{ t.menu.compete }}</p>
            <div class="space-y-1">
              <component
                :is="item.href.startsWith('#') ? 'a' : Link"
                v-for="item in enlacesCompetencias"
                :key="'movil-' + item.href"
                :href="item.href"
                class="flex items-center gap-3 py-3 border-b border-gray-100 dark:border-white/5"
                @click="cerrarMenus"
              >
                <i class="text-xl text-neon ph-fill" :class="item.icon"></i>
                <span class="flex items-center gap-2 text-sm font-bold tracking-wider text-black uppercase dark:text-white">
                  {{ item.title }}
                  <span v-if="item.nuevo" class="px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-black uppercase bg-neon">
                    {{ t.menu.badgeNew }}
                  </span>
                  <span v-else-if="item.pronto" class="px-1.5 py-0.5 text-[9px] font-bold tracking-wider uppercase border border-amber-400/50 text-amber-400">
                    {{ t.menu.soon }}
                  </span>
                </span>
              </component>
            </div>
          </div>

          <div class="flex flex-col gap-4 text-sm font-bold tracking-widest text-gray-500 uppercase dark:text-gray-400">
            <a href="#que-es" @click="cerrarMenus">{{ t.nav.about }}</a>
            <a href="#pricing" @click="cerrarMenus">{{ t.nav.pricing }}</a>
            <a href="#partners" @click="cerrarMenus">{{ t.nav.partners }}</a>
            <a href="#contacto" @click="cerrarMenus">{{ t.nav.custom }}</a>
          </div>

          <div v-if="props.canLogin" class="flex flex-col gap-3 pt-2 border-t border-gray-200 dark:border-white/10">
            <template v-if="$page.props.auth?.user">
              <Link :href="route('dashboard')" class="px-6 py-3 text-sm font-bold tracking-wider text-center uppercase btn-skew" @click="cerrarMenus">
                <span class="btn-content">Lobby</span>
              </Link>
              <Link
                v-if="$page.props.auth.user.email === '18jangel18@gmail.com' || ['admin', 'superadmin'].includes($page.props.auth.user.role || '')"
                :href="route('jangel.indexdos')"
                class="py-3 text-sm font-bold tracking-wider text-center text-[var(--rankit-neon)] uppercase border border-[var(--rankit-neon)]/40"
                @click="cerrarMenus"
              >
                {{ t.menu.adminFull }}
              </Link>
            </template>
            <template v-else>
              <Link :href="route('login')" class="py-3 text-sm font-bold tracking-wider text-center text-gray-600 uppercase border border-gray-300 dark:border-white/15 dark:text-gray-300" @click="cerrarMenus">
                {{ t.nav.login }}
              </Link>
              <Link v-if="props.canRegister" :href="route('register')" class="px-6 py-3 text-sm font-bold tracking-wider text-center uppercase btn-skew" @click="cerrarMenus">
                <span class="btn-content">{{ t.nav.create }}</span>
              </Link>
            </template>
          </div>
        </div>
      </div>
    </transition>

    <!-- Hero Section -->
    <header class="relative min-h-[90vh] flex items-center pt-20 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:40px_40px]">
      <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-transparent via-gray-50/80 to-gray-50 dark:bg-gradient-to-b dark:from-transparent dark:via-[#050505]/50 dark:to-[#050505]"></div>

      <div class="relative z-10 grid items-center w-full grid-cols-1 gap-16 px-6 mx-auto max-w-7xl lg:grid-cols-2">
        <div>
          <!-- CometaX Badge -->
          <div class="flex items-center gap-2 mb-6 transition-opacity cursor-default opacity-70 hover:opacity-100 group">
            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500">{{ t.hero.powered }}</span>
            <img src="https://raw.githubusercontent.com/JFabrizzio5/CometaX/bbeb654b90e817236d9d64009b33618065fbba91/image_2025-12-16_083018257-removebg-preview%20(1).png" class="w-auto h-6 transition-transform dark:invert group-hover:scale-105" alt="CometaX Logo" />
          </div>

          <div class="flex items-center gap-4 mb-6">
            <svg class="w-16 h-16 text-black dark:text-white shrink-0" viewBox="0 0 100 100" fill="none">
              <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
              <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
              <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
            </svg>
            <div class="inline-flex items-center gap-2 px-3 py-1 text-xs font-bold tracking-widest uppercase border border-neon text-neon bg-neon/5 h-fit">
              <span class="w-2 h-2 rounded-full bg-neon animate-pulse"></span>
              <span>{{ t.hero.badge }}</span>
            </div>
          </div>

          <h1 class="font-display font-black text-6xl md:text-8xl leading-[0.9] mb-6 uppercase text-black dark:text-white">
            <span>{{ t.hero.title1 }}</span><br />
            <span>{{ t.hero.title2 }}</span><br />
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-gray-800 dark:to-white">
              {{ t.hero.title3 }}
            </span>
          </h1>

          <p class="max-w-lg pl-6 mb-8 text-xl font-light text-gray-600 border-l-4 dark:text-gray-400 border-neon">
            {{ t.hero.desc }}
          </p>

          <div class="flex flex-col gap-4 sm:flex-row">
  <!-- Si está logeado: se queda "Organizar Torneo" (como pediste) -->
  <template v-if="$page.props.auth?.user">
    <Link
      :href="route('dashboard')"
      class="px-10 py-4 text-lg font-bold tracking-wider uppercase btn-skew"
    >
      <span class="btn-content">LOBBY</span>
    </Link>
    <Link
      v-if="$page.props.auth.user.email === '18jangel18@gmail.com' || ['admin', 'superadmin'].includes($page.props.auth.user.role || '')"
      :href="route('jangel.indexdos')"
      class="px-10 py-4 text-lg font-bold tracking-wider uppercase btn-skew-ghost"
    >
      <span class="btn-content text-[var(--rankit-neon)]">ADMIN EVENTOS</span>
    </Link>
  </template>

  <!-- Visitante nuevo: un solo camino claro (crear cuenta) + hablar con ventas.
       "Ingresar" vive en la barra, que es donde lo busca quien ya es cliente. -->
  <template v-else>
    <Link
      v-if="props.canRegister"
      :href="route('register')"
      class="px-10 py-4 text-lg font-bold tracking-wider uppercase btn-skew"
    >
      <span class="btn-content">{{ t.compete.ownCta }}</span>
    </Link>

    <button
      class="px-8 py-4 text-lg font-bold tracking-wider text-black uppercase transition border-2 border-gray-300 dark:text-white hover:border-black dark:border-white/20 dark:hover:border-white"
      @click="abrirContratar('Gestor Pro')"
    >
      {{ t.compete.ownCta2 }}
    </button>
  </template>
</div>

        </div>

        <!-- Hero Visuals -->
        <div class="relative hidden lg:block h-[600px]">
          <div class="absolute top-10 right-10 w-72 p-3 z-10 transition duration-500 bg-white border border-gray-200 shadow-xl dark:bg-[#111] dark:border-gray-800 dark:shadow-2xl hover:scale-105">
            <div class="relative h-40 mb-3 overflow-hidden bg-gray-200 dark:bg-gray-800">
              <img src="https://images.unsplash.com/photo-1522770179533-24471fcdba45?q=80&w=800" class="object-cover w-full h-full grayscale opacity-80 mix-blend-multiply dark:mix-blend-normal" alt="Soccer" />
              <div class="absolute top-2 left-2 bg-black text-white text-[10px] font-bold px-2 py-1 uppercase">Fútbol 7</div>
            </div>
            <div class="flex items-end justify-between">
              <div>
                <h3 class="text-lg font-bold text-black uppercase font-display dark:text-white">Night League</h3>
                <p class="text-[10px] text-gray-500 font-mono">FINALES • HOY 20:00</p>
              </div>
              <div class="font-mono text-xl font-bold text-neon">2 - 1</div>
            </div>
          </div>

          <div class="absolute top-40 right-48 w-80 p-3 z-20 border-neon bg-white border shadow-[0_10px_40px_rgba(0,0,0,0.1)] dark:bg-[#111] dark:border dark:shadow-[0_0_50px_rgba(0,0,0,0.8)]">
            <div class="relative h-48 mb-3 overflow-hidden bg-gray-200 dark:bg-gray-800">
              <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=800" class="object-cover w-full h-full opacity-90 dark:opacity-80" alt="Esports" />
              <div class="absolute top-2 right-2 bg-red-600 text-white text-[10px] font-bold px-2 py-1 uppercase animate-pulse">Live</div>
            </div>
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <img src="https://ui-avatars.com/api/?name=T1&background=000&color=fff" class="w-6 h-6 bg-gray-300 rounded dark:bg-gray-800" alt="T1" />
                <span class="text-sm font-bold text-black dark:text-white">T1</span>
              </div>
              <span class="font-mono text-lg font-bold text-neon">13 - 11</span>
              <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-black dark:text-white">G2</span>
                <img src="https://ui-avatars.com/api/?name=G2&background=000&color=fff" class="w-6 h-6 bg-gray-300 rounded dark:bg-gray-800" alt="G2" />
              </div>
            </div>
            <div class="w-full h-1 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-800">
              <div class="w-3/4 h-full bg-neon"></div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Marquee -->
    <div class="relative py-6 overflow-hidden bg-white border-gray-200 select-none border-y dark:bg-black dark:border-white/10">
      <div class="absolute top-0 bottom-0 left-0 z-10 w-24 pointer-events-none bg-gradient-to-r from-white dark:from-black to-transparent"></div>
      <div class="absolute top-0 bottom-0 right-0 z-10 w-24 pointer-events-none bg-gradient-to-l from-white dark:from-black to-transparent"></div>

      <div class="flex w-max animate-marquee items-center opacity-80 uppercase font-bold tracking-[0.2em] text-sm text-gray-500 dark:text-gray-400">
        <div class="flex items-center gap-16 px-8">
          <span>Valorant</span> <span class="text-lg text-neon">•</span>
          <span>Fútbol 7</span> <span class="text-lg text-neon">•</span>
          <span>League of Legends</span> <span class="text-lg text-neon">•</span>
          <span>Padel</span> <span class="text-lg text-neon">•</span>
          <span>Fortnite</span> <span class="text-lg text-neon">•</span>
          <span>Basquetbol</span> <span class="text-lg text-neon">•</span>
          <span>Call of Duty</span> <span class="text-lg text-neon">•</span>
          <span>Tenis</span> <span class="text-lg text-neon">•</span>
          <span>Rocket League</span> <span class="text-lg text-neon">•</span>
          <span>FIFA/EAFC</span> <span class="text-lg text-neon">•</span>
        </div>
        <div class="flex items-center gap-16 px-8">
          <span>Valorant</span> <span class="text-lg text-neon">•</span>
          <span>Fútbol 7</span> <span class="text-lg text-neon">•</span>
          <span>League of Legends</span> <span class="text-lg text-neon">•</span>
          <span>Padel</span> <span class="text-lg text-neon">•</span>
          <span>Fortnite</span> <span class="text-lg text-neon">•</span>
          <span>Basquetbol</span> <span class="text-lg text-neon">•</span>
          <span>Call of Duty</span> <span class="text-lg text-neon">•</span>
          <span>Tenis</span> <span class="text-lg text-neon">•</span>
          <span>Rocket League</span> <span class="text-lg text-neon">•</span>
          <span>FIFA/EAFC</span> <span class="text-lg text-neon">•</span>
        </div>
      </div>
    </div>

    <!-- ======================= QUÉ ES RANKIT ========================== -->
    <section id="que-es" class="py-24 bg-white border-t border-gray-200 dark:bg-[#050505] dark:border-white/10 scroll-mt-24">
      <div class="px-6 mx-auto max-w-7xl">
        <div class="grid gap-14 lg:grid-cols-2 lg:gap-20">
          <!-- Texto -->
          <div>
            <span class="text-neon font-bold tracking-[0.3em] uppercase text-xs mb-4 block">{{ t.about.tag }}</span>
            <h2 class="mb-6 text-4xl font-black leading-tight text-black uppercase font-display md:text-5xl dark:text-white">
              <span>{{ t.about.title }}</span> <span class="text-neon">{{ t.about.titleSub }}</span>
            </h2>
            <p class="pl-6 mb-10 text-lg font-light leading-relaxed text-gray-700 border-l-4 dark:text-gray-300 border-neon">
              {{ t.about.lead }}
            </p>
            <!-- Para quién -->
            <span class="block mb-4 text-[10px] font-bold tracking-[0.25em] text-gray-400 uppercase">{{ t.about.forTag }}</span>
            <div class="flex flex-wrap gap-2 mb-10">
              <span
                v-for="quien in [t.about.for1, t.about.for2, t.about.for3, t.about.for4]"
                :key="quien"
                class="px-3 py-1.5 text-[11px] font-bold tracking-wider text-gray-600 uppercase border border-gray-300 dark:border-white/15 dark:text-gray-300"
              >
                {{ quien }}
              </span>
            </div>

            <!-- Firma CometaX -->
            <div class="flex flex-col gap-3 p-4 border border-gray-200 sm:flex-row sm:items-center dark:border-white/10 bg-gray-50 dark:bg-white/[0.02]">
              <p class="flex-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ t.about.byline }}</p>
              <a
                href="https://cometax.click"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 text-[11px] font-bold tracking-wider uppercase text-neon hover:underline shrink-0"
              >
                {{ t.about.bylineCta }} <i class="ph-bold ph-arrow-up-right"></i>
              </a>
            </div>
          </div>

          <!-- Módulos -->
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 h-fit">
            <div
              v-for="(mod, i) in [
                { icon: 'ph-clipboard-text', title: t.about.f1Title, desc: t.about.f1Desc },
                { icon: 'ph-tree-structure', title: t.about.f2Title, desc: t.about.f2Desc },
                { icon: 'ph-chart-line-up', title: t.about.f3Title, desc: t.about.f3Desc },
                { icon: 'ph-broadcast', title: t.about.f4Title, desc: t.about.f4Desc },
              ]"
              :key="mod.title"
              class="relative p-6 brutal-card"
              :class="i % 2 === 1 ? 'sm:translate-y-6' : ''"
            >
              <i class="mb-4 text-3xl ph-fill text-neon" :class="mod.icon"></i>
              <h3 class="mb-2 text-base font-bold leading-tight text-black uppercase font-display dark:text-white">{{ mod.title }}</h3>
              <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ mod.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ======================== RANKIT LEAGUE ========================== -->
    <!-- ================== COMPETENCIAS RANKIT ========================== -->
    <!-- Antes eran tres secciones (League, Torneos, Semanales) diciendo cosas
         parecidas. Ahora es una sola tarjeta por competencia: menos scroll y un
         solo camino a la acción. -->
    <section id="competencias" class="py-24 border-t border-gray-200 bg-gray-50 dark:bg-[#080808] dark:border-white/10 scroll-mt-24">
      <div class="px-6 mx-auto max-w-7xl">
        <div class="max-w-2xl mb-14">
          <span class="text-neon font-bold tracking-[0.3em] uppercase text-xs mb-3 block">{{ t.compete.tag }}</span>
          <h2 class="mb-4 text-4xl font-black text-black uppercase font-display md:text-5xl dark:text-white">
            <span>{{ t.compete.title }}</span> <span class="text-neon">{{ t.compete.titleSub }}</span>
          </h2>
          <p class="font-light text-gray-500 dark:text-gray-400">{{ t.compete.desc }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <!-- Rankit League -->
          <article class="flex flex-col p-8 brutal-card">
            <i class="mb-5 text-4xl ph-fill ph-trophy text-neon"></i>
            <h3 class="mb-2 text-2xl font-black italic text-black uppercase font-display dark:text-white">
              {{ t.league.title }} {{ t.league.titleSub }}
            </h3>
            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t.league.desc }}</p>
            <ul class="mb-8 space-y-2">
              <li v-for="pt in [t.league.p1, t.league.p2, t.league.p3]" :key="pt" class="flex items-start gap-2 text-xs text-gray-600 dark:text-gray-300">
                <i class="mt-0.5 ph-fill ph-check-circle text-neon shrink-0"></i><span>{{ pt }}</span>
              </li>
            </ul>
            <Link href="/league" class="inline-flex items-center gap-2 mt-auto text-xs font-bold tracking-wider uppercase text-neon hover:underline">
              {{ t.league.cta }} <i class="ph-bold ph-arrow-right"></i>
            </Link>
          </article>

          <!-- Semanales -->
          <article class="flex flex-col p-8 brutal-card border-neon">
            <div class="flex items-start justify-between mb-5">
              <i class="text-4xl ph-fill ph-calendar-star text-neon"></i>
              <span class="px-2 py-1 text-[9px] font-bold tracking-widest text-black uppercase bg-neon">{{ t.semanales.tag }}</span>
            </div>
            <h3 class="mb-2 text-2xl font-black italic text-black uppercase font-display dark:text-white">
              {{ t.semanales.title }} {{ t.semanales.titleSub }}
            </h3>
            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t.semanales.desc }}</p>
            <div class="flex flex-wrap gap-2 mb-8">
              <span class="px-2.5 py-1 text-[10px] font-bold tracking-wider text-black uppercase bg-neon">{{ t.semanales.free }}</span>
              <span class="px-2.5 py-1 text-[10px] font-bold tracking-wider text-gray-600 uppercase border border-gray-300 dark:border-white/15 dark:text-gray-300">{{ t.semanales.dateLabel }}</span>
              <span class="px-2.5 py-1 text-[10px] font-bold tracking-wider text-gray-600 uppercase border border-gray-300 dark:border-white/15 dark:text-gray-300">{{ t.semanales.prize }}</span>
            </div>
            <Link href="/semanales" class="inline-flex items-center gap-2 mt-auto text-xs font-bold tracking-wider uppercase text-neon hover:underline">
              {{ t.semanales.cta }} <i class="ph-bold ph-arrow-right"></i>
            </Link>
          </article>

          <!-- 1v1 / Wagers -->
          <article class="flex flex-col p-8 brutal-card">
            <div class="flex items-start justify-between mb-5">
              <i class="text-4xl ph-fill ph-sword text-neon"></i>
              <span class="px-2 py-1 text-[9px] font-bold tracking-widest uppercase border border-amber-500/50 text-amber-500">{{ t.compete.soonBadge }}</span>
            </div>
            <h3 class="mb-2 text-2xl font-black italic text-black uppercase font-display dark:text-white">{{ t.menu.duels }}</h3>
            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t.pricing.soon1Desc }}</p>
            <ul class="mb-8 space-y-2">
              <li v-for="pt in [t.pricing.soon1, t.pricing.soon2, t.pricing.soon3]" :key="pt" class="flex items-start gap-2 text-xs text-gray-600 dark:text-gray-300">
                <i class="mt-0.5 ph-fill ph-clock text-amber-500 shrink-0"></i><span>{{ pt }}</span>
              </li>
            </ul>
            <Link href="/duels" class="inline-flex items-center gap-2 mt-auto text-xs font-bold tracking-wider uppercase text-neon hover:underline">
              {{ t.pricing.soonCta }} <i class="ph-bold ph-arrow-right"></i>
            </Link>
          </article>
        </div>

        <!-- Cierre: un solo camino a la acción -->
        <div class="flex flex-col items-start gap-6 p-8 mt-8 border border-gray-200 sm:flex-row sm:items-center dark:border-white/10 bg-white dark:bg-black">
          <div class="flex-1">
            <h3 class="mb-2 text-2xl font-black text-black uppercase font-display dark:text-white">{{ t.compete.ownTitle }}</h3>
            <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t.compete.ownDesc }}</p>
          </div>
          <div class="flex flex-col w-full gap-3 sm:flex-row sm:w-auto shrink-0">
            <Link v-if="props.canRegister" :href="route('register')" class="px-7 py-3.5 text-xs font-bold tracking-wider text-center uppercase btn-skew">
              <span class="btn-content">{{ t.compete.ownCta }}</span>
            </Link>
            <button
              class="px-7 py-3.5 text-xs font-bold tracking-wider text-black uppercase transition border border-gray-300 hover:border-black dark:border-white/20 dark:text-white dark:hover:border-white"
              @click="abrirContratar('Gestor Pro')"
            >
              {{ t.compete.ownCta2 }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="relative py-24 overflow-hidden bg-white border-t border-gray-200 dark:bg-black dark:border-white/10">
      <div class="relative z-10 px-6 mx-auto max-w-7xl">
        <div class="mb-20 text-center">
          <h2 class="mb-4 text-5xl font-black text-black uppercase font-display dark:text-white">
            <span>{{ t.pricing.title }}</span> <span class="text-neon">{{ t.pricing.titleSub }}</span>
          </h2>
          <p class="max-w-2xl mx-auto font-light text-gray-500 dark:text-gray-400">{{ t.pricing.desc }}</p>
        </div>

        <div class="grid items-end grid-cols-1 gap-8 md:grid-cols-3">
          <!-- Base -->
          <div class="brutal-card p-8 h-min bg-white dark:bg-[#080808]">
            <h3 class="text-2xl font-bold text-gray-700 uppercase font-display dark:text-gray-300">Base</h3>
            <div class="flex items-baseline mt-4 mb-6">
              <span class="text-4xl font-black text-black dark:text-white">$250</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN / {{ t.pricing.period }}</span>
            </div>
            <button
              class="w-full py-3 mb-8 text-xs font-bold tracking-wider text-black uppercase transition border border-gray-300 hover:border-black dark:border-gray-700 dark:hover:border-white dark:text-white"
              @click="abrirContratar('Base')"
            >
              {{ t.pricing.btnStart }}
            </button>
            <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.tournaments }}</li>
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.brackets }}</li>
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.publicPage }}</li>
              <li class="flex items-start gap-3 text-gray-400 dark:text-gray-500">
                <i class="mt-0.5 ph ph-x-circle"></i> <span>{{ t.pricing.feat.noPriority }}</span>
              </li>
            </ul>
          </div>

          <!-- Pro -->
          <div class="brutal-card p-8 border-neon relative transform md:-translate-y-4 shadow-xl bg-white dark:bg-[#0a0a0a]">
            <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-neon text-black text-[10px] font-bold px-3 py-1 uppercase tracking-widest">
              {{ t.pricing.recommended }}
            </div>
            <h3 class="text-3xl font-bold text-black uppercase font-display dark:text-white">Gestor Pro</h3>
            <div class="flex items-baseline mt-4 mb-6">
              <span class="text-5xl font-black text-black dark:text-white">$800</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN / {{ t.pricing.period }}</span>
            </div>
            <button
              class="w-full bg-neon hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black text-black font-bold py-4 uppercase text-xs tracking-wider transition mb-8 shadow-[0_0_20px_var(--rankit-neon)]"
              @click="abrirContratar('Gestor Pro')"
            >
              {{ t.pricing.btnPro }}
            </button>
            <ul class="space-y-4 text-sm text-gray-800 dark:text-white">
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.unlimited }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.obs }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.payments }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.stats }}</li>
            </ul>
          </div>

          <!-- Enterprise -->
          <div class="brutal-card p-8 h-min bg-white dark:bg-[#080808]">
            <h3 class="text-2xl font-bold text-black uppercase font-display dark:text-white">{{ t.pricing.planEnterprise }}</h3>
            <div class="flex items-baseline mt-4 mb-6">
              <span class="text-4xl font-black text-black dark:text-white">$5,000</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN / {{ t.pricing.period }}</span>
            </div>
            <button
              class="w-full py-3 mb-8 text-xs font-bold tracking-wider text-black uppercase transition border border-gray-300 hover:border-black dark:border-gray-700 dark:hover:border-white dark:text-white"
              @click="abrirContratar(t.pricing.planEnterprise)"
            >
              {{ t.pricing.btnContact }}
            </button>
            <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.multiuser }}</li>
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.whitelabel }}</li>
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.priority }}</li>
            </ul>
          </div>
        </div>

        <!-- Complementos en una sola fila: el ticket se ajusta sin inflar el plan base -->
        <div class="p-6 mt-10 border border-gray-200 sm:p-8 dark:border-white/10 bg-gray-50 dark:bg-white/[0.02]">
          <div class="flex flex-col gap-2 mb-6 sm:flex-row sm:items-baseline sm:gap-4">
            <h3 class="text-xl font-black text-black uppercase font-display dark:text-white">{{ t.pricing.addonsTitle }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ t.pricing.addonsDesc }}</p>
          </div>

          <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div
              v-for="addon in [
                { icon: 'ph-users-three', title: t.pricing.addonStaffTitle, desc: t.pricing.addonStaffDesc },
                { icon: 'ph-headset', title: t.pricing.addonSupportTitle, desc: t.pricing.addonSupportDesc },
                { icon: 'ph-rocket-launch', title: t.pricing.addonSetupTitle, desc: t.pricing.addonSetupDesc },
              ]"
              :key="addon.title"
              class="flex items-start gap-3"
            >
              <i class="mt-0.5 text-2xl ph-fill text-neon shrink-0" :class="addon.icon"></i>
              <div class="min-w-0">
                <h4 class="text-sm font-bold text-black uppercase font-display dark:text-white">{{ addon.title }}</h4>
                <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ addon.desc }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- En desarrollo: una línea, el detalle vive en /duels -->
        <div class="flex flex-col items-start gap-4 p-6 mt-6 border border-gray-200 sm:flex-row sm:items-center dark:border-white/10">
          <span class="inline-flex items-center gap-2 px-2.5 py-1 text-[10px] font-bold tracking-[0.25em] uppercase border border-amber-500/50 text-amber-500 bg-amber-500/10 shrink-0">
            <i class="ph-fill ph-hammer"></i> {{ t.pricing.soonTag }}
          </span>
          <p class="flex-1 text-sm text-gray-600 dark:text-gray-300">
            {{ t.pricing.soon1 }} · {{ t.pricing.soon2 }} · {{ t.pricing.soon3 }}
          </p>
          <Link href="/duels" class="inline-flex items-center gap-2 text-xs font-bold tracking-wider uppercase text-neon hover:underline shrink-0">
            {{ t.pricing.soonCta }} <i class="ph-bold ph-arrow-right"></i>
          </Link>
        </div>
      </div>
    </section>

    <!-- NEW SECTION: Partners & Creators (Rankit Alliance) -->
    <section id="partners" class="py-24 bg-tech-grid-light dark:bg-tech-grid-dark bg-[length:20px_20px] relative border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-[#050505]">
      <div class="absolute inset-0 pointer-events-none bg-gradient-to-t from-white dark:from-black via-transparent to-transparent opacity-80"></div>
      <div class="relative z-10 max-w-6xl px-6 mx-auto">
        <div class="mb-16 text-center">
          <span class="text-neon font-bold tracking-[0.3em] uppercase text-xs mb-3 block">{{ t.alliance.tag }}</span>
          <h2 class="mb-6 text-4xl font-black text-black uppercase font-display md:text-5xl dark:text-white">
            <!-- Ambos spans en la misma línea: el compilador de Vue condensa los saltos de línea y uniría las palabras -->
            <span>{{ t.alliance.title }}</span> <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-purple-600">{{ t.alliance.titleSub }}</span>
          </h2>
          <p class="max-w-2xl mx-auto text-lg text-gray-500 dark:text-gray-400">
            {{ t.alliance.desc }}
          </p>
        </div>

        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
          <!-- Tech Partner Card -->
          <div class="brutal-card p-10 bg-white dark:bg-[#0c0c0c] border-l-4 border-l-blue-500 group">
            <div class="flex items-center justify-center w-12 h-12 mb-6 text-blue-500 rounded-lg bg-blue-500/10">
              <i class="text-2xl ph ph-handshake"></i>
            </div>
            <h3 class="mb-3 text-2xl font-bold text-black uppercase font-display dark:text-white">{{ t.alliance.techTitle }}</h3>
            <p class="h-12 mb-8 text-gray-500 dark:text-gray-400">{{ t.alliance.techDesc }}</p>
<a
  href="https://instagram.com/cometaxcompany"
  target="_blank"
  rel="noopener"
  class="inline-flex items-center gap-2 text-sm font-bold tracking-wider text-blue-500 uppercase transition-colors group-hover:text-blue-400"
>
              <span>{{ t.alliance.techCta }}</span> <i class="ph ph-arrow-right"></i>
            </a>
          </div>

          <!-- Creator Card -->
          <div class="brutal-card p-10 bg-white dark:bg-[#0c0c0c] border-l-4 border-l-pink-500 group">
            <div class="flex items-center justify-center w-12 h-12 mb-6 text-pink-500 rounded-lg bg-pink-500/10">
              <i class="text-2xl ph ph-video-camera"></i>
            </div>
            <h3 class="mb-3 text-2xl font-bold text-black uppercase font-display dark:text-white">{{ t.alliance.creatorTitle }}</h3>
            <p class="h-12 mb-8 text-gray-500 dark:text-gray-400">{{ t.alliance.creatorDesc }}</p>
<a
  href="https://instagram.com/cometaxcompany"
  target="_blank"
  rel="noopener"
  class="inline-flex items-center gap-2 text-sm font-bold tracking-wider text-pink-500 uppercase transition-colors group-hover:text-pink-400"
>

              <span>{{ t.alliance.creatorCta }}</span> <i class="ph ph-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Charity / Karma -->
    <section class="py-20 px-6 border-t border-gray-200 dark:border-white/10 bg-white dark:bg-[#080808]">
      <div class="max-w-4xl mx-auto text-center">
        <div class="w-16 h-16 bg-neon/10 rounded-full flex items-center justify-center mx-auto mb-6 shadow-[0_0_30px_var(--rankit-neon)]">
          <i class="text-3xl ph ph-heart text-neon"></i>
        </div>
        <h2 class="mb-4 text-3xl font-black text-black uppercase font-display dark:text-white">
          <span>{{ t.karma.title }}</span> <span class="text-neon">{{ t.karma.titleSub }}</span>
        </h2>
        <p class="max-w-2xl mx-auto mb-8 text-base leading-relaxed text-gray-600 dark:text-gray-400" v-html="t.karma.descHtml"></p>
        
        <div class="flex flex-wrap items-center justify-center gap-8 transition-all duration-500 opacity-60 grayscale hover:grayscale-0">
          <div class="flex items-center gap-2 font-bold text-gray-500 hover:text-black dark:hover:text-white"><i class="ph ph-plant"></i> GreenFields</div>
          <div class="flex items-center gap-2 font-bold text-gray-500 hover:text-black dark:hover:text-white"><i class="ph ph-graduation-cap"></i> EduSports</div>
          <div class="flex items-center gap-2 font-bold text-gray-500 hover:text-black dark:hover:text-white"><i class="ph ph-trophy"></i> YoungChamps</div>
        </div>
      </div>
    </section>

    <!-- Contact Form -->
    <!-- ====================== CONTACTO (unificado) ====================== -->
    <!-- Antes había dos secciones casi idénticas (Rankit y CometaX). Una sola. -->
    <section id="contacto" class="py-24 border-t border-gray-200 bg-gray-50 dark:bg-[#050505] dark:border-white/10 scroll-mt-24">
      <div class="max-w-5xl px-6 mx-auto">
        <div class="max-w-2xl mb-12">
          <span class="block mb-2 text-xs font-bold tracking-widest uppercase text-neon">{{ t.custom.tag }}</span>
          <h2 class="mb-4 text-4xl font-black text-black uppercase font-display md:text-5xl dark:text-white">
            <span>{{ t.custom.title }}</span> <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-gray-800 dark:to-white">{{ t.custom.titleSub }}</span>
          </h2>
          <p class="text-lg text-gray-500 dark:text-gray-400">{{ t.custom.desc }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <!-- Rankit -->
          <div class="flex flex-col p-8 brutal-card">
            <i class="mb-4 text-3xl ph-fill ph-instagram-logo text-neon"></i>
            <h3 class="mb-2 text-xl font-bold text-black uppercase font-display dark:text-white">{{ t.contact.rankitTitle }}</h3>
            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t.contact.rankitDesc }}</p>
            <a href="https://instagram.com/rankit.pro" target="_blank" rel="noopener" class="w-full py-3.5 mt-auto text-xs font-bold tracking-wider text-center uppercase btn-skew">
              <span class="btn-content">{{ t.contact.rankitCta }}</span>
            </a>
          </div>

          <!-- CometaX -->
          <div class="flex flex-col p-8 brutal-card">
            <i class="mb-4 text-3xl ph-fill ph-buildings text-neon"></i>
            <h3 class="mb-2 text-xl font-bold text-black uppercase font-display dark:text-white">{{ t.contact.cometaxTitle }}</h3>
            <p class="mb-6 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ t.contact.cometaxDesc }}</p>
            <a href="https://cometax.click" target="_blank" rel="noopener" class="w-full py-3.5 mt-auto text-xs font-bold tracking-wider text-center text-black uppercase transition border border-gray-300 hover:border-black dark:border-white/20 dark:text-white dark:hover:border-white">
              {{ t.contact.cometaxCta }}
            </a>
          </div>
        </div>
      </div>
    </section>


    <!-- Footer -->
    <footer class="py-12 transition-colors bg-gray-100 border-t border-gray-200 dark:bg-black dark:border-white/10">
      <div class="flex flex-col items-center justify-between gap-12 px-6 mx-auto max-w-7xl md:flex-row md:gap-6">
        
        <div class="flex items-center gap-4">
          <svg class="w-8 h-8 text-gray-600 dark:text-gray-600" viewBox="0 0 100 100" fill="none">
            <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
            <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
            <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="currentColor" />
          </svg>
          <div class="flex flex-col">
            <span class="text-xl font-bold text-black uppercase font-display dark:text-white">Rankit</span>
            <span class="text-[10px] text-gray-500 dark:text-gray-600 font-mono uppercase tracking-widest">Competitive Platform</span>
          </div>
        </div>
        
        <!-- Enlaces rápidos -->
        <div class="flex flex-wrap items-center justify-center gap-6 text-xs font-bold tracking-widest text-gray-500 uppercase dark:text-gray-500">
          <a href="#competencias" class="transition hover:text-neon">{{ t.nav.tournaments }}</a>
          <Link href="/semanales" class="transition hover:text-neon text-neon">{{ t.nav.weekly }}</Link>
          <a href="#pricing" class="transition hover:text-neon">{{ t.nav.pricing }}</a>
        </div>

        <!-- CometaX Footer Logo -->
        <div class="flex flex-col items-center justify-center gap-2 transition-opacity cursor-pointer group hover:opacity-100">
          <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-gray-600">Powered By</span>
          <div class="flex items-center gap-2">
            <img src="https://raw.githubusercontent.com/JFabrizzio5/CometaX/bbeb654b90e817236d9d64009b33618065fbba91/image_2025-12-16_083018257-removebg-preview%20(1).png" alt="CometaX Logo" class="w-auto h-8 transition-all duration-500 dark:invert group-hover:scale-105 opacity-60 group-hover:opacity-100" />
            <span class="text-lg font-bold tracking-tight text-gray-800 transition-colors dark:text-gray-200 group-hover:text-neon">CometaX</span>
          </div>
        </div>

        <div class="font-mono text-xs tracking-widest text-gray-500 uppercase dark:text-gray-700">
          Laravel v{{ props.laravelVersion }} (PHP v{{ props.phpVersion }})
        </div>
      </div>
    </footer>

    <!-- ===================== MODAL DE CONTRATACIÓN ====================== -->
    <!-- Todavía no hay cobro en línea: aquí explicamos las 3 vías reales. -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div v-if="modalContratar" class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="modalContratar = false"></div>

        <div class="relative w-full max-w-lg bg-white dark:bg-[#0a0a0a] border border-gray-200 dark:border-white/10 shadow-2xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-gray-200 dark:border-white/10">
            <div>
              <span class="text-neon font-bold tracking-[0.3em] uppercase text-[10px] block mb-1">{{ t.hire.subtitle }}</span>
              <h3 class="text-2xl font-black text-black uppercase font-display dark:text-white">{{ t.hire.title }}</h3>
              <p class="mt-1 font-mono text-xs text-gray-500">{{ t.hire.planLabel }}: <span class="text-neon">{{ planElegido }}</span></p>
            </div>
            <button class="text-gray-400 hover:text-black dark:hover:text-white" :aria-label="t.hire.close" @click="modalContratar = false">
              <i class="text-2xl ph-bold ph-x"></i>
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ t.hire.intro }}</p>

            <!-- 1. Instagram (vía preferente) -->
            <a
              :href="IG_RANKIT"
              target="_blank"
              rel="noopener"
              class="flex items-start gap-4 p-4 transition-colors border border-gray-200 dark:border-white/10 hover:border-neon group"
            >
              <span class="flex items-center justify-center w-10 h-10 text-white shrink-0 bg-gradient-to-tr from-amber-500 via-pink-600 to-purple-600">
                <i class="text-xl ph-fill ph-instagram-logo"></i>
              </span>
              <span class="min-w-0">
                <span class="flex items-center gap-2">
                  <span class="text-sm font-bold text-black uppercase dark:text-white group-hover:text-neon">{{ t.hire.step1Title }}</span>
                  <span class="px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-black uppercase bg-neon">1</span>
                </span>
                <span class="block mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ t.hire.step1Desc }}</span>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-bold tracking-wider uppercase text-neon">
                  {{ t.hire.step1Cta }} <i class="ph-bold ph-arrow-up-right"></i>
                </span>
              </span>
            </a>

            <!-- 2. WhatsApp -->
            <a
              :href="enlaceWhatsAppPlan"
              target="_blank"
              rel="noopener"
              class="flex items-start gap-4 p-4 transition-colors border border-gray-200 dark:border-white/10 hover:border-[#25D366] group"
            >
              <span class="flex items-center justify-center w-10 h-10 text-black shrink-0 bg-[#25D366]">
                <i class="text-xl ph-fill ph-whatsapp-logo"></i>
              </span>
              <span class="min-w-0">
                <span class="text-sm font-bold text-black uppercase dark:text-white group-hover:text-[#25D366]">{{ t.hire.step2Title }}</span>
                <span class="block mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ t.hire.step2Desc }}</span>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-bold tracking-wider uppercase text-[#25D366]">
                  {{ t.hire.step2Cta }} <i class="ph-bold ph-arrow-up-right"></i>
                </span>
              </span>
            </a>

            <!-- 3. CometaX -->
            <a
              :href="WEB_COMETAX"
              target="_blank"
              rel="noopener"
              class="flex items-start gap-4 p-4 transition-colors border border-gray-200 dark:border-white/10 hover:border-black dark:hover:border-white group"
            >
              <span class="flex items-center justify-center w-10 h-10 text-white bg-black shrink-0 dark:bg-white dark:text-black">
                <i class="text-xl ph-fill ph-buildings"></i>
              </span>
              <span class="min-w-0">
                <span class="text-sm font-bold text-black uppercase dark:text-white">{{ t.hire.step3Title }}</span>
                <span class="block mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ t.hire.step3Desc }}</span>
                <span class="inline-flex items-center gap-1 mt-2 text-[11px] font-bold tracking-wider text-black uppercase dark:text-white">
                  {{ t.hire.step3Cta }} <i class="ph-bold ph-arrow-up-right"></i>
                </span>
              </span>
            </a>

            <p class="flex items-start gap-2 pt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-500">
              <i class="mt-0.5 ph-fill ph-info text-neon shrink-0"></i>
              <span>{{ t.hire.note }}</span>
            </p>
          </div>
        </div>
      </div>
    </transition>

    <!-- Widget de contacto: WhatsApp directo + chatbot de dudas -->
    <RankitContactWidget :lang="lang" origen="Landing principal" />
  </div>
</template>

<style>
:root {
  --rankit-neon: #bf00ff;
}

/* Tipografías */
.font-display {
  font-family: "Chakra Petch", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Apple Color Emoji", "Segoe UI Emoji";
}
.font-sans {
    font-family: "Archivo", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
}

/* Utilidades del demo */
.text-neon { color: var(--rankit-neon); }
.bg-neon { background-color: var(--rankit-neon); }
.border-neon { border-color: var(--rankit-neon); }

/* Tech Grid */
.bg-tech-grid-dark {
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
}
.bg-tech-grid-light {
  background-image:
    linear-gradient(to right, rgba(0, 0, 0, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(0, 0, 0, 0.05) 1px, transparent 1px);
}
.text-ig { color: var(--rankit-neon); }

/* Brutal card */
.brutal-card {
  position: relative;
  transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.dark .brutal-card {
  background: #0a0a0a;
  border: 1px solid #333;
}
html:not(.dark) .brutal-card {
  background: #ffffff;
  border: 1px solid #e5e5e5;
  box-shadow: 4px 4px 0px #00000010;
}
.brutal-card:hover {
  border-color: var(--rankit-neon);
  transform: translate(-4px, -4px);
}
.dark .brutal-card:hover {
  box-shadow: 6px 6px 0px var(--rankit-neon);
}
html:not(.dark) .brutal-card:hover {
  box-shadow: 6px 6px 0px var(--rankit-neon), 6px 6px 0px 2px black;
}

/* Botones skew */
.btn-skew {
  background-color: var(--rankit-neon);
  color: white;
  transform: skewX(-10deg);
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.btn-skew:hover {
  background-color: white;
  color: black;
  box-shadow: 0 0 15px var(--rankit-neon);
}
html:not(.dark) .btn-skew:hover {
  background-color: black;
  color: white;
  box-shadow: 4px 4px 0px rgba(0,0,0,0.2);
}

/* Botón Skew Outline Disruptivo */
.btn-skew-outline {
  background: transparent;
  color: currentColor;
  border: 1px solid currentColor;
  transform: skewX(-10deg);
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  overflow: hidden;
}
.btn-skew-outline::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: currentColor;
  transition: all 0.3s ease;
  z-index: -1;
  opacity: 0.1;
}
.btn-skew-outline:hover {
  transform: skewX(-10deg) translateY(-2px);
  box-shadow: 4px 4px 0px var(--rankit-neon);
  border-color: var(--rankit-neon);
  color: var(--rankit-neon);
}
.btn-skew-outline:hover::before { left: 0; }
.btn-content { transform: skewX(10deg); }

/* Input brutal */
.brutal-input {
  width: 100%;
  background: transparent;
  border-bottom: 2px solid #333;
  padding: 1rem 0;
  font-family: "Archivo", ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial;
  font-weight: 600;
  outline: none;
  transition: all 0.3s;
}
.dark .brutal-input { color: white; border-color: #333; }
html:not(.dark) .brutal-input { color: black; border-color: #e5e5e5; }
.brutal-input:focus { border-color: var(--rankit-neon); padding-left: 1rem; }

/* Marquee */
@keyframes marquee {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
.animate-marquee { animation: marquee 30s linear infinite; }
</style>