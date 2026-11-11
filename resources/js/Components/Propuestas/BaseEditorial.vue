<script setup lang="ts">
/**
 * Lienzo común de las propuestas: la composición editorial de la referencia
 * (palabras gigantes, mucho aire, texto de interfaz chico) pero con la
 * identidad de Rankit: Chakra Petch en itálica, morado #bf00ff, botones
 * inclinados (skew -10°), tarjetas de borde duro y la retícula técnica.
 * Incluye la píldora para brincar entre propuestas (solo existe en la demo).
 */
import { Head, usePage } from '@inertiajs/vue3'
import { onMounted } from 'vue'
import RankitContactWidget from '@/Components/RankitContactWidget.vue'
import IntroLogo from './IntroLogo.vue'
import { libre } from './animaciones'

import PildoraPropuestas, { type Letra } from './PildoraPropuestas.vue'

// sinIntro: la página arma su propia entrada (la K usa el logo 3D, y la intro
// en 2D lo repetiría).
// contacto: si se ve el botón de WhatsApp/chat (la K lo esconde durante el hero y el recorrido).
// actual: letra de la propuesta (sin ella no sale la píldora: páginas reales como /torneos).
withDefaults(defineProps<{ actual?: Letra; titulo: string; sinIntro?: boolean; contacto?: boolean }>(), { contacto: true, actual: undefined })
// La píldora solo en /propuestas (demo local): la K también es la página de inicio real.
const enDemo = usePage().url.startsWith('/propuestas')

// Íconos Phosphor: al final, cuando la página ya cargó y el navegador quedó libre. Son
// adorno (los de la barra van en SVG) y sus fuentes pesan ~300 KB: al principio
// competían con lo que sí se ve primero.
onMounted(() => {
  const poner = () => libre(2500).then(() => {
    if (document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) return
    const s = document.createElement('script')
    s.src = 'https://unpkg.com/@phosphor-icons/web'
    s.async = true
    document.head.appendChild(s)
  })
  if (document.readyState === 'complete') poner()
  else window.addEventListener('load', poner, { once: true })
})
</script>

<template>
  <Head :title="titulo" />

  <div class="editorial min-h-screen overflow-x-hidden bg-[#050505] text-white antialiased">
    <slot />
  </div>

  <IntroLogo v-if="!sinIntro" />

  <!-- WhatsApp directo + chatbot, igual que la landing actual -->
  <Transition enter-active-class="transition duration-500" enter-from-class="opacity-0 translate-y-4" leave-active-class="transition duration-300" leave-to-class="opacity-0 translate-y-4">
    <div v-show="contacto"><RankitContactWidget lang="es" origen="Landing (propuesta)" /></div>
  </Transition>

  <PildoraPropuestas v-if="actual && enDemo" :actual="actual" />
</template>

<style>
/* Letras de respaldo con el mismo ancho que Archivo y Chakra Petch (size-adjust medido en
   Chrome). La fuente de Google ya no bloquea el primer pintado: mientras llega se usa Arial
   (Roboto en Android) escalada, así el texto parte igual y nada brinca cuando llega la buena.
   Sin esto los botones del hero salían más anchos, bajaban a dos renglones y movían todo (CLS). */
@font-face { font-family: "Archivo respaldo"; src: local("Arial"), local("ArialMT"), local("Liberation Sans"), local("Helvetica"); font-weight: 100 500; size-adjust: 98.2%; }
@font-face { font-family: "Archivo respaldo"; src: local("Arial Bold"), local("Arial-BoldMT"), local("Liberation Sans Bold"), local("Helvetica Bold"); font-weight: 600 900; size-adjust: 96.3%; }
@font-face { font-family: "Archivo respaldo Roboto"; src: local("Roboto"), local("Roboto-Regular"); font-weight: 100 500; size-adjust: 105.2%; }
@font-face { font-family: "Archivo respaldo Roboto"; src: local("Roboto Bold"), local("Roboto-Bold"); font-weight: 600 900; size-adjust: 101.9%; }
@font-face { font-family: "Chakra respaldo"; src: local("Arial"), local("ArialMT"), local("Liberation Sans"), local("Helvetica"); font-weight: 100 500; size-adjust: 95%; }
@font-face { font-family: "Chakra respaldo"; src: local("Arial Bold"), local("Arial-BoldMT"), local("Liberation Sans Bold"), local("Helvetica Bold"); font-weight: 600 900; size-adjust: 93.3%; }
@font-face { font-family: "Chakra respaldo Roboto"; src: local("Roboto Bold"), local("Roboto-Bold"); font-weight: 600 900; size-adjust: 91.5%; }

