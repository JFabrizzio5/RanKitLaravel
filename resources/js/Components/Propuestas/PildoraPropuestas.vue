<script setup lang="ts">
/**
 * Solo demo: brincar entre propuestas y volver a ver la intro.
 * Va plegada en un botón chico (abierta tapaba textos de las secciones).
 */
import { Link } from '@inertiajs/vue3'
import { ref } from 'vue'

export type Letra = 'a' | 'b' | 'c' | 'd' | 'e' | 'f' | 'g' | 'h' | 'i' | 'k'
defineProps<{ actual: Letra }>()
const propuestas: Letra[] = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'k']
const abierta = ref(false)

function verIntro() {
  try { sessionStorage.removeItem('rk-intro') } catch { /* sin storage */ }
  window.location.reload()
}
</script>

<template>
  <!-- Solo demo: cambiar de propuesta -->
  <div style="font-family: Archivo, sans-serif; --neon: #bf00ff" class="fixed bottom-4 left-3 z-[60] flex items-center gap-0.5 rounded-full border border-white/15 bg-black/85 p-1 text-[11px] font-bold uppercase tracking-widest text-white sm:left-4 sm:gap-1">
    <button
      v-if="!abierta" type="button"
      class="flex h-8 items-center gap-1.5 rounded-full px-3 hover:bg-white/10"
      title="Cambiar de propuesta" @click="abierta = true"
    >
      <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[var(--neon)]">{{ actual }}</span>
      <span class="text-white/60">Propuestas</span>
    </button>
    <template v-else>
      <Link
        v-for="v in propuestas"
        :key="v"
        :href="`/propuestas/landing/${v}`"
        class="flex h-6 w-6 items-center justify-center rounded-full sm:h-8 sm:w-8"
        :class="actual === v ? 'bg-[var(--neon)] text-white' : 'hover:bg-white/10'"
      >{{ v }}</Link>
      <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-sm hover:bg-white/10" title="Ver la intro otra vez" aria-label="Ver la intro otra vez" @click="verIntro">↻</button>
      <button type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-sm hover:bg-white/10" title="Plegar" aria-label="Plegar" @click="abierta = false">×</button>
    </template>
  </div>
</template>
