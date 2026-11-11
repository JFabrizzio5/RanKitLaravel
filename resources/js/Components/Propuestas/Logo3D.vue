<script setup lang="ts">
/**
 * Lienzo con el logo de Rankit en 3D. Carga Three.js aparte cuando se monta.
 * Sin WebGL o con "reducir movimiento" avisa con `plano` y, si `respaldo`,
 * muestra el logo plano él mismo (si no, el padre lo pone donde quiera).
 * Expone la escena para que el padre la gire o cambie de figura con el scroll.
 *
 * diferir: Three.js no se pide al montar sino cuando la página ya cargó y el
 * navegador quedó libre, o al primer toque/scroll (lo que pase antes). Mientras,
 * el padre muestra el logo plano. Pesa ~150 KB y armar la escena es la tarea
 * más larga de la página en un celular: así no retrasa lo primero que se ve.
 */
import { onMounted, onUnmounted, ref, shallowRef } from 'vue'
import { libre, sinMovimiento } from './animaciones'
import { FORMAS } from './logoFormas'
import type { Escena3D } from './logo3d'

const props = withDefaults(defineProps<{ vivo?: boolean; distancia?: number; respaldo?: boolean; diferir?: boolean }>(), { vivo: true, distancia: 230, respaldo: true, diferir: false })
const emit = defineEmits<{ listo: [Escena3D]; plano: [] }>()

const lienzo = ref<HTMLCanvasElement | null>(null)
const escena = shallowRef<Escena3D | null>(null)
const plano = ref(false)

let vivo = true
const EVENTOS = ['pointerdown', 'keydown', 'wheel', 'touchstart', 'scroll'] as const

/** Después de cargar y con el navegador libre, o al primer toque/scroll. */
function cuandoToque(fn: () => void) {
  let hecho = false
  const ir = () => {
    if (hecho) return
    hecho = true
    EVENTOS.forEach((e) => window.removeEventListener(e, ir))
    fn()
  }
  EVENTOS.forEach((e) => window.addEventListener(e, ir, { passive: true, once: true }))
  const tras = () => setTimeout(() => libre(1200).then(ir), 400)
  if (document.readyState === 'complete') tras()
  else window.addEventListener('load', tras, { once: true })
}

async function arrancar() {
  if (!vivo) return
  const { crearEscena, hayWebGL } = await import('./logo3d')
  if (!vivo) return
  if (!lienzo.value || !hayWebGL() || sinMovimiento()) {
    plano.value = true
    emit('plano')
    return
  }
  escena.value = crearEscena(lienzo.value, props.distancia)
  escena.value.vivo(props.vivo)
  emit('listo', escena.value)
}

onMounted(() => {
  // Con "reducir movimiento" no hace falta ni bajar Three.js.
  if (sinMovimiento()) { plano.value = true; emit('plano'); return }
  if (props.diferir) cuandoToque(arrancar)
  else arrancar()
})
onUnmounted(() => {
  vivo = false
  escena.value?.destruir()
})

defineExpose({ escena })
</script>

<template>
  <div class="relative h-full w-full">
    <canvas v-show="!plano" ref="lienzo" class="h-full w-full" aria-label="Logo de Rankit en 3D" role="img"></canvas>
    <svg v-if="plano && respaldo" viewBox="0 0 100 100" class="absolute inset-0 m-auto h-3/5 w-3/5" role="img" aria-label="Logo de Rankit">
      <path v-for="(p, i) in FORMAS.logo" :key="i" :d="p.d" :fill="p.color" />
    </svg>
  </div>
</template>