.editorial {
  --neon: #bf00ff;
  --gris: rgba(255, 255, 255, 0.55);
  --linea: rgba(255, 255, 255, 0.1);
  font-family: "Archivo", "Archivo respaldo", "Archivo respaldo Roboto", ui-sans-serif, system-ui, sans-serif;
}
/* Títulos: la letra de la marca (Chakra Petch) en itálica, como el logo RANKIT. */
.editorial .titular {
  font-family: "Chakra Petch", "Chakra respaldo", "Chakra respaldo Roboto", ui-sans-serif, system-ui, sans-serif;
  font-weight: 700;
  font-style: italic;
  text-transform: uppercase;
  line-height: 0.88;
  letter-spacing: -0.03em;
}
/* Texto de interfaz: chico, mayúsculas, con aire. */
.editorial .ui {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
.editorial .kicker {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--neon);
}
/* Retícula técnica de Rankit (la misma de la landing actual). */
.editorial .reticula {
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.035) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
  background-size: 40px 40px;
}
/* Botón inclinado de Rankit: la caja va a -10° y el texto se endereza. */
.editorial .boton {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.95rem 2rem;
  font-family: "Chakra Petch", "Chakra respaldo", "Chakra respaldo Roboto", sans-serif;
  font-weight: 700;
  font-size: 14px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  transform: skewX(-10deg);
  transition: background-color 0.2s, color 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.editorial .boton > * { transform: skewX(10deg); }
.editorial .boton-lleno { background: var(--neon); color: #fff; }
.editorial .boton-lleno:hover { background: #fff; color: #000; box-shadow: 0 0 24px rgba(191, 0, 255, 0.55); }
.editorial .boton-linea { border: 1px solid rgba(255, 255, 255, 0.3); color: #fff; }
.editorial .boton-linea:hover { border-color: var(--neon); color: var(--neon); }
/* Tarjeta de borde duro (brutal-card de Rankit). */
.editorial .tarjeta { background: #0a0a0a; border: 1px solid #262626; transition: border-color 0.2s, transform 0.2s; }
.editorial .tarjeta:hover { border-color: var(--neon); transform: translateY(-4px); }
.editorial .flecha::before {
  content: "↗";
  color: var(--neon);
  margin-right: 0.4em;
}
/* Enlaces de la barra: corchetes que aparecen al pasar el mouse (como la referencia). */
.editorial .enlace-nav { position: relative; transition: color 0.25s; }
.editorial .enlace-nav::before, .editorial .enlace-nav::after { display: inline-block; opacity: 0; color: var(--neon); transition: opacity 0.25s, transform 0.25s; }
.editorial .enlace-nav::before { content: "["; margin-right: 0.35em; transform: translateX(8px); }
.editorial .enlace-nav::after { content: "]"; margin-left: 0.35em; transform: translateX(-8px); }
.editorial .enlace-nav:hover::before, .editorial .enlace-nav:hover::after { opacity: 1; transform: none; }
/* Botón: brillo que cruza al pasar el mouse. */
.editorial .boton { position: relative; overflow: hidden; }
.editorial .boton::after { content: ""; position: absolute; inset: 0; background: linear-gradient(100deg, transparent 30%, rgba(255, 255, 255, 0.35) 50%, transparent 70%); transform: translateX(-120%); transition: transform 0.7s ease; }
.editorial .boton:hover::after { transform: translateX(120%); }
/* Tarjetas: la imagen se acerca al pasar el mouse. */
.editorial .tarjeta img { transition: transform 0.9s cubic-bezier(.2,.8,.2,1); }
.editorial .tarjeta:hover img { transform: scale(1.07); }
/* Cinta que corre sola: el contenido va duplicado y se mueve la mitad. */
.editorial .cinta { animation: cinta 40s linear infinite; }
@keyframes cinta { to { transform: translateX(-50%); } }
.editorial .contorno { color: transparent; -webkit-text-stroke: 1.5px rgba(255, 255, 255, 0.35); }
@media (prefers-reduced-motion: reduce) {
  .editorial *, .editorial *::before, .editorial *::after { animation: none !important; transition: none !important; }
}
</style>
