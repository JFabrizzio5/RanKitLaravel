<script setup lang="ts">
/**
 * Estado del plan de organizador con su cupo y lo que puede hacer el usuario:
 * en el perfil manda a /planes; en /planes cancela o reanuda la renovación.
 */
import { Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import ContadorTickets from '@/Components/ContadorTickets.vue'
import { fechaLarga, usePlan, useRecarga } from '@/Composables/usePlan'

const props = withDefaults(defineProps<{ contexto?: 'perfil' | 'planes' }>(), { contexto: 'perfil' })

const { plan, suscripcion, tieneBase, etiqueta, detalle } = usePlan()
const { recargando, recargar } = useRecarga()

// Cada recarga es un pase Base con su propio vencimiento.
const recargas = computed(() => plan.value?.periodos.filter((p) => p.limite !== null) ?? [])

const textoRecarga = computed(() => recargando.value
  ? 'Abriendo pago…'
  : `Recargar +${plan.value?.recarga?.tickets ?? ''} tickets · $${plan.value?.recarga?.precio ?? ''}`)

function cambiarRenovacion(renovar: boolean) {
  if (!renovar && !confirm('Tu plan sigue activo hasta que termine el mes pagado y ya no se cobra otra vez. ¿Cancelar la renovación?')) return
  router.post(route(renovar ? 'planes.renovacion.reanudar' : 'planes.renovacion.cancelar'), {}, { preserveScroll: true })
}
</script>

<template>
  <section v-if="plan" class="brutal-card p-6 sm:p-8 bg-white dark:bg-[#0a0a0a]">
    <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
      <div class="min-w-0 flex-1">
        <p class="text-xs font-bold tracking-widest uppercase text-gray-500">Tu plan</p>
        <p class="mt-1 text-2xl font-black uppercase font-display">
          {{ etiqueta }}
          <template v-if="plan.activo && !plan.exento">
            <span class="text-neon mx-1.5">·</span>
            <span v-if="plan.ilimitado">torneos ilimitados</span>
            <span v-else>tickets de torneo</span>
          </template>
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ detalle }}</p>

        <!-- Tickets del plan Base -->
        <div v-if="tieneBase" class="mt-4">
          <ContadorTickets />
          <ul v-if="recargas.length > 1" class="mt-3 space-y-0.5 text-xs text-gray-500">
            <li v-for="r in recargas" :key="r.id">
              Recarga del {{ fechaLarga(r.inicio) }}: {{ (r.limite ?? 0) - r.usados }} de {{ r.limite }} sin usar, vence el {{ fechaLarga(r.vence) }}
            </li>
          </ul>
        </div>

        <template v-if="contexto === 'planes' && suscripcion">
          <button v-if="suscripcion.renueva" class="mt-3 text-xs font-bold uppercase tracking-wider text-gray-500 underline hover:text-red-500" @click="cambiarRenovacion(false)">
            Cancelar renovación
          </button>
          <button v-else class="mt-3 text-xs font-bold uppercase tracking-wider text-neon underline" @click="cambiarRenovacion(true)">
            Reanudar renovación
          </button>
        </template>
      </div>

      <!-- Acciones -->
      <div class="flex flex-col gap-2 sm:flex-row md:flex-col md:items-stretch md:min-w-[14rem]">
        <template v-if="contexto === 'perfil'">
          <Link v-if="!plan.activo" :href="route('planes.index')" class="btn-skew px-6 py-3 text-xs font-black tracking-widest uppercase">
            <span class="btn-content">Elegir plan</span>
          </Link>
          <template v-else-if="tieneBase">
            <button type="button" :disabled="recargando" class="btn-skew px-6 py-3 text-xs font-black tracking-widest uppercase disabled:opacity-60" @click="recargar">
              <span class="btn-content">{{ textoRecarga }}</span>
            </button>
            <Link :href="route('planes.index', { quiero: 'pro' })" class="px-6 py-3 text-center text-xs font-bold tracking-widest uppercase border border-gray-300 dark:border-gray-700 hover:border-[var(--rankit-neon)]">
              Subir a Gestor Pro
            </Link>
          </template>
          <Link v-else-if="!plan.exento" :href="route('planes.index')" class="px-6 py-3 text-center text-xs font-bold tracking-widest uppercase border border-gray-300 dark:border-gray-700 hover:border-[var(--rankit-neon)]">
            Administrar plan
          </Link>
        </template>
        <template v-else>
          <button v-if="tieneBase" type="button" :disabled="recargando" class="btn-skew px-6 py-3 text-xs font-black tracking-widest uppercase disabled:opacity-60" @click="recargar">
            <span class="btn-content">{{ textoRecarga }}</span>
          </button>
          <Link v-if="plan.administra && tieneBase" :href="route('game.selector')" class="px-6 py-3 text-center text-xs font-bold tracking-widest uppercase border border-gray-300 dark:border-gray-700 hover:border-[var(--rankit-neon)]">
            Ir al panel
          </Link>
          <Link v-else-if="plan.administra" :href="route('game.selector')" class="btn-skew px-8 py-4 text-sm font-black tracking-widest uppercase">
            <span class="btn-content">{{ plan.activo ? 'Ir al panel' : 'Mi panel' }}</span>
          </Link>
        </template>
      </div>
    </div>
  </section>
</template>
