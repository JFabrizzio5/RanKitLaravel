<script setup lang="ts">
/**
 * Contador de tickets de torneo del plan Base: el número grande y una fila de
 * boletos (llenos los disponibles, apagados los usados).
 */
import { computed } from 'vue'
import { usePlan } from '@/Composables/usePlan'

withDefaults(defineProps<{ compacto?: boolean }>(), { compacto: false })

const { cupo, tickets } = usePlan()

// Más de 10 boletos ya no se leen; ahí basta el número.
const boletos = computed(() => cupo.value.total <= 10
  ? Array.from({ length: cupo.value.total }, (_, i) => i < cupo.value.usados ? 'usado' : 'libre')
  : [])
</script>

<template>
  <div class="flex items-center gap-4">
    <div class="flex items-baseline gap-2">
      <i class="ph-fill ph-ticket text-[var(--rankit-neon)]" :class="compacto ? 'text-2xl' : 'text-3xl'"></i>
      <span class="font-black leading-none font-display" :class="[compacto ? 'text-3xl' : 'text-5xl', tickets === 0 ? 'text-red-500' : '']">{{ tickets }}</span>
      <span class="text-sm text-gray-500 dark:text-gray-400">{{ tickets === 1 ? 'ticket' : 'tickets' }}</span>
    </div>
    <div v-if="boletos.length" class="flex flex-wrap gap-1" :aria-label="`${cupo.usados} de ${cupo.total} usados`">
      <i
        v-for="(b, i) in boletos"
        :key="i"
        class="ph-fill ph-ticket"
        :class="[compacto ? 'text-base' : 'text-xl', b === 'libre' ? 'text-[var(--rankit-neon)]' : 'text-gray-300 dark:text-white/15']"
      ></i>
    </div>
  </div>
</template>
