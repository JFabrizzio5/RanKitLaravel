<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TarjetaPlan from '@/Components/TarjetaPlan.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import { fechaLarga, usePlan } from '@/Composables/usePlan'

type Plan = {
  clave: 'base' | 'pro'
  nombre: string
  precio: number
  torneos: number | null
  dias: number
  recurrente: boolean
}

const props = defineProps<{
  planes: Plan[]
  pasarelas: ('stripe' | 'mercadopago')[]
  resultado: 'activo' | 'pendiente' | 'fallido' | 'revocado' | null
  cancelado: boolean
  motivo: string | null
  quiero: 'mas' | 'pro' | null
  graciaDias: number
}>()

const page = usePage()
const errorPago = computed(() => (page.props.errors as Record<string, string>)?.pago)
const { plan, suscripcion, cupo, tieneBase } = usePlan()

const enviando = ref<string | null>(null)

function pagar(clave: string, pasarela: string) {
  enviando.value = `${clave}-${pasarela}`
  router.post(route('planes.pagar', { plan: clave, pasarela }), {}, {
    onFinish: () => { enviando.value = null },
  })
}

function reanudar() {
  router.post(route('planes.renovacion.reanudar'), {}, { preserveScroll: true })
}

const dinero = (n: number) => '$' + n.toLocaleString('es-MX')

const aviso = computed(() => {
  if (props.resultado === 'activo') return { tono: 'ok', texto: '¡Listo! Tu plan ya está activo.' }
  if (props.resultado === 'pendiente') return { tono: 'espera', texto: 'Tu pago está pendiente. Si pagaste en OXXO o por SPEI, el plan se activa solo en cuanto se acredite (puede tardar unas horas).' }
  if (props.resultado === 'fallido') return { tono: 'error', texto: 'El pago no se completó. No se hizo ningún cargo; puedes intentarlo otra vez.' }
  if (props.resultado === 'revocado') return { tono: 'error', texto: 'Ese pago fue reembolsado, así que el plan ya no está activo.' }
  if (props.cancelado) return { tono: 'espera', texto: 'Cancelaste el pago. No se hizo ningún cargo.' }
  if (props.motivo === 'sin-cupo' && tieneBase.value && cupo.value.restantes === 0) return { tono: 'espera', texto: 'Ya no tienes tickets de torneo. Recarga para crear más o sube a Gestor Pro para tener torneos ilimitados.' }
  if (props.motivo === 'sin-plan' && !plan.value?.activo) return { tono: 'espera', texto: 'Para crear y administrar torneos necesitas un plan activo.' }
  return null
})

// Qué hace cada botón según el plan que ya tiene.
type Boton = { texto: string; accion: 'pagar' | 'reanudar' | null }

function boton(p: Plan, pasarela: string): Boton {
  const enStripe = pasarela === 'stripe'
  if (p.clave === 'base') {
    if (plan.value?.ilimitado) return { texto: 'Ya tienes torneos ilimitados', accion: null }
    if (tieneBase.value) return { texto: enStripe ? `Recargar ${p.torneos} tickets` : 'Mercado Pago', accion: 'pagar' }
    return { texto: enStripe ? 'Pagar con tarjeta' : 'Mercado Pago', accion: 'pagar' }
  }
  if (suscripcion.value?.renueva) return { texto: 'Es tu plan actual', accion: null }
  if (suscripcion.value) return { texto: 'Reanudar renovación', accion: 'reanudar' }
  if (tieneBase.value) return { texto: enStripe ? 'Subir a Gestor Pro' : 'Mercado Pago', accion: 'pagar' }
  return { texto: enStripe ? 'Suscribirme con tarjeta' : 'Mercado Pago', accion: 'pagar' }
}

function pulsar(p: Plan, pasarela: string) {
  const b = boton(p, pasarela)
  if (b.accion === 'pagar') pagar(p.clave, pasarela)
  if (b.accion === 'reanudar') reanudar()
}

