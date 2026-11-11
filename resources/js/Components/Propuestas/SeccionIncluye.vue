<script setup lang="ts">
/**
 * "Plataforma": lo que se lleva el organizador al contratar y el botón de
 * compra. A la derecha, el widget de OBS de verdad (el mismo componente que
 * sale en el stream) con datos de ejemplo, y botones para cambiarle el tema y
 * el color: así se ve que se puede personalizar.
 */
import { Link } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import TablaWidget from '@/Components/Widget/TablaWidget.vue'
import { configBase, type Tema } from '@/Components/Widget/configWidget'
import { fondos } from './medios'
import { useCerca } from './useCerca'

const props = defineProps<{ precioBase: number }>()

// La foto del stream se pide cuando la sección ya está cerca (no al cargar la página).
const caja = ref<HTMLElement | null>(null)
const cerca = useCerca(caja)

// Datos inventados para la muestra (no son jugadores reales).
const demo = [
  { rank: 1, player_name: 'NovaFrag', total_points: 65, total_kills: 39, games_played: 3 },
  { rank: 2, player_name: 'Kairo', total_points: 54, total_kills: 32, games_played: 3 },
  { rank: 3, player_name: 'LunaSnipe', total_points: 53, total_kills: 24, games_played: 3 },
  { rank: 4, player_name: 'Tempo', total_points: 45, total_kills: 21, games_played: 3 },
  { rank: 5, player_name: 'Brisa', total_points: 39, total_kills: 18, games_played: 3 },
]
const config = reactive({ ...configBase(), filas: 5, titulo: 'Top jugadores' })
const temas: { valor: Tema; texto: string }[] = [{ valor: 'oscuro', texto: 'Oscuro' }, { valor: 'cristal', texto: 'Cristal' }, { valor: 'claro', texto: 'Claro' }]
const colores = ['bf00ff', '00e5ff', '22c55e', 'facc15', 'ff3b6b']

const incluye = [
  { icon: 'ph-link', t: 'Inscripciones en línea', d: 'Compartes un link y se registran solos.' },
  { icon: 'ph-film-strip', t: 'Resultados desde el replay', d: 'Nadie captura puntos a mano.' },
  { icon: 'ph-ranking', t: 'Tabla en vivo', d: 'Se actualiza con cada partida, para todos.' },
  { icon: 'ph-broadcast', t: 'Widgets para OBS', d: 'La tabla en tu stream, al momento.' },
  { icon: 'ph-sliders-horizontal', t: 'Tus reglas', d: 'Puntos por eliminación y por lugar, a tu manera.' },
  { icon: 'ph-lock-key', t: 'Código privado', d: 'Solo entran los inscritos.' },
]
</script>

<template>
  <section id="plataforma" class="relative mx-auto max-w-[1600px] scroll-mt-24 px-5 py-24 sm:px-8 lg:py-32">
    <div class="grid gap-14 lg:grid-cols-[1fr_1.15fr] lg:items-center">
      <div>
        <p class="kicker" data-anim="subir">Para organizar</p>
        <h2 data-anim="lineas" class="titular mt-3 text-[12vw] sm:text-7xl lg:text-[96px]">Tu torneo listo <span class="text-[var(--neon)]">hoy</span></h2>
        <p data-anim="subir" class="mt-5 max-w-md text-lg text-[var(--gris)]">Fortnite, LoL, Valorant y fútbol 7. Pagas, se abre tu panel y lo compartes en minutos.</p>

        <ul data-anim="escalonar" class="mt-10 grid gap-x-8 gap-y-6 sm:grid-cols-2">
          <li v-for="i in incluye" :key="i.t" class="flex gap-3">
            <i class="ph-fill mt-0.5 text-2xl text-[var(--neon)]" :class="i.icon"></i>
            <span>
              <span class="block font-bold uppercase tracking-wide">{{ i.t }}</span>
              <span class="block text-sm text-[var(--gris)]">{{ i.d }}</span>
            </span>
          </li>
        </ul>

        <div data-anim="subir" class="mt-10 flex flex-wrap items-center gap-4">
          <Link href="/contratar/base" class="boton boton-lleno"><span>Contratar · ${{ props.precioBase.toLocaleString('es-MX') }}</span></Link>
          <a href="#pricing" class="enlace-nav ui text-white/70 hover:text-white">Ver planes</a>
        </div>
      </div>

      <!-- El widget real con datos de ejemplo, sobre un stream -->
      <div data-anim="subir">
        <div ref="caja" class="relative overflow-hidden rounded-2xl border border-white/10" style="aspect-ratio: 16 / 11">
          <img v-if="cerca" :src="fondos.gamer.medio" :srcset="`${fondos.gamer.medio} 960w, ${fondos.gamer.grande} 1920w`" sizes="(min-width: 1024px) 50vw, 100vw" alt="" class="absolute inset-0 h-full w-full object-cover opacity-70" decoding="async" />
          <div class="absolute left-[4%] top-[6%] w-[min(70%,380px)]">
            <TablaWidget :filas="demo" :config="config" :torneo="{ nombre: 'Tu torneo', logo: null }" />
          </div>
          <span class="ui absolute bottom-3 right-3 rounded bg-black/70 px-2 py-1 !text-[10px] text-white/70">Ejemplo del widget</span>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-3">
          <span class="ui text-white/50">Personalízalo</span>
          <div class="flex gap-1.5">
            <button v-for="t in temas" :key="t.valor" type="button" class="ui rounded-full border px-3 py-1.5 !text-[10px] transition" :class="config.tema === t.valor ? 'border-[var(--neon)] text-white' : 'border-white/15 text-white/60 hover:text-white'" @click="config.tema = t.valor">{{ t.texto }}</button>
          </div>
          <div class="flex gap-2">
            <button v-for="c in colores" :key="c" type="button" class="h-6 w-6 rounded-full border-2 transition" :class="config.acento === c ? 'scale-110 border-white' : 'border-transparent'" :style="{ background: '#' + c }" :aria-label="`Color #${c}`" @click="config.acento = c"></button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
