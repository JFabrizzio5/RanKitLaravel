<script setup lang="ts">
/**
 * Número que "rueda" dígito por dígito al entrar en pantalla (el efecto de
 * las cifras de la referencia). Sin JS o con movimiento reducido se ve el
 * número final directo.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'

const props = withDefaults(defineProps<{ valor: number; sufijo?: string }>(), { sufijo: '' })

const texto = computed(() => props.valor.toLocaleString('es-MX'))
const raiz = ref<HTMLElement | null>(null)
const visible = ref(false)
let obs: IntersectionObserver | undefined

onMounted(() => {
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
    visible.value = true
    return
  }
  obs = new IntersectionObserver(([e]) => {
    if (e.isIntersecting) { visible.value = true; obs?.disconnect() }
  }, { threshold: 0.4 })
  if (raiz.value) obs.observe(raiz.value)
})
onUnmounted(() => obs?.disconnect())
</script>

<template>
  <span ref="raiz" class="inline-flex items-start leading-none" :aria-label="texto + sufijo">
    <template v-for="(c, i) in texto" :key="i">
      <span v-if="/\d/.test(c)" class="relative inline-block h-[1em] overflow-hidden" aria-hidden="true">
        <span
          class="flex flex-col transition-transform duration-[1400ms] ease-[cubic-bezier(.2,.8,.2,1)]"
          :style="{ transform: `translateY(-${visible ? Number(c) : 0}em)`, transitionDelay: `${i * 90}ms` }"
        >
          <span v-for="d in 10" :key="d" class="h-[1em]">{{ d - 1 }}</span>
        </span>
      </span>
      <span v-else aria-hidden="true">{{ c }}</span>
    </template>
    <span v-if="sufijo" class="ml-0.5 text-[0.55em] leading-none text-[var(--neon)]" aria-hidden="true">{{ sufijo }}</span>
  </span>
</template>
