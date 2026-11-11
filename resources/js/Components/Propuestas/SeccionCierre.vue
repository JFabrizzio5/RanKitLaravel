<script setup lang="ts">
/**
 * Cierre: dos palabras gigantes con un video entre ellas y los contactos
 * (la sección "LET'S CONTACT COLLABORATE" de la referencia), y el pie.
 */
import { Link } from '@inertiajs/vue3'
import VideoBucle from './VideoBucle.vue'
import { contacto, medios } from './medios'

withDefaults(defineProps<{ arriba?: string; abajo?: string }>(), { arriba: 'Arma tu', abajo: 'torneo' })

const columnas = [
  { titulo: 'Rankit', enlaces: [{ t: 'Inicio', h: '/' }, { t: 'Liga', h: '/league' }, { t: 'Semanales', h: '/semanales' }, { t: 'Precios', h: '/#pricing' }] },
  { titulo: 'Cuenta', enlaces: [{ t: 'Crear cuenta', h: '/register' }, { t: 'Entrar', h: '/login' }] },
]
</script>

<template>
  <section class="relative mx-auto max-w-[1600px] overflow-hidden px-5 pt-24 sm:px-8 lg:pt-32">
    <p data-anim="desde-izq" class="titular text-center text-[16vw] lg:text-[220px]">{{ arriba }}</p>
    <div data-anim="crecer" class="relative z-10 mx-auto -my-[2vw] aspect-video w-[70vw] max-w-[560px] lg:-my-6">
      <VideoBucle :src="medios.final.video" :poster="medios.final.poster" alt="Equipo levantando el trofeo" />
    </div>
    <p data-anim="desde-der" class="titular text-center text-[16vw] text-[var(--neon)] lg:text-[220px]">{{ abajo }}</p>

    <div data-anim="escalonar" class="mt-16 flex flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-10">
      <Link href="/register" class="boton boton-lleno"><span>Crear cuenta gratis</span></Link>
      <a :href="contacto.whatsapp" target="_blank" rel="noopener" class="boton boton-linea"><span>WhatsApp</span></a>
      <a :href="contacto.instagram" target="_blank" rel="noopener" class="ui flecha">Instagram</a>
    </div>
  </section>

  <footer class="mx-auto mt-28 max-w-[1600px] border-t border-[var(--linea)] px-5 pb-24 pt-12 sm:px-8">
    <div class="grid gap-10 sm:grid-cols-3">
      <div v-for="c in columnas" :key="c.titulo">
        <p class="ui text-[var(--gris)]">{{ c.titulo }}</p>
        <ul class="mt-4 space-y-2">
          <li v-for="e in c.enlaces" :key="e.h"><Link :href="e.h" class="ui text-white hover:text-[var(--neon)]">{{ e.t }}</Link></li>
        </ul>
      </div>
      <div class="sm:text-right">
        <p class="ui text-[var(--gris)]">Contacto</p>
        <a :href="contacto.whatsapp" target="_blank" rel="noopener" class="ui mt-4 block text-white hover:text-[var(--neon)]">WhatsApp</a>
        <a :href="contacto.instagram" target="_blank" rel="noopener" class="ui mt-2 block text-white hover:text-[var(--neon)]">@rankit.pro</a>
      </div>
    </div>
    <p class="ui mt-16 text-[var(--gris)]">© {{ new Date().getFullYear() }} Rankit · Powered by CometaX</p>
  </footer>
</template>
