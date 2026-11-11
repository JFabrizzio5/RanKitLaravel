<script setup lang="ts">
/** Preguntas frecuentes en acordeón (la sección "FAQ" de la referencia). Respuestas reales del producto. */
import { ref } from 'vue'

const props = defineProps<{ precioBase: number; precioPro: number }>()

const preguntas = [
  { p: '¿Cuánto cuesta?', r: `Jugar los semanales es gratis. Para organizar: 2 torneos por $${props.precioBase} o torneos ilimitados con Gestor Pro por $${props.precioPro} al mes.` },
  { p: '¿Qué juegos puedo usar?', r: 'Fortnite (los resultados salen del replay), League of Legends y Valorant con brackets, y fútbol 7 con ligas y jornadas.' },
  { p: '¿Cómo se calculan los puntos?', r: 'Tú decides cuánto vale cada eliminación y cada lugar. Rankit suma partida por partida y ordena la tabla.' },
  { p: '¿Puedo poner la tabla en mi stream?', r: 'Sí. Cada torneo tiene widgets para OBS que se actualizan solos.' },
  { p: '¿Los jugadores necesitan cuenta?', r: 'Para inscribirse basta con sus datos. Con cuenta ven todos sus torneos en un solo lugar.' },
]
const abierta = ref<number | null>(0)
</script>

<template>
  <section class="mx-auto grid max-w-[1600px] gap-12 px-5 py-24 sm:px-8 lg:grid-cols-[1fr_1.4fr] lg:py-32">
    <div>
      <!-- Una sola palabra de 9 letras: el tamaño va con el ancho para que quepa completa desde 320 px. -->
      <h2 data-anim="letras" class="titular text-[min(10.5vw,110px)]">Preguntas</h2>
      <p data-anim="subir" class="mt-4 text-xl text-[var(--gris)]">Respuestas claras, sin letras chiquitas.</p>
    </div>
    <ul data-anim="escalonar" class="border-t border-[var(--linea)]">
      <li v-for="(q, i) in preguntas" :key="q.p" class="border-b border-[var(--linea)]">
        <button type="button" class="flex w-full items-center justify-between gap-6 py-6 text-left text-lg font-medium sm:text-xl" :aria-expanded="abierta === i" @click="abierta = abierta === i ? null : i">
          {{ q.p }}
          <span class="text-2xl leading-none text-[var(--neon)] transition" :class="abierta === i ? 'rotate-45' : ''">+</span>
        </button>
        <div class="grid transition-[grid-template-rows] duration-300" :class="abierta === i ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
          <p class="overflow-hidden pr-10 text-[var(--gris)]"><span class="block pb-6">{{ q.r }}</span></p>
        </div>
      </li>
    </ul>
  </section>
</template>
