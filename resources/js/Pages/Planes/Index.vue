<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

type Plan = {
  clave: 'base' | 'pro'
  nombre: string
  precio: number
  torneos: number | null
  dias: number
  recurrente: boolean
}

type Resumen = {
  exento: boolean
  activo: boolean
  ilimitado: boolean
  plan: 'base' | 'pro' | null
  torneos_restantes: number | null
  vence: string | null
  renueva: boolean
  periodos: { id: number; plan: string; proveedor: string; limite: number | null; usados: number; vence: string; renueva: boolean; suscripcion: string | null }[]
}

const props = defineProps<{
  planes: Plan[]
  pasarelas: ('stripe' | 'mercadopago')[]
  resultado: 'activo' | 'pendiente' | 'fallido' | 'revocado' | null
  cancelado: boolean
  motivo: string | null
}>()

const page = usePage()
const plan = computed(() => page.props.plan as Resumen | null)
const errorPago = computed(() => (page.props.errors as Record<string, string>)?.pago)

// Suscripción de Stripe vigente (la única que se puede cancelar o reanudar).
const suscripcion = computed(() => plan.value?.periodos.find((p) => p.suscripcion) ?? null)

const enviando = ref<string | null>(null)

function pagar(clave: string, pasarela: string) {
  enviando.value = `${clave}-${pasarela}`
  router.post(route('planes.pagar', { plan: clave, pasarela }), {}, {
    onFinish: () => { enviando.value = null },
  })
}

function cambiarRenovacion(renovar: boolean) {
  if (!renovar && !confirm('Tu plan sigue activo hasta que termine el mes pagado y ya no se cobra otra vez. ¿Cancelar la renovación?')) return
  router.post(route(renovar ? 'planes.renovacion.reanudar' : 'planes.renovacion.cancelar'), {}, { preserveScroll: true })
}

function fecha(iso: string | null) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
}

const dinero = (n: number) => '$' + n.toLocaleString('es-MX')

const aviso = computed(() => {
  if (props.resultado === 'activo') return { tono: 'ok', texto: '¡Listo! Tu plan ya está activo.' }
  if (props.resultado === 'pendiente') return { tono: 'espera', texto: 'Tu pago está pendiente. Si pagaste en OXXO o por SPEI, el plan se activa solo en cuanto se acredite (puede tardar unas horas).' }
  if (props.resultado === 'fallido') return { tono: 'error', texto: 'El pago no se completó. No se hizo ningún cargo; puedes intentarlo otra vez.' }
  if (props.resultado === 'revocado') return { tono: 'error', texto: 'Ese pago fue reembolsado, así que el plan ya no está activo.' }
  if (props.cancelado) return { tono: 'espera', texto: 'Cancelaste el pago. No se hizo ningún cargo.' }
  if (props.motivo === 'sin-plan' && !plan.value?.activo) return { tono: 'espera', texto: 'Para crear y administrar torneos necesitas un plan activo.' }
  return null
})

const nombrePasarela: Record<string, string> = {
  stripe: 'Pagar con tarjeta',
  mercadopago: 'Mercado Pago',
}

function etiquetaBoton(p: Plan, pasarela: string) {
  if (p.clave === 'pro' && pasarela === 'stripe') return 'Suscribirme con tarjeta'
  return nombrePasarela[pasarela]
}

const waVentas = 'https://wa.me/525532351392?text=' + encodeURIComponent('Hola Rankit, quiero contratar un plan de organizador.')
</script>

