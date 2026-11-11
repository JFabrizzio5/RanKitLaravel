<script setup lang="ts">
/** Cifras reales de producción con contador rodante (la franja "statistics" de la referencia). */
import ContadorRodante from './ContadorRodante.vue'
import { cifras } from './medios'

const datos = [
  { valor: cifras.torneos, sufijo: '+', texto: 'Torneos creados' },
  { valor: cifras.jugadores, sufijo: '+', texto: 'Jugadores en tablas' },
  { valor: cifras.partidas, sufijo: '', texto: 'Partidas procesadas' },
  { valor: cifras.eliminaciones, sufijo: '', texto: 'Eliminaciones contadas' },
]
</script>

<template>
  <section class="mx-auto max-w-[1600px] px-5 py-24 sm:px-8 lg:py-32">
    <p data-anim="lineas" class="max-w-md text-2xl font-medium leading-snug sm:text-3xl">
      Organizadores de México ya corren sus torneos en Rankit.
    </p>
    <!-- gap-px sobre fondo gris = líneas finas entre celdas -->
    <div data-anim="escalonar" class="mt-14 grid grid-cols-2 gap-px border-y border-[var(--linea)] bg-[var(--linea)] lg:grid-cols-4">
      <div v-for="d in datos" :key="d.texto" class="bg-[#0d0d0d] px-4 py-8 sm:px-6">
        <p class="titular text-6xl sm:text-7xl"><ContadorRodante :valor="d.valor" :sufijo="d.sufijo" /></p>
        <p class="ui mt-4 text-[var(--gris)]">{{ d.texto }}</p>
      </div>
    </div>
  </section>
</template>
