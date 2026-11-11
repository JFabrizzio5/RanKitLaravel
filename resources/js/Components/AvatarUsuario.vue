<script setup lang="ts">
/** Círculo con la inicial del usuario, como el avatar de una cuenta de Google. */
import { computed } from 'vue'
import { colorAvatar, inicial } from '@/Composables/usePlan'

const props = withDefaults(defineProps<{
  nombre?: string | null
  semilla?: string | null
  tamano?: 'sm' | 'md' | 'lg' | 'xl'
}>(), { tamano: 'md' })

const medidas = {
  sm: 'w-8 h-8 text-sm',
  md: 'w-10 h-10 text-base',
  lg: 'w-16 h-16 text-2xl',
  xl: 'w-24 h-24 text-4xl',
}

const fondo = computed(() => colorAvatar(props.semilla ?? props.nombre))
</script>

<template>
  <span
    class="inline-flex items-center justify-center shrink-0 rounded-full font-semibold text-white select-none"
    :class="medidas[tamano]"
    :style="{ backgroundColor: fondo }"
    aria-hidden="true"
  >{{ inicial(nombre) }}</span>
</template>
