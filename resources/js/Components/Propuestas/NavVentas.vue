<script setup lang="ts">
/**
 * Barra fija pensada para vender (propuesta K). Trae todo lo que tenía la
 * barra de la landing actual (Competencias con Liga, Semanales, Mundial, En
 * curso y 1v1; Plataforma, Precios, Partners, A la medida; Entrar / Lobby /
 * Admin) y un botón "Contratar" siempre a la vista con las 3 formas de
 * comprar: un torneo (Base), torneos sin límite (Pro) o evento a la medida
 * (WhatsApp de ventas).
 *
 * Sin backdrop-blur: el fondo es sólido para no cargar la tarjeta de video.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps<{ precioBase: number; precioPro: number }>()

type Usuario = { email?: string; role?: string | null }
const page = usePage()
const usuario = computed(() => (page.props.auth as { user?: Usuario } | undefined)?.user ?? null)
const esAdmin = computed(() => !!usuario.value && (usuario.value.email === '18jangel18@gmail.com' || ['admin', 'superadmin'].includes(usuario.value.role ?? '')))

const waVentas = 'https://wa.me/525532351392?text=' + encodeURIComponent('Hola Rankit, quiero cotizar un torneo o evento.')

const competencias = [
  { href: '/league', icon: 'ph-trophy', titulo: 'Rankit League', desc: 'La liga oficial por temporadas', marca: '' },
  { href: '/semanales', icon: 'ph-calendar-star', titulo: 'Semanales', desc: 'Gratis, con premios en metálico', marca: 'Nuevo' },
  { href: '/mundial', icon: 'ph-globe-hemisphere-west', titulo: 'Mundial 2026', desc: 'Campeón, bracket y estadísticas', marca: '' },
  { href: '/torneos', icon: 'ph-list-bullets', titulo: 'Todos los torneos', desc: 'Abiertos, próximos y jugados', marca: '' },
  { href: '/duels', icon: 'ph-sword', titulo: '1v1 y Wagers', desc: 'Retos con bolsa', marca: 'Pronto' },
]
const enlaces = [
  { texto: 'Plataforma', href: '#plataforma' },
  { texto: 'Precios', href: '#pricing' },
  { texto: 'Partners', href: '#partners' },
  { texto: 'A la medida', href: '#contacto' },
]
const ofertas = computed(() => [
  { href: '/contratar/base', externo: false, icon: 'ph-ticket', titulo: 'Un torneo', precio: `$${props.precioBase.toLocaleString('es-MX')}`, nota: 'pago único · 2 torneos en 30 días' },
  { href: '/contratar/pro', externo: false, icon: 'ph-infinity', titulo: 'Torneos sin límite', precio: `$${props.precioPro.toLocaleString('es-MX')}`, nota: 'al mes · para ligas y circuitos' },
  { href: waVentas, externo: true, icon: 'ph-whatsapp-logo', titulo: 'Evento a la medida', precio: 'Cotiza', nota: 'empresas, escuelas y ligas · por WhatsApp' },
])

const menu = ref<'' | 'competir' | 'contratar'>('')
const movil = ref(false)
const solida = ref(false)

const alternar = (m: 'competir' | 'contratar') => { menu.value = menu.value === m ? '' : m }
const cerrar = () => { menu.value = ''; movil.value = false }

const alTocar = (e: PointerEvent) => { if (!(e.target as HTMLElement).closest('[data-menu]')) menu.value = '' }
const alTeclear = (e: KeyboardEvent) => { if (e.key === 'Escape') cerrar() }
const alBajar = () => { solida.value = window.scrollY > 8 }

watch(movil, (v) => { document.documentElement.style.overflow = v ? 'hidden' : '' })
onMounted(() => {
  document.addEventListener('pointerdown', alTocar)
  document.addEventListener('keydown', alTeclear)
  window.addEventListener('scroll', alBajar, { passive: true })
  alBajar()
})
onUnmounted(() => {
  document.removeEventListener('pointerdown', alTocar)
  document.removeEventListener('keydown', alTeclear)
  window.removeEventListener('scroll', alBajar)
  document.documentElement.style.overflow = ''
})
</script>

<template>
  <header class="fixed inset-x-0 top-0 z-50 transition-colors duration-300" :class="solida || menu ? 'border-b border-white/10 bg-[#050505]/95' : 'border-b border-transparent'" data-intro="barra">
    <div class="mx-auto flex h-16 max-w-[1600px] items-center justify-between gap-4 px-5 sm:px-8 lg:h-[72px]">
      <!-- Logo -->
      <Link href="/" class="flex shrink-0 items-center gap-2" aria-label="Rankit, inicio" @click="cerrar">
        <svg class="h-7 w-7" viewBox="0 0 100 100" aria-hidden="true">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--neon)" />
        </svg>
        <span class="titular text-2xl">Rankit</span>
      </Link>

      <!-- Enlaces (escritorio). Los íconos de la barra van en SVG: Phosphor se carga al final. -->
      <nav class="hidden items-center gap-7 lg:flex" aria-label="Principal">
        <div class="relative" data-menu>
          <button type="button" class="ui flex items-center gap-1.5 text-white/70 transition hover:text-white" :class="menu === 'competir' ? '!text-white' : ''" :aria-expanded="menu === 'competir'" aria-haspopup="true" @click="alternar('competir')">
            Competencias
            <span class="h-1.5 w-1.5 rounded-full bg-[var(--neon)]"></span>
            <svg viewBox="0 0 16 16" class="h-2.5 w-2.5 transition-transform" :class="menu === 'competir' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6l5 5 5-5" /></svg>
          </button>
          <div v-show="menu === 'competir'" class="absolute left-0 top-full w-80 pt-4">
            <div class="border border-white/10 bg-[#0b0b0b] p-2 shadow-2xl">
              <component :is="c.href.startsWith('#') ? 'a' : Link" v-for="c in competencias" :key="c.href" :href="c.href" class="group flex items-start gap-3 p-3 transition-colors hover:bg-white/5" @click="cerrar">
                <i class="ph-fill mt-0.5 text-xl text-white/40 transition-colors group-hover:text-[var(--neon)]" :class="c.icon"></i>
                <span>
                  <span class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-white group-hover:text-[var(--neon)]">{{ c.titulo }}</span>
                    <span v-if="c.marca === 'Nuevo'" class="bg-[var(--neon)] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">Nuevo</span>
                    <span v-else-if="c.marca" class="border border-amber-400/50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-400">{{ c.marca }}</span>
                  </span>
                  <span class="mt-0.5 block text-[12px] text-white/50">{{ c.desc }}</span>
                </span>
              </component>
            </div>
          </div>
        </div>
        <a v-for="e in enlaces" :key="e.href" :href="e.href" class="ui enlace-nav text-white/70 hover:text-white">{{ e.texto }}</a>
      </nav>

      <!-- Acciones -->
      <div class="flex items-center gap-3 sm:gap-5">
        <Link v-if="esAdmin" href="/admin/jangel" class="ui hidden items-center gap-1.5 text-[var(--neon)] lg:flex"><i class="ph-bold ph-sliders"></i> Admin</Link>
        <Link v-if="usuario" href="/dashboard" class="ui hidden text-white/70 hover:text-white sm:inline">Mi lobby</Link>
        <Link v-else href="/login" class="ui hidden text-white/70 hover:text-white sm:inline">Entrar</Link>

        <div class="relative" data-menu>
          <button type="button" class="boton boton-lleno !gap-2 !px-4 !py-2.5 !text-[12px] sm:!px-5" :aria-expanded="menu === 'contratar'" aria-haspopup="true" @click="alternar('contratar')">
            <span class="flex items-center gap-2">Contratar <svg viewBox="0 0 16 16" class="h-2.5 w-2.5 transition-transform" :class="menu === 'contratar' ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6l5 5 5-5" /></svg></span>
          </button>
          <div v-show="menu === 'contratar'" class="fixed inset-x-3 top-[68px] sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:w-[380px] sm:pt-4">
            <div class="border border-white/10 bg-[#0b0b0b] p-2 shadow-2xl">
              <p class="ui px-3 pb-1 pt-2 text-white/45">¿Qué necesitas?</p>
              <component :is="o.externo ? 'a' : Link" v-for="o in ofertas" :key="o.titulo" :href="o.href" :target="o.externo ? '_blank' : undefined" :rel="o.externo ? 'noopener' : undefined" class="group flex items-center gap-4 border border-transparent p-3 transition-colors hover:border-[var(--neon)] hover:bg-white/[.03]" @click="cerrar">
                <i class="ph-fill text-2xl text-[var(--neon)]" :class="o.icon"></i>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-bold uppercase tracking-wider">{{ o.titulo }}</span>
                  <span class="block text-[12px] text-white/50">{{ o.nota }}</span>
                </span>
                <span class="titular text-2xl not-italic">{{ o.precio }}</span>
              </component>
              <Link href="/torneos" class="mt-1 flex items-center justify-between border-t border-white/10 px-3 pb-2 pt-3 text-[13px] text-white/60 hover:text-white" @click="cerrar">
                ¿Vas a jugar? Mira todos los torneos <i class="ph-bold ph-arrow-right"></i>
              </Link>
            </div>
          </div>
        </div>

        <button type="button" class="-mr-2 p-2 text-white lg:hidden" :aria-expanded="movil" aria-label="Abrir menú" @click="movil = true; menu = ''">
          <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6.5h16M4 12h16M4 17.5h16" /></svg>
        </button>
      </div>
    </div>

    <!-- Menú en celular y tablet -->
    <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
      <div v-if="movil" class="fixed inset-0 z-[70] flex flex-col overflow-y-auto bg-[#070707] px-5 pb-8 pt-4" role="dialog" aria-modal="true" aria-label="Menú">
        <div class="flex items-center justify-between">
          <span class="titular text-2xl">Rankit</span>
          <button type="button" class="-mr-2 p-2" aria-label="Cerrar menú" @click="movil = false"><svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg></button>
        </div>

        <p class="kicker mt-8">Contratar</p>
        <div class="mt-3 grid gap-2">
          <component :is="o.externo ? 'a' : Link" v-for="o in ofertas" :key="o.titulo" :href="o.href" :target="o.externo ? '_blank' : undefined" :rel="o.externo ? 'noopener' : undefined" class="flex items-center gap-4 border border-white/10 p-4" @click="cerrar">
            <i class="ph-fill text-2xl text-[var(--neon)]" :class="o.icon"></i>
            <span class="min-w-0 flex-1">
              <span class="block font-bold uppercase tracking-wider">{{ o.titulo }}</span>
              <span class="block text-[13px] text-white/50">{{ o.nota }}</span>
            </span>
            <span class="titular text-2xl not-italic">{{ o.precio }}</span>
          </component>
        </div>

        <p class="kicker mt-8">Competencias</p>
        <div class="mt-2 grid">
          <component :is="c.href.startsWith('#') ? 'a' : Link" v-for="c in competencias" :key="c.href" :href="c.href" class="flex items-center gap-3 border-b border-white/5 py-3" @click="cerrar">
            <i class="ph-fill text-xl text-white/40" :class="c.icon"></i>
            <span class="font-semibold">{{ c.titulo }}</span>
            <span v-if="c.marca" class="ml-auto text-[10px] font-bold uppercase tracking-wider" :class="c.marca === 'Nuevo' ? 'text-[var(--neon)]' : 'text-amber-400'">{{ c.marca }}</span>
          </component>
        </div>

        <p class="kicker mt-8">Rankit</p>
        <div class="mt-2 grid">
          <a v-for="e in enlaces" :key="e.href" :href="e.href" class="border-b border-white/5 py-3 font-semibold" @click="cerrar">{{ e.texto }}</a>
        </div>

        <div class="mt-8 flex flex-col gap-3">
          <Link v-if="usuario" href="/dashboard" class="boton boton-linea w-full" @click="cerrar"><span>Mi lobby</span></Link>
          <Link v-else href="/login" class="boton boton-linea w-full" @click="cerrar"><span>Entrar</span></Link>
          <Link v-if="esAdmin" href="/admin/jangel" class="ui text-center text-[var(--neon)]" @click="cerrar">Admin</Link>
        </div>
      </div>
    </Transition>
  </header>
</template>
