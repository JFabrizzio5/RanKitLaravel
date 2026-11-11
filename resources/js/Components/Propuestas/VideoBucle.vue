<script setup lang="ts">
/**
 * Video corto en bucle, mudo, con su póster. Se pausa fuera de pantalla para
 * no gastar batería; con "reducir movimiento" solo muestra el póster. Botón
 * opcional de pausa, como el del video del hero de la referencia.
 *
 * Ni el video ni el póster se piden hasta que el bloque está cerca de la
 * pantalla: al cargar le quitaban red a lo primero que se ve (el póster solo
 * ya eran ~130 KB).
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { useCerca } from './useCerca'

withDefaults(defineProps<{ src: string; poster: string; boton?: boolean; alt?: string }>(), { boton: false, alt: '' })

const video = ref<HTMLVideoElement | null>(null)
const pausado = ref(false)
const quieto = ref(false)
const caja = ref<HTMLElement | null>(null)
const cerca = useCerca(caja)
let obs: IntersectionObserver | undefined

onMounted(() => {
  quieto.value = matchMedia('(prefers-reduced-motion: reduce)').matches
  if (quieto.value || !video.value) return
  obs = new IntersectionObserver(([e]) => {
    if (!video.value || pausado.value) return
    e.isIntersecting ? video.value.play().catch(() => {}) : video.value.pause()
  }, { threshold: 0.15 })
  obs.observe(video.value)
})
onUnmounted(() => obs?.disconnect())

function alternar() {
  if (!video.value) return
  pausado.value = !pausado.value
  pausado.value ? video.value.pause() : video.value.play().catch(() => {})
}
</script>

<template>
  <div ref="caja" class="relative h-full w-full overflow-hidden bg-black">
    <img v-if="quieto && cerca" :src="poster" :alt="alt" class="h-full w-full object-cover" />
    <video
      v-else
      ref="video"
      :src="cerca ? src : undefined"
      :poster="cerca ? poster : undefined"
      class="h-full w-full object-cover"
      muted
      loop
      playsinline
      preload="metadata"
      :aria-label="alt || undefined"
    ></video>
    <button
      v-if="boton && !quieto"
      type="button"
      class="absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-black transition hover:bg-white"
      :aria-label="pausado ? 'Reproducir video' : 'Pausar video'"
      @click="alternar"
    >
      <i class="ph-fill text-base" :class="pausado ? 'ph-play' : 'ph-pause'"></i>
    </button>
  </div>
</template>