<template>
  <Head title="Planes — Rankit" />

  <AuthenticatedLayout>
    <div class="min-h-[calc(100vh-80px)] bg-gray-50 dark:bg-[#050505] text-gray-900 dark:text-white px-4 sm:px-6 lg:px-16 py-16" style="font-family: 'Archivo', sans-serif;">
      <div class="max-w-5xl mx-auto">
        <header class="mb-10">
          <p class="text-xs font-bold tracking-[0.3em] uppercase text-neon">Organizadores</p>
          <h1 class="mt-2 text-4xl md:text-5xl font-black uppercase italic tracking-tighter font-display">
            Elige tu <span class="text-neon">plan</span>
          </h1>
          <p class="mt-3 max-w-2xl text-gray-500 dark:text-gray-400">
            Con un plan activo entras al panel para crear torneos de Fortnite, LoL/Valorant y fútbol, subir replays y mostrar la tabla en vivo.
          </p>
        </header>

        <!-- Avisos del regreso del pago -->
        <div v-if="aviso" class="mb-6 px-4 py-3 border-l-4 text-sm"
          :class="{
            'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300': aviso.tono === 'ok',
            'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-300': aviso.tono === 'espera',
            'border-red-500 bg-red-500/10 text-red-700 dark:text-red-300': aviso.tono === 'error',
          }">
          {{ aviso.texto }}
        </div>
        <div v-if="errorPago" class="mb-6 px-4 py-3 border-l-4 border-red-500 bg-red-500/10 text-sm text-red-700 dark:text-red-300">
          {{ errorPago }}
        </div>

        <!-- Estado actual -->
        <section v-if="plan?.activo" class="brutal-card p-6 mb-10 bg-white dark:bg-[#0a0a0a]">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
              <p class="text-xs font-bold tracking-widest uppercase text-gray-500">Tu plan</p>
              <p v-if="plan.exento" class="mt-1 text-2xl font-black uppercase font-display">Acceso ilimitado</p>
              <template v-else>
                <p class="mt-1 text-2xl font-black uppercase font-display">
                  {{ plan.ilimitado ? 'Gestor Pro' : 'Base' }}
                  <span class="text-neon">·</span>
                  <span v-if="plan.ilimitado">torneos ilimitados</span>
                  <span v-else>{{ plan.torneos_restantes }} {{ plan.torneos_restantes === 1 ? 'torneo disponible' : 'torneos disponibles' }}</span>
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  <template v-if="plan.renueva">Se renueva el {{ fecha(plan.vence) }}.</template>
                  <template v-else>Activo hasta el {{ fecha(plan.vence) }}.</template>
                </p>
                <button v-if="suscripcion && suscripcion.renueva" class="mt-2 text-xs font-bold uppercase tracking-wider text-gray-500 underline hover:text-red-500" @click="cambiarRenovacion(false)">
                  Cancelar renovación
                </button>
                <button v-else-if="suscripcion" class="mt-2 text-xs font-bold uppercase tracking-wider text-neon underline" @click="cambiarRenovacion(true)">
                  Reanudar renovación
                </button>
              </template>
            </div>
            <Link :href="route('game.selector')" class="btn-skew inline-block px-8 py-4 text-sm font-black tracking-widest text-white uppercase">
              <span class="inline-block" style="transform: skewX(10deg);">Ir al panel</span>
            </Link>
          </div>
        </section>

        <!-- Planes -->
        <div v-if="!plan?.exento" class="grid gap-8 md:grid-cols-2">
          <article v-for="p in planes" :key="p.clave" class="brutal-card relative p-8 bg-white dark:bg-[#0a0a0a]" :class="{ 'border-neon': p.clave === 'pro' }">
            <h2 class="text-2xl font-bold uppercase font-display">{{ p.nombre }}</h2>
            <div class="flex items-baseline mt-4">
              <span class="text-5xl font-black">{{ dinero(p.precio) }}</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN{{ p.recurrente ? ' / mes' : '' }}</span>
            </div>

            <ul class="mt-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
              <li class="flex gap-3"><i class="ph ph-check text-neon mt-0.5"></i>
                <span v-if="p.torneos">{{ p.torneos }} torneos durante {{ p.dias }} días</span>
                <span v-else>Torneos ilimitados</span>
              </li>
              <li class="flex gap-3"><i class="ph ph-check text-neon mt-0.5"></i> Fortnite, LoL/Valorant y fútbol</li>
              <li class="flex gap-3"><i class="ph ph-check text-neon mt-0.5"></i> Replays, tabla en vivo y widgets para OBS</li>
              <li class="flex gap-3 text-gray-500"><i class="ph ph-info mt-0.5"></i>
                <span v-if="p.recurrente">Se cobra con tarjeta cada mes hasta que lo canceles.</span>
                <span v-else>Pago único con tarjeta.</span>
              </li>
            </ul>

            <div v-if="pasarelas.length" class="mt-8 space-y-3">
              <button
                v-for="pasarela in pasarelas"
                :key="pasarela"
                type="button"
                :disabled="enviando !== null || (p.clave === 'base' && plan?.ilimitado) || (p.clave === 'pro' && plan?.renueva)"
                class="w-full py-3 text-xs font-bold tracking-wider uppercase transition border disabled:opacity-40 disabled:cursor-not-allowed"
                :class="pasarela === 'stripe'
                  ? 'bg-neon text-white border-transparent hover:shadow-[0_0_20px_var(--rankit-neon)]'
                  : 'border-gray-300 dark:border-gray-700 hover:border-black dark:hover:border-white'"
                @click="pagar(p.clave, pasarela)"
              >
                {{ enviando === `${p.clave}-${pasarela}` ? 'Abriendo pago…' : etiquetaBoton(p, pasarela) }}
              </button>
              <button
                v-if="!pasarelas.includes('mercadopago')"
                type="button"
                disabled
                class="w-full py-3 text-xs font-bold tracking-wider uppercase border border-dashed border-gray-300 dark:border-gray-700 text-gray-400 cursor-not-allowed"
              >
                Mercado Pago · Próximamente
              </button>
            </div>
            <a v-else :href="waVentas" target="_blank" rel="noopener" class="block mt-8 py-3 text-center text-xs font-bold tracking-wider uppercase border border-gray-300 dark:border-gray-700 hover:border-black dark:hover:border-white">
              El pago en línea aún no está activo · escríbenos
            </a>
          </article>
        </div>

        <p v-if="!plan?.exento" class="mt-8 text-xs text-gray-500 dark:text-gray-500 max-w-3xl">
          Cada torneo que creas cuenta para tu plan aunque después lo borres; las fases que genera "clasificar a la siguiente ronda" no cuentan.
          Si el plan vence, el panel se cierra pero las páginas públicas de tus torneos siguen visibles.
        </p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
