<script setup lang="ts">
/**
 * Junto al botón "Crear torneo": cuántos tickets le quedan (plan Base) con un
 * botón para recargar, o por qué el backend rechazó el alta. Los exentos no
 * ven nada.
 */
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fechaLarga, usePlan, useRecarga } from '@/Composables/usePlan'

const props = defineProps<{ error?: string }>()

const { plan, tickets, proximoVence, tieneBase, avisoVence } = usePlan()
const { recargando, recargar } = useRecarga()

const texto = computed(() => {
  const p = plan.value
  if (p && !p.activo && p.gracia_hasta) {
    return `Tu plan venció: administras tus torneos hasta el ${fechaLarga(p.gracia_hasta)}, pero para crear otro necesitas un plan`
  }
  if (!p || p.exento || !p.activo) return ''
  if (p.ilimitado) return `Gestor Pro · torneos ilimitados hasta el ${fechaLarga(p.vence)}`
  const n = tickets.value
  return n === 0
    ? 'Ya no tienes tickets de torneo'
    : `Tienes ${n} ${n === 1 ? 'ticket' : 'tickets'} de torneo (vence${n === 1 ? '' : ' el primero'} el ${fechaLarga(proximoVence.value)})`
})
</script>

<template>
  <div v-if="props.error || texto" class="text-xs">
    <p v-if="props.error" class="px-3 py-2 border-l-2 border-red-500 bg-red-500/10 text-red-600 dark:text-red-300">
      {{ props.error }}
      <button v-if="tieneBase" type="button" :disabled="recargando" class="ml-1 font-bold underline disabled:opacity-60" @click="recargar">
        {{ recargando ? 'Abriendo pago…' : `Recargar +${plan?.recarga?.tickets} tickets` }}
      </button>
      <Link v-else :href="route('planes.index', { motivo: 'sin-cupo' })" class="ml-1 font-bold underline">Ver planes</Link>
    </p>
    <p v-else class="flex flex-wrap items-center gap-x-2 gap-y-1 text-gray-500 dark:text-gray-400">
      <i v-if="tieneBase" class="ph-fill ph-ticket text-base text-[var(--rankit-neon)]"></i>
      <span>{{ texto }}</span>
      <span v-if="avisoVence" class="basis-full flex items-center gap-1 text-amber-600 dark:text-amber-400"><i class="ph-fill ph-warning"></i>{{ avisoVence }}</span>
      <button v-if="tieneBase" type="button" :disabled="recargando" class="font-bold text-[var(--rankit-neon)] underline disabled:opacity-60" @click="recargar">
        {{ recargando ? 'Abriendo pago…' : `Recargar +${plan?.recarga?.tickets} · $${plan?.recarga?.precio}` }}
      </button>
      <Link v-else-if="!plan?.ilimitado" :href="route('planes.index')" class="font-bold text-[var(--rankit-neon)] underline">Ver planes</Link>
    </p>
  </div>
</template>
