<script setup lang="ts">
/**
 * Lienzo de los objetos 3D del recorrido (objetos3d.ts). Uno solo para los
 * tres: nunca se ven dos a la vez, así que se cambia el modelo y no el canvas.
 *
 * Three.js y los modelos se piden con preparar() (cuando empieza el
 * recorrido), no al cargar la página. Sin WebGL se muestra la foto recortada.
 */
import { computed, onUnmounted, ref } from 'vue'
import type { NombreObjeto, Objetos3D } from './objetos3d'

const props = defineProps<{ objetos: Record<NombreObjeto, { alt: string; respaldo: string }> }>()

const lienzo = ref<HTMLCanvasElement | null>(null)
const actual = ref<NombreObjeto | null>(null)
const fallo = ref(false)
const alt = computed(() => (actual.value ? props.objetos[actual.value].alt : ''))
let motor: Objetos3D | null = null
let cargando: Promise<void> | null = null
let vueltas = 0
let vivo = false

function preparar() {
  cargando ??= import('./objetos3d')
    .then(async ({ crearObjetos }) => {
      if (!lienzo.value) return
      motor = await crearObjetos(lienzo.value)
      if (desmontado) { motor.destruir(); motor = null; return }
      motor.ver(actual.value)
      motor.girar(vueltas)
      motor.vivo(vivo)
    })
    .catch(() => { fallo.value = true })
  return cargando
}

let desmontado = false
onUnmounted(() => {
  desmontado = true
  motor?.destruir()
})

defineExpose({
  preparar,
  ver(n: NombreObjeto | null) {
    if (n === actual.value) return
    actual.value = n
    motor?.ver(n)
  },
  /** Vueltas que pone el scroll. */
  girar(v: number) { vueltas = v; motor?.girar(v) },
  vivo(si: boolean) { vivo = si; motor?.vivo(si) },
})
</script>

<template>
  <div class="relative h-full w-full">
    <canvas v-show="!fallo" ref="lienzo" class="block h-full w-full" role="img" :aria-label="alt"></canvas>
    <img v-if="fallo && actual" :src="objetos[actual].respaldo" :alt="alt" class="absolute inset-0 h-full w-full object-contain" decoding="async" />
  </div>
</template>
