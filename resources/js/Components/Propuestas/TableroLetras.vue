<script setup lang="ts">
/**
 * Tablero de letras que giran (como los de aeropuerto o estadio): cuando
 * cambia el texto, cada casilla pasa por letras al azar y se detiene en la
 * suya, una tras otra de izquierda a derecha.
 */
import { onUnmounted, ref, watch } from 'vue'
import { sinMovimiento } from './animaciones'

const props = withDefaults(defineProps<{ texto: string; casillas: number; acento?: boolean }>(), { acento: false })

const ALEATORIAS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
const relleno = (t: string) => t.toUpperCase().padEnd(props.casillas, ' ').slice(0, props.casillas).split('')

const letras = ref<string[]>(relleno(props.texto))
const girando = ref<boolean[]>(letras.value.map(() => false))
const relojes: ReturnType<typeof setInterval>[] = []

function limpiar() { relojes.splice(0).forEach(clearInterval) }

watch(() => props.texto, (nuevo) => {
  const destino = relleno(nuevo)
  if (sinMovimiento()) { letras.value = destino; return }
  limpiar()
  destino.forEach((final, i) => {
    const hasta = performance.now() + 260 + i * 75
    girando.value[i] = true
    relojes.push(setInterval(() => {
      if (performance.now() >= hasta) {
        letras.value[i] = final
        girando.value[i] = false
        return
      }
      letras.value[i] = ALEATORIAS[Math.floor(Math.random() * ALEATORIAS.length)]
    }, 55))
  })
})
onUnmounted(limpiar)
</script>

<template>
  <!-- Decorativo: el texto lo dice el título oculto (sr-only) de quien lo usa. -->
  <div class="flex justify-center gap-[0.08em]" aria-hidden="true">
    <span
      v-for="(l, i) in letras"
      :key="i"
      class="casilla relative flex h-[1.25em] w-[0.86em] items-center justify-center overflow-hidden rounded-[0.08em] bg-[#121212] font-display font-bold not-italic leading-none shadow-[inset_0_-0.06em_0_rgba(0,0,0,0.6)]"
      :class="[acento && l.trim() ? 'text-[var(--neon)]' : 'text-white', girando[i] ? 'girando' : '']"
      aria-hidden="true"
    >
      {{ l }}
      <span class="pointer-events-none absolute inset-x-0 top-1/2 h-px bg-black/80"></span>
    </span>
  </div>
</template>

<style scoped>
.casilla { box-shadow: inset 0 1px 0 rgba(255,255,255,0.06), 0 0.04em 0.12em rgba(0,0,0,0.6); }
.girando { animation: voltear 0.11s linear infinite; }
@keyframes voltear {
  0% { transform: scaleY(1); }
  50% { transform: scaleY(0.82); filter: brightness(0.7); }
  100% { transform: scaleY(1); }
}
</style>
