<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

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
      nav: { tournaments: 'Torneos', pricing: 'Precios', custom: 'Personalizado', partners: 'Partners', weekly: 'Semanales', login: 'Ingresar', create: 'Crear Cuenta', dashboard: 'Dashboard' },
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
      semanales: {
        tag: 'Nuevo · Eventos promocionales',
        title: 'Torneos',
        titleSub: 'Semanales',
        desc: 'Cada semana lanzamos torneos promocionales con entrada gratuita y premios en metálico. Sin cuota, sin excusas.',
        dateLabel: 'PRÓXIMAMENTE',
        free: 'ENTRADA GRATIS',
        prize: 'PREMIOS EN METÁLICO SEMANALES',
        requirements: 'Discord + WhatsApp obligatorios',
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
          unlimited: 'Torneos Ilimitados',
          obs: 'OBS Widgets (Streaming)',
          payments: 'Pagos integrados',
          multiuser: 'Multiusuario',
          whitelabel: 'Marca Blanca',
        },
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
      nav: { tournaments: 'Tournaments', pricing: 'Pricing', custom: 'Custom', partners: 'Partners', weekly: 'Weeklies', login: 'Login', create: 'Sign Up', dashboard: 'Dashboard' },
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
      semanales: {
        tag: 'New · Promo events',
        title: 'Weekly',
        titleSub: 'Tournaments',
        desc: 'Every week we launch promotional tournaments with free entry and cash prizes. No fee, no excuses.',
        dateLabel: 'COMING SOON',
        free: 'FREE ENTRY',
        prize: 'WEEKLY CASH PRIZES',
        requirements: 'Discord + WhatsApp required',
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
          unlimited: 'Unlimited Tournaments',
          obs: 'OBS Widgets (Streaming)',
          payments: 'Integrated Payments',
          multiuser: 'Multi-user Access',
          whitelabel: 'White Label',
        },
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

      <!-- Links Desktop -->
      <div class="hidden gap-8 text-sm font-bold tracking-widest text-gray-500 uppercase md:flex dark:text-gray-400">
        <a href="#torneos" class="transition hover:text-neon">{{ t.nav.tournaments }}</a>
        <a href="#pricing" class="text-black transition hover:text-neon dark:text-white">{{ t.nav.pricing }}</a>
        <a href="#contacto" class="transition hover:text-neon">{{ t.nav.custom }}</a>
        <a href="#partners" class="transition hover:text-neon text-neon">{{ t.nav.partners }}</a>
        <Link href="/league" class="transition hover:text-neon">League</Link>
        <Link href="/mundial" class="transition hover:text-neon">Mundial</Link>
        <!-- Semanales: eventos promocionales gratuitos (destacado con acento neón) -->
        <Link href="/semanales" class="flex items-center gap-1.5 transition hover:text-neon text-neon">
          <span class="w-1.5 h-1.5 rounded-full bg-neon animate-pulse"></span>
          <span>{{ t.nav.weekly }}</span>
        </Link>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-4">
        <!-- Language -->
        <button @click="toggleLanguage" class="flex items-center gap-1 px-2 py-1 text-xs font-bold transition-colors border border-gray-300 rounded dark:border-gray-700 hover:border-neon">
          <i class="text-lg ph ph-translate"></i>
          <span>{{ lang.toUpperCase() }}</span>
        </button>

        <!-- Theme -->
        <button @click="toggleTheme" class="p-2 mr-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
          <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
          <i v-else class="text-xl ph-fill ph-moon"></i>
        </button>

        <!-- AUTH LINKS (Conservando Lógica Vue/Inertia) -->
        <template v-if="props.canLogin">
          <template v-if="$page.props.auth?.user">
            <Link
              :href="route('dashboard')"
              class="hidden mr-4 text-sm font-bold tracking-wider text-gray-600 uppercase sm:block dark:text-gray-300 hover:text-black dark:hover:text-white"
            >
              LOBBY
            </Link>
            <Link
              v-if="$page.props.auth.user.email === '18jangel18@gmail.com' || ['admin', 'superadmin'].includes($page.props.auth.user.role || '')"
              :href="route('jangel.indexdos')"
              class="hidden mr-4 text-sm font-bold tracking-wider text-[var(--rankit-neon)] uppercase sm:block hover:text-white"
            >
              ADMIN EVENTOS
            </Link>
          </template>

          <template v-else>
            <Link
              :href="route('login')"
              class="hidden mr-4 text-sm font-bold tracking-wider text-gray-600 uppercase sm:block dark:text-gray-300 hover:text-black dark:hover:text-white"
            >
              {{ t.nav.login }}
            </Link>

            <Link
              v-if="props.canRegister"
              :href="route('register')"
              class="px-6 py-2 text-sm font-bold tracking-wider uppercase btn-skew"
            >
              <span class="btn-content">{{ t.nav.create }}</span>
            </Link>
          </template>
        </template>
      </div>
    </nav>

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

  <!-- Si NO está logeado: Ingresar + Crear cuenta -->
  <template v-else>
    <Link
      :href="route('login')"
      class="px-10 py-4 text-lg font-bold tracking-wider uppercase btn-skew"
    >
      <span class="btn-content">{{ t.nav.login }}</span>
    </Link>

    <Link
      v-if="props.canRegister"
      :href="route('register')"
      class="px-8 py-4 text-lg font-bold tracking-wider uppercase btn-skew-outline"
    >
      <span class="btn-content">{{ t.nav.create }}</span>
    </Link>
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

    <!-- Active Tournaments -->
    <section id="torneos" class="py-24 bg-gray-50 dark:bg-[#080808]">
      <div class="px-6 mx-auto max-w-7xl">
        <div class="flex items-end justify-between mb-12">
          <div>
            <h2 class="mb-2 text-4xl font-black text-black uppercase font-display dark:text-white">
              <span>{{ t.tournaments.title }}</span> <span class="text-neon">{{ t.tournaments.titleSub }}</span>
            </h2>
            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ t.tournaments.desc }}</p>
          </div>
          <a href="#" class="items-center hidden gap-2 text-sm font-bold tracking-wider text-black uppercase transition md:flex hover:text-neon dark:text-white">
            <span>{{ t.tournaments.viewAll }}</span> <i class="ph ph-arrow-right"></i>
          </a>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
          <div class="cursor-pointer brutal-card group">
            <div class="aspect-[4/3] relative overflow-hidden border-b border-gray-200 dark:border-gray-800">
              <img src="public/BellzCup.png" class="object-cover w-full h-full transition duration-500 opacity-90 dark:opacity-60 group-hover:opacity-100 group-hover:scale-110" alt="Valorant" />
              <div class="absolute top-0 left-0 px-3 py-1 text-xs font-bold text-black uppercase bg-neon">Esports</div>
            </div>
            <div class="p-6">
              <h3 class="mb-1 text-xl font-bold text-black uppercase transition font-display dark:text-white group-hover:text-neon">BellzCup</h3>
              <p class="mb-4 font-mono text-xs text-gray-500">Battle Royale • Crossplay • $2,400.00 MXN en premios</p>
            </div>
          </div>

          <div class="cursor-pointer brutal-card group">
            <div class="aspect-[4/3] relative overflow-hidden border-b border-gray-200 dark:border-gray-800">
              <img src="https://images.unsplash.com/photo-1579952363873-27f3bade9f55?q=80&w=800" class="object-cover w-full h-full transition duration-500 opacity-90 dark:opacity-60 group-hover:opacity-100 group-hover:scale-110" alt="Futbol" />
              <div class="absolute top-0 left-0 px-3 py-1 text-xs font-bold text-white uppercase bg-black">Deportes</div>
            </div>
            <div class="p-6">
              <h3 class="mb-1 text-xl font-bold text-black uppercase transition font-display dark:text-white group-hover:text-neon">Copa Nocturna</h3>
              <p class="mb-4 font-mono text-xs text-gray-500">Fut 7 • Cancha Sur • Trofeo</p>
            </div>
          </div>

          <div class="cursor-pointer brutal-card group">
            <div class="aspect-[4/3] relative overflow-hidden border-b border-gray-200 dark:border-gray-800">
              <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=800" class="object-cover w-full h-full transition duration-500 opacity-90 dark:opacity-60 group-hover:opacity-100 group-hover:scale-110" alt="League" />
              <div class="absolute top-0 left-0 px-3 py-1 text-xs font-bold text-black uppercase bg-neon">Esports</div>
            </div>
            <div class="p-6">
              <h3 class="mb-1 text-xl font-bold text-black uppercase transition font-display dark:text-white group-hover:text-neon">League Weekly</h3>
              <p class="mb-4 font-mono text-xs text-gray-500">5v5 • Grieta • RP Prizes</p>
            </div>
          </div>

          <div class="flex flex-col items-center justify-center p-6 text-center bg-transparent border-gray-300 border-dashed cursor-pointer brutal-card group hover:border-solid dark:border-gray-800">
            <div class="flex items-center justify-center w-16 h-16 mb-4 text-gray-500 transition bg-gray-200 rounded-full group-hover:bg-neon group-hover:text-black dark:bg-gray-900 dark:text-gray-500">
              <i class="text-2xl ph ph-plus"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-500 uppercase font-display dark:text-gray-400 group-hover:text-black dark:group-hover:text-white">{{ t.tournaments.cardCreate }}</h3>
          </div>
        </div>
      </div>
    </section>

    <!-- Semanales: eventos promocionales gratuitos, independientes de Rankit League -->
    <section
      id="semanales"
      class="relative py-24 overflow-hidden border-y-2 border-neon bg-gradient-to-b from-white via-[var(--rankit-neon)]/5 to-white dark:from-[#0a0014] dark:via-[#12001f] dark:to-[#050505]"
    >
      <!-- Halo neón decorativo -->
      <div class="absolute inset-x-0 top-0 h-px pointer-events-none bg-gradient-to-r from-transparent via-[var(--rankit-neon)] to-transparent"></div>
      <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[42rem] h-48 rounded-full pointer-events-none bg-[var(--rankit-neon)]/20 blur-3xl"></div>

      <div class="relative z-10 px-6 mx-auto max-w-7xl">
        <!-- Encabezado -->
        <div class="mb-14 text-center">
          <span class="inline-flex items-center gap-2 px-3 py-1 mb-5 text-[10px] font-bold uppercase tracking-[0.25em] border border-neon text-neon bg-neon/10">
            <span class="w-2 h-2 rounded-full bg-neon animate-pulse"></span>
            {{ t.semanales.tag }}
          </span>

          <h2 class="mb-4 text-4xl font-black text-black uppercase font-display md:text-6xl dark:text-white">
            <!-- Ambos spans en la misma línea: el compilador de Vue condensa los saltos de línea y uniría las palabras -->
            <span>{{ t.semanales.title }}</span> <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-purple-600 dark:to-white">{{ t.semanales.titleSub }}</span>
          </h2>

          <p class="max-w-2xl mx-auto text-lg text-gray-600 dark:text-gray-400">{{ t.semanales.desc }}</p>

          <!-- Resumen rápido -->
          <div class="flex flex-wrap items-center justify-center gap-3 mt-8">
            <span class="px-4 py-2 text-xs font-bold tracking-widest text-black uppercase bg-neon">{{ t.semanales.free }}</span>
            <span class="flex items-center gap-2 px-4 py-2 text-xs font-bold tracking-widest text-gray-800 uppercase bg-white border border-gray-300 dark:bg-white/5 dark:border-white/15 dark:text-gray-200">
              <i class="ph-fill ph-money text-neon"></i> {{ t.semanales.prize }}
            </span>
          </div>
        </div>

        <!-- Tarjetas: Semanal 1 y Semanal 2 -->
        <div class="grid grid-cols-1 gap-8 md:grid-cols-2">
          <div class="brutal-card p-8 bg-white dark:bg-[#0c0018] border-l-4 border-l-[var(--rankit-neon)] group">
            <div class="flex flex-wrap items-center gap-2 mb-6">
              <span class="flex items-center gap-2 px-3 py-1 text-[10px] font-bold uppercase tracking-widest border border-neon text-neon bg-neon/10">
                <i class="ph ph-calendar-blank"></i> {{ t.semanales.dateLabel }}
              </span>
              <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-black bg-neon">{{ t.semanales.free }}</span>
            </div>

            <h3 class="mb-4 text-3xl font-black text-black uppercase transition font-display dark:text-white group-hover:text-neon">
              {{ t.semanales.card1 }}
            </h3>

            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
              <li class="flex items-center gap-3"><i class="text-lg ph-fill ph-money text-neon"></i> {{ t.semanales.prize }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-discord-logo text-neon"></i> {{ t.semanales.requirements }}</li>
            </ul>
          </div>

          <div class="brutal-card p-8 bg-white dark:bg-[#0c0018] border-l-4 border-l-[var(--rankit-neon)] group">
            <div class="flex flex-wrap items-center gap-2 mb-6">
              <span class="flex items-center gap-2 px-3 py-1 text-[10px] font-bold uppercase tracking-widest border border-neon text-neon bg-neon/10">
                <i class="ph ph-calendar-blank"></i> {{ t.semanales.dateLabel }}
              </span>
              <span class="px-3 py-1 text-[10px] font-bold uppercase tracking-widest text-black bg-neon">{{ t.semanales.free }}</span>
            </div>

            <h3 class="mb-4 text-3xl font-black text-black uppercase transition font-display dark:text-white group-hover:text-neon">
              {{ t.semanales.card2 }}
            </h3>

            <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
              <li class="flex items-center gap-3"><i class="text-lg ph-fill ph-money text-neon"></i> {{ t.semanales.prize }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-discord-logo text-neon"></i> {{ t.semanales.requirements }}</li>
            </ul>
          </div>
        </div>

        <!-- CTA -->
        <div class="flex justify-center mt-12">
          <Link href="/semanales" class="px-10 py-4 text-lg font-bold tracking-wider uppercase btn-skew">
            <span class="btn-content">{{ t.semanales.cta }}</span>
          </Link>
        </div>

        <!-- Aviso: independientes de Rankit League -->
        <p class="flex items-start max-w-3xl gap-3 p-4 mx-auto mt-10 font-mono text-xs leading-relaxed text-gray-600 bg-white border border-gray-300 dark:text-gray-400 dark:bg-white/5 dark:border-white/15">
          <i class="text-base ph ph-info text-neon shrink-0"></i>
          <span>{{ t.semanales.note }}</span>
        </p>
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
              <span class="text-4xl font-black text-black dark:text-white">$100</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN / {{ t.pricing.period }}</span>
            </div>
            <button class="w-full py-3 mb-8 text-xs font-bold tracking-wider text-black uppercase transition border border-gray-300 hover:border-black dark:border-gray-700 dark:hover:border-white dark:text-white">
              {{ t.pricing.btnStart }}
            </button>
            <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.tournaments }}</li>
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.brackets }}</li>
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
            <button class="w-full bg-neon hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black text-black font-bold py-4 uppercase text-xs tracking-wider transition mb-8 shadow-[0_0_20px_var(--rankit-neon)]">
              {{ t.pricing.btnPro }}
            </button>
            <ul class="space-y-4 text-sm text-gray-800 dark:text-white">
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.unlimited }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.obs }}</li>
              <li class="flex items-center gap-3"><i class="text-lg ph ph-check-circle text-neon"></i> {{ t.pricing.feat.payments }}</li>
            </ul>
          </div>

          <!-- Enterprise -->
          <div class="brutal-card p-8 h-min bg-white dark:bg-[#080808]">
            <h3 class="text-2xl font-bold text-black uppercase font-display dark:text-white">{{ t.pricing.planEnterprise }}</h3>
            <div class="flex items-baseline mt-4 mb-6">
              <span class="text-4xl font-black text-black dark:text-white">$5,000</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN / {{ t.pricing.period }}</span>
            </div>
            <button class="w-full py-3 mb-8 text-xs font-bold tracking-wider text-black uppercase transition border border-gray-300 hover:border-black dark:border-gray-700 dark:hover:border-white dark:text-white">
              {{ t.pricing.btnContact }}
            </button>
            <ul class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.multiuser }}</li>
              <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.pricing.feat.whitelabel }}</li>
            </ul>
          </div>
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
    <section id="contacto" class="py-24 bg-gray-50 dark:bg-[#050505] border-t border-gray-200 dark:border-white/10">
  <div class="grid items-center max-w-5xl grid-cols-1 gap-16 px-6 mx-auto md:grid-cols-2">
    <div>
      <span class="block mb-2 text-xs font-bold tracking-widest uppercase text-neon">{{ t.custom.tag }}</span>
      <h2 class="mb-6 text-4xl font-black text-black uppercase font-display md:text-5xl dark:text-white">
        <!-- Ambos spans en la misma línea: el compilador de Vue condensa los saltos de línea y uniría las palabras -->
        <span>{{ t.custom.title }}</span> <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-gray-800 dark:to-white">{{ t.custom.titleSub }}</span>
      </h2>
      <p class="mb-8 text-lg text-gray-500 dark:text-gray-400">{{ t.custom.desc }}</p>
      <ul class="mb-8 space-y-4 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.custom.feat1 }}</li>
        <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.custom.feat2 }}</li>
        <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> {{ t.custom.feat3 }}</li>
      </ul>
    </div>

    <!-- Instagram CTA -->
    <div class="brutal-card p-10 bg-white dark:bg-[#0a0a0a] flex flex-col items-center justify-center text-center">
      <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 shadow-[0_0_30px_var(--rankit-neon)] bg-neon/10">
