<script setup lang="ts">
/**
 * El logo de Rankit que se transforma en control, balón y trofeo.
 * - modo "bucle": recorre las figuras solo, sin parar (y avisa cuál toca).
 * - modo "manual": el padre lo mueve con irA() o arma su propia línea de
 *   tiempo con `piezas` (p. ej. atada al scroll).
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { gsap, sinMovimiento } from './animaciones'
import { FORMAS, morfar, type NombreForma } from './logoFormas'

const props = withDefaults(defineProps<{
  modo?: 'bucle' | 'manual'
  secuencia?: NombreForma[]
  pausa?: number
  /** true: el bucle espera a que el padre llame iniciar() (p. ej. al terminar su intro). */
  esperar?: boolean
}>(), { modo: 'bucle', secuencia: () => ['logo', 'control', 'balon', 'trofeo'], pausa: 1.1, esperar: false })

const emit = defineEmits<{ forma: [NombreForma] }>()

const p0 = ref<SVGPathElement | null>(null)
const p1 = ref<SVGPathElement | null>(null)
const p2 = ref<SVGPathElement | null>(null)
const piezas = () => [p0.value!, p1.value!, p2.value!]

let tl: gsap.core.Timeline | null = null
let actual: gsap.core.Timeline | null = null

onMounted(() => {
  if (props.modo !== 'bucle' || sinMovimiento()) return
  tl = gsap.timeline({ repeat: -1, delay: 0.6, paused: props.esperar })
  const orden = [...props.secuencia.slice(1), props.secuencia[0]]
  orden.forEach((forma) => {
    tl!.call(() => emit('forma', forma), [], `+=${props.pausa}`)
    morfar(tl!, piezas(), forma, '<')
  })
})
onUnmounted(() => { tl?.kill(); actual?.kill() })

/** Lleva el logo a una figura (modo manual). */
function irA(forma: NombreForma, duracion = 0.7) {
  actual?.kill()
  actual = morfar(gsap.timeline(), piezas(), forma, 0, duracion)
  emit('forma', forma)
}

/** Arranca el bucle cuando se creó con esperar=true. */
function iniciar() { tl?.play() }

defineExpose({ irA, iniciar, piezas })
</script>

<template>
  <svg viewBox="0 0 100 100" class="overflow-visible" role="img" aria-label="Logo de Rankit">
    <path ref="p0" :d="FORMAS.logo[0].d" :fill="FORMAS.logo[0].color" />
    <path ref="p1" :d="FORMAS.logo[1].d" :fill="FORMAS.logo[1].color" />
    <path ref="p2" :d="FORMAS.logo[2].d" :fill="FORMAS.logo[2].color" />
  </svg>
</template>
