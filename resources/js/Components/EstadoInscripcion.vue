<script setup lang="ts">
/**
 * Chip del estado de una inscripción (payment_status). Un solo lugar para el
 * texto y el color, así /mis-torneos y el dashboard dicen lo mismo.
 */
import { computed } from 'vue'
import type { EstadoInscripcion } from '@/types/inscripciones'

const props = withDefaults(defineProps<{
  estado: EstadoInscripcion
  compacto?: boolean
  // El dashboard pinta sus tarjetas oscuras aunque el tema sea claro.
  oscuro?: boolean
}>(), { compacto: false, oscuro: false })

const ESTADOS = {
  pending: { texto: 'Por confirmar', icono: 'ph-hourglass-medium', color: 'amber' },
  paid: { texto: 'Confirmado', icono: 'ph-check-circle', color: 'emerald' },
  rejected: { texto: 'Rechazado', icono: 'ph-x-circle', color: 'red' },
} as const

// Clases completas (no armadas con el color) para que Tailwind las encuentre.
const TONOS = {
  amber: { auto: 'text-amber-700 dark:text-amber-300', oscuro: 'text-amber-300', base: 'border-amber-500/50 bg-amber-500/15' },
  emerald: { auto: 'text-emerald-700 dark:text-emerald-300', oscuro: 'text-emerald-300', base: 'border-emerald-500/50 bg-emerald-500/15' },
  red: { auto: 'text-red-700 dark:text-red-300', oscuro: 'text-red-300', base: 'border-red-500/50 bg-red-500/15' },
} as const

// Un estado desconocido se muestra como pendiente (igual que en el servidor).
const info = computed(() => ESTADOS[props.estado] ?? ESTADOS.pending)
const clases = computed(() => {
  const tono = TONOS[info.value.color]
  return [tono.base, props.oscuro ? tono.oscuro : tono.auto]
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border font-bold uppercase tracking-wider"
    :class="[clases, compacto ? 'px-3 py-1 text-[11px]' : 'px-4 py-1.5 text-sm']"
  >
    <i class="ph-fill" :class="[info.icono, compacto ? 'text-sm' : 'text-base']"></i>
    {{ info.texto }}
  </span>
</template>
