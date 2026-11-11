<script setup lang="ts">
/**
 * Barra de las propuestas. "lateral": enlaces chicos apilados a la izquierda,
 * logo al centro y la acción a la derecha (como la referencia). "barra": una
 * sola línea. En celular las dos abren un menú a pantalla completa con
 * enlaces grandes.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

withDefaults(defineProps<{ variante?: 'lateral' | 'barra'; flotante?: boolean }>(), {
  variante: 'lateral',
  flotante: false,
})

const page = usePage()
const conSesion = computed(() => !!(page.props.auth as { user?: unknown } | undefined)?.user)
const abierto = ref(false)
watch(abierto, (v) => { document.documentElement.style.overflow = v ? 'hidden' : '' })

const enlaces = [
  { texto: 'Inicio', href: '/' },
  { texto: 'Torneos', href: '#eventos' },
  { texto: 'Liga', href: '/league' },
  // /planes pide sesión; los precios para visitantes están en la landing.
  { texto: 'Precios', href: '#pricing' },
]
const accion = computed(() => conSesion.value ? { texto: 'Mi lobby', href: '/dashboard' } : { texto: 'Crear torneo', href: '/register' })
</script>

<template>
  <header class="z-50 w-full" data-intro="barra" :class="flotante ? 'absolute inset-x-0 top-0' : 'relative'">
    <div class="mx-auto flex max-w-[1600px] items-start justify-between px-5 pt-5 sm:px-8 sm:pt-7">
      <!-- Izquierda -->
      <nav v-if="variante === 'lateral'" class="hidden flex-col gap-1.5 md:flex" aria-label="Principal">
        <Link v-for="e in enlaces" :key="e.href" :href="e.href" class="ui enlace-nav text-white/55 hover:text-white">{{ e.texto }}</Link>
      </nav>
      <Link v-else href="/" class="flex items-center gap-2" aria-label="Rankit, inicio">
        <svg class="h-6 w-6" viewBox="0 0 100 100" aria-hidden="true">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--neon)" />
        </svg>
        <span class="titular text-2xl">Rankit</span>
      </Link>

      <!-- Centro -->
      <Link v-if="variante === 'lateral'" href="/" class="flex items-center gap-2 md:absolute md:left-1/2 md:-translate-x-1/2" aria-label="Rankit, inicio">
        <svg class="h-6 w-6" viewBox="0 0 100 100" aria-hidden="true">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--neon)" />
        </svg>
        <span class="titular text-2xl">Rankit</span>
      </Link>
      <nav v-else class="hidden items-center gap-8 md:flex" aria-label="Principal">
        <Link v-for="e in enlaces" :key="e.href" :href="e.href" class="ui enlace-nav text-white/60 hover:text-white">{{ e.texto }}</Link>
      </nav>

      <!-- Derecha -->
      <div class="flex items-center gap-6">
        <Link v-if="!conSesion" href="/login" class="ui hidden text-white/60 hover:text-white md:inline">Entrar</Link>
        <!-- Envuelto: .boton fuerza display:inline-flex y le gana a "hidden". -->
        <span class="hidden md:inline-flex"><Link :href="accion.href" class="boton boton-lleno !px-5 !py-2.5 !text-[12px]"><span>{{ accion.texto }}</span></Link></span>
        <button type="button" class="ui -mr-2 p-2 text-white md:hidden" :aria-expanded="abierto" @click="abierto = true">Menú</button>
      </div>
    </div>

    <!-- Menú móvil a pantalla completa -->
    <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
      <div v-if="abierto" class="fixed inset-0 z-[70] flex flex-col bg-[#0d0d0d] px-5 pb-10 pt-5" role="dialog" aria-modal="true" aria-label="Menú">
        <div class="flex items-center justify-between">
          <span class="titular text-2xl">Rankit</span>
          <button type="button" class="ui -mr-2 p-2" @click="abierto = false">Cerrar</button>
        </div>
        <nav class="mt-12 flex flex-col gap-2" aria-label="Principal">
          <Link v-for="e in enlaces" :key="e.href" :href="e.href" class="titular text-6xl" @click="abierto = false">{{ e.texto }}</Link>
        </nav>
        <div class="mt-auto flex flex-col gap-3">
          <Link :href="accion.href" class="boton boton-lleno w-full" @click="abierto = false"><span>{{ accion.texto }}</span></Link>
          <Link v-if="!conSesion" href="/login" class="ui text-white/60" @click="abierto = false">Entrar</Link>
        </div>
      </div>
    </Transition>
  </header>
</template>