<img
    src="https://cdn-icons-png.flaticon.com/512/1400/1400487.png"
    alt="Instagram"
    class="object-contain w-8 h-8"
  />      </div>

      <h3 class="mb-3 text-2xl font-bold text-black uppercase font-display dark:text-white">
        Instagram
      </h3>
      <p class="mb-8 text-gray-500 dark:text-gray-400">
        Escríbenos por DM y te armamos una demo o una propuesta a medida.
      </p>

      <!-- Cambia el href por tu IG real -->
      <a
        href="https://instagram.com/rankit.pro"
        target="_blank"
        rel="noopener"
        class="w-full py-4 text-sm font-bold tracking-wider uppercase btn-skew"
      >
        <span class="btn-content">Abrir Instagram</span>
      </a>
    </div>
  </div>
</section>
<section id="software-medida" class="py-24 bg-gray-50 dark:bg-[#050505] border-t border-gray-200 dark:border-white/10">
  <div class="grid items-center max-w-5xl grid-cols-1 gap-16 px-6 mx-auto md:grid-cols-2">

    <!-- Instagram CTA (ahora del lado izquierdo) -->
    <div class="brutal-card p-10 bg-white dark:bg-[#0a0a0a] flex flex-col items-center justify-center text-center">
      <div class="w-16 h-16 rounded-full flex items-center justify-center mb-6 shadow-[0_0_30px_var(--rankit-neon)] bg-neon/10">
        <img
    src="https://cdn-icons-png.flaticon.com/512/1400/1400487.png"
          alt="Instagram"
          class="object-contain w-8 h-8"
        />
      </div>

      <h3 class="mb-3 text-2xl font-bold text-black uppercase font-display dark:text-white">
        CometaX Company
      </h3>
      <p class="mb-8 text-gray-500 dark:text-gray-400">
        Escríbenos por DM para cotizar software a medida.
      </p>

      <a
        href="https://instagram.com/cometaxcompany"
        target="_blank"
        rel="noopener"
        class="w-full py-4 text-sm font-bold tracking-wider uppercase btn-skew"
      >
        <span class="btn-content">Abrir Instagram</span>
      </a>
    </div>

    <!-- Texto (ahora del lado derecho) -->
    <div>
      <span class="block mb-2 text-xs font-bold tracking-widest uppercase text-neon">Software</span>
      <h2 class="mb-6 text-4xl font-black text-black uppercase font-display md:text-5xl dark:text-white">
        <span>¿Necesitas software</span>
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-[var(--rankit-neon)] to-gray-800 dark:to-white">
          a medida?
        </span>
      </h2>

      <p class="mb-8 text-lg text-gray-500 dark:text-gray-400">
        Si tu organización necesita un sistema personalizado, integraciones especiales o una plataforma completa, lo construimos contigo.
      </p>

      <ul class="mb-8 space-y-4 text-sm text-gray-600 dark:text-gray-300">
        <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> Integraciones y automatizaciones</li>
        <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> Dashboard + roles + permisos</li>
        <li class="flex items-center gap-3"><i class="ph ph-check text-neon"></i> Deploy, soporte y escalamiento</li>
      </ul>
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
          <a href="#torneos" class="transition hover:text-neon">{{ t.nav.tournaments }}</a>
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