<script setup lang="ts">
/**
 * "Lo que hace Rankit": renglones grandes con una miniatura real entre las
 * dos palabras (la sección "core skills" de la referencia), y debajo dos
 * frases en mayúsculas que dicen lo esencial.
 */
import { medios } from './medios'

const renglones = [
  { a: 'Inscripciones', img: medios.mirada.poster, b: 'en línea' },
  { a: 'Tabla', img: medios.tabla, b: 'en vivo' },
  { a: 'Resultados', img: medios.arena.poster, b: 'desde el replay' },
  { a: 'Widgets', img: medios.widget, b: 'para OBS' },
  { a: 'Ligas', img: medios.futbol.poster, b: 'y brackets' },
  { a: 'Premios', img: medios.final.poster, b: 'y clasificados' },
]
</script>

<template>
  <section class="mx-auto max-w-[1600px] px-5 py-24 sm:px-8 lg:py-32">
    <h2 data-anim="letras" class="titular text-center text-[12vw] sm:text-7xl lg:text-[100px]">Lo que hace Rankit</h2>
    <ul data-anim="escalonar" class="mt-12 flex flex-col items-center gap-3 sm:gap-4">
      <li v-for="r in renglones" :key="r.a" class="group flex items-center gap-3 text-[7vw] font-medium tracking-tight text-white/45 transition hover:text-white sm:gap-5 sm:text-5xl">
        <span>{{ r.a }}</span>
        <img :src="r.img" alt="" loading="lazy" class="h-[1em] w-[1.6em] rounded-md object-cover object-top transition duration-500 group-hover:scale-110" />
        <span>{{ r.b }}</span>
      </li>
    </ul>
    <div class="mt-24 grid gap-10 border-t border-[var(--linea)] pt-10 md:grid-cols-2">
      <p data-anim="llenar" class="ui max-w-md text-sm leading-relaxed text-white">Fortnite, League of Legends, Valorant y fútbol 7 en un solo lugar.</p>
      <p data-anim="llenar" class="ui max-w-md text-sm leading-relaxed text-[var(--gris)] md:justify-self-end">La tabla se arma con los datos de cada partida. <span class="text-white">Nadie la captura a mano.</span></p>
    </div>
  </section>
</template>