// Nota bajo los botones cuando ya hay un plan y conviene saber qué pasa al comprar.
function nota(p: Plan): string {
  const pl = plan.value
  if (!pl?.activo || pl.exento) return ''
  if (p.clave === 'base' && tieneBase.value) {
    const n = cupo.value.restantes
    const tienes = n === 0 ? 'No te quedan tickets' : `Tienes ${n} ${n === 1 ? 'ticket' : 'tickets'}`
    return `${tienes}; con la recarga suman ${n + (p.torneos ?? 0)}. Los nuevos vencen en ${p.dias} días.`
  }
  if (p.clave === 'pro' && tieneBase.value) {
    return 'Desde hoy tus torneos son ilimitados. Lo que te quedaba del pase Base no se reembolsa ni se descuenta.'
  }
  if (p.clave === 'pro' && pl.ilimitado && !suscripcion.value) {
    return `Tu acceso Pro sigue hasta el ${fechaLarga(pl.vence)}. Si te suscribes ahora, el primer mes empieza hoy.`
  }
  if (p.clave === 'pro' && suscripcion.value && !suscripcion.value.renueva) {
    return `Cancelaste la renovación: el plan termina el ${fechaLarga(suscripcion.value.vence)}. Reanúdala para no perderlo; no se cobra nada hoy.`
  }
  return ''
}

// Desde el menú de la cuenta: resalta y lleva a la tarjeta que pidió.
const resaltada = computed(() => props.quiero === 'pro' ? 'pro' : props.quiero === 'mas' ? 'base' : null)
onMounted(() => {
  if (resaltada.value) document.getElementById(`plan-${resaltada.value}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
})

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
        <TarjetaPlan v-if="plan?.activo || plan?.gracia_hasta" contexto="planes" class="mb-10" />

        <!-- Planes -->
        <div v-if="!plan?.exento" class="grid gap-8 md:grid-cols-2">
          <article v-for="p in planes" :id="`plan-${p.clave}`" :key="p.clave" class="brutal-card relative p-8 bg-white dark:bg-[#0a0a0a]" :class="{ 'border-neon': p.clave === 'pro', 'ring-2 ring-[var(--rankit-neon)] ring-offset-4 ring-offset-gray-50 dark:ring-offset-[#050505]': resaltada === p.clave }">
            <h2 class="text-2xl font-bold uppercase font-display">{{ p.nombre }}</h2>
            <div class="flex items-baseline mt-4">
              <span class="text-5xl font-black">{{ dinero(p.precio) }}</span>
              <span class="ml-2 font-mono text-sm text-gray-500">MXN{{ p.recurrente ? ' / mes' : '' }}</span>
            </div>

            <ul class="mt-6 space-y-3 text-sm text-gray-700 dark:text-gray-300">
              <li class="flex gap-3"><i class="ph ph-check text-neon mt-0.5"></i>
                <span v-if="p.torneos">{{ p.torneos }} tickets de torneo (1 ticket = 1 torneo) para usar en {{ p.dias }} días. Recarga cuando quieras.</span>
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
                :disabled="enviando !== null || boton(p, pasarela).accion === null"
                class="w-full py-3 text-xs font-bold tracking-wider uppercase transition border disabled:opacity-40 disabled:cursor-not-allowed"
                :class="pasarela === 'stripe'
                  ? 'bg-neon text-white border-transparent hover:shadow-[0_0_20px_var(--rankit-neon)]'
                  : 'border-gray-300 dark:border-gray-700 hover:border-black dark:hover:border-white'"
                @click="pulsar(p, pasarela)"
              >
                {{ enviando === `${p.clave}-${pasarela}` ? 'Abriendo pago…' : boton(p, pasarela).texto }}
              </button>
              <button
                v-if="!pasarelas.includes('mercadopago')"
                type="button"
                disabled
                class="w-full py-3 text-xs font-bold tracking-wider uppercase border border-dashed border-gray-300 dark:border-gray-700 text-gray-400 cursor-not-allowed"
              >
                Mercado Pago · Próximamente
              </button>
              <p v-if="nota(p)" class="pt-1 text-xs text-gray-500 dark:text-gray-400">{{ nota(p) }}</p>
            </div>
            <a v-else :href="waVentas" target="_blank" rel="noopener" class="block mt-8 py-3 text-center text-xs font-bold tracking-wider uppercase border border-gray-300 dark:border-gray-700 hover:border-black dark:hover:border-white">
              El pago en línea aún no está activo · escríbenos
            </a>
          </article>
        </div>

        <p v-if="!plan?.exento" class="mt-8 text-xs text-gray-500 dark:text-gray-500 max-w-3xl">
          Cada torneo que creas cuenta para tu plan aunque después lo borres; las fases que genera "clasificar a la siguiente ronda" no cuentan.
          <template v-if="graciaDias > 0">Cada torneo lo puedes seguir administrando {{ graciaDias }} días después de crearlo aunque tu plan venza antes; para crear otro sí necesitas plan vigente.</template>
          <template v-else>Si el plan vence, el panel se cierra.</template>
          Las páginas públicas de tus torneos siguen visibles siempre.
        </p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
