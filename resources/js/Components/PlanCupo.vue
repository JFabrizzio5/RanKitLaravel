<script setup lang="ts">
/**
 * Cupo del plan de organizador junto al botón "Crear torneo": cuántos torneos
 * le quedan y, si el backend rechazó el alta, por qué. Los exentos no ven nada.
 */
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{ error?: string }>()

const plan = computed(() => usePage().props.plan as {
  exento: boolean
  activo: boolean
  ilimitado: boolean
  torneos_restantes: number | null
  vence: string | null
} | null)

const vence = computed(() => plan.value?.vence
  ? new Date(plan.value.vence).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
  : '')

const texto = computed(() => {
  const p = plan.value
  if (!p || p.exento || !p.activo) return ''
  if (p.ilimitado) return `Gestor Pro · torneos ilimitados hasta el ${vence.value}`
  const n = p.torneos_restantes ?? 0
  return n === 0
    ? 'Ya usaste los torneos de tu plan'
    : `Te ${n === 1 ? 'queda 1 torneo' : `quedan ${n} torneos`} en tu plan (vence el ${vence.value})`
})
</script>

<template>
  <div v-if="props.error || texto" class="text-xs">
    <p v-if="props.error" class="px-3 py-2 border-l-2 border-red-500 bg-red-500/10 text-red-600 dark:text-red-300">
      {{ props.error }}
      <Link :href="route('planes.index')" class="ml-1 font-bold underline">Ver planes</Link>
    </p>
    <p v-else class="text-gray-500 dark:text-gray-400">
      {{ texto }}
      <Link v-if="!plan?.ilimitado" :href="route('planes.index')" class="ml-1 font-bold text-[var(--rankit-neon)] underline">Ver planes</Link>
    </p>
  </div>
</template>
