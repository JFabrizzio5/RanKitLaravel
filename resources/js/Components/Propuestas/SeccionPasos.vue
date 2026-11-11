<script setup lang="ts">
/**
 * "Así funciona": tres columnas con número, título y una línea, más una
 * barra que se llena al entrar en pantalla (la sección "framework" de la
 * referencia).
 */
import { onMounted, onUnmounted, ref } from 'vue'

const pasos = [
  { n: '01', titulo: 'Crea', texto: 'Nombre, reglas y cuántos puntos vale cada eliminación y cada lugar.' },
  { n: '02', titulo: 'Inscribe', texto: 'Compartes el link y los jugadores se registran solos.' },
  { n: '03', titulo: 'Juega', texto: 'Subes el replay y la tabla se actualiza para todos.' },
]

const raiz = ref<HTMLElement | null>(null)
const lleno = ref(false)
let obs: IntersectionObserver | undefined
onMounted(() => {
  obs = new IntersectionObserver(([e]) => { if (e.isIntersecting) { lleno.value = true; obs?.disconnect() } }, { threshold: 0.4 })
  if (raiz.value) obs.observe(raiz.value)
})
onUnmounted(() => obs?.disconnect())
</script>

<template>
  <section ref="raiz" class="mx-auto max-w-[1600px] px-5 py-24 sm:px-8 lg:py-32">
    <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
      <h2 data-anim="letras" class="titular text-[13vw] sm:text-8xl lg:text-[110px]">Así funciona</h2>
      <p data-anim="subir" class="ui max-w-xs text-[var(--gris)]">Tres pasos. Sin Excel, sin capturas de pantalla.</p>
    </div>

    <div class="relative mt-14 h-px bg-[var(--linea)]">
      <div class="absolute inset-y-0 left-0 bg-[var(--neon)] transition-[width] duration-[1800ms] ease-out" :style="{ width: lleno ? '100%' : '0%' }"></div>
    </div>

    <ol data-anim="escalonar" class="grid gap-12 pt-10 md:grid-cols-3">
      <li v-for="p in pasos" :key="p.n">
        <span class="ui text-[var(--neon)]">{{ p.n }}</span>
        <h3 class="titular mt-4 text-6xl">{{ p.titulo }}</h3>
        <p class="mt-4 max-w-xs text-lg leading-snug text-[var(--gris)]">{{ p.texto }}</p>
      </li>
    </ol>
  </section>
</template>
