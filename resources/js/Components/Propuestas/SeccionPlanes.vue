<script setup lang="ts">
/**
 * "Para organizar": los dos planes reales con su precio (mismo catálogo que
 * /planes). Los botones van a /contratar/{plan}: con sesión abre /planes con
 * la tarjeta resaltada; sin sesión crea la cuenta y regresa ahí.
 * `medida`: tercera tarjeta para eventos a la medida (cotización por WhatsApp).
 */
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { contacto } from './medios'

const props = defineProps<{ precioBase: number; precioPro: number; medida?: boolean }>()
const waVentas = 'https://wa.me/525532351392?text=' + encodeURIComponent('Hola Rankit, quiero cotizar un evento a la medida.')

const comunes = ['Fortnite, LoL, Valorant y fútbol 7', 'Inscripciones en línea', 'Tabla en vivo y widgets para OBS']
const planes = computed(() => [
  { nombre: 'Base', precio: props.precioBase, periodo: 'pago único', lineas: ['2 torneos (tickets) para usar en 30 días', ...comunes], destacado: false, boton: 'Empezar con Base', href: '/contratar/base' },
  { nombre: 'Gestor Pro', precio: props.precioPro, periodo: 'al mes', lineas: ['Torneos ilimitados', ...comunes, 'Cancelas cuando quieras'], destacado: true, boton: 'Quiero Gestor Pro', href: '/contratar/pro' },
])
</script>

<template>
  <section id="pricing" class="mx-auto max-w-[1600px] scroll-mt-20 px-5 py-24 sm:px-8 lg:py-32">
    <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
      <div>
        <p class="kicker" data-anim="subir">Para organizar</p>
        <h2 data-anim="letras" class="titular mt-3 text-[13vw] sm:text-8xl lg:text-[110px]">Planes</h2>
      </div>
      <p data-anim="subir" class="max-w-sm text-lg text-[var(--gris)]">Pagas con tarjeta y el panel se abre al momento. Precios en pesos, con comisión incluida.</p>
    </div>

    <div data-anim="escalonar" class="mt-12 grid gap-6" :class="medida ? 'lg:grid-cols-3' : 'lg:grid-cols-2'">
      <article v-for="p in planes" :key="p.nombre" class="tarjeta relative flex flex-col p-8 sm:p-10" :class="p.destacado ? '!border-[var(--neon)]' : ''">
        <span v-if="p.destacado" class="absolute -top-3 right-8 bg-[var(--neon)] px-3 py-1 font-mono text-[11px] font-bold uppercase">Para ligas y circuitos</span>
        <h3 class="titular text-4xl">{{ p.nombre }}</h3>
        <p class="mt-6 flex items-baseline gap-2">
          <span class="titular text-7xl not-italic">${{ p.precio.toLocaleString('es-MX') }}</span>
          <span class="font-mono text-sm uppercase text-gray-400">MXN · {{ p.periodo }}</span>
        </p>
        <ul class="mt-8 space-y-3">
          <li v-for="l in p.lineas" :key="l" class="flex gap-3 text-gray-300"><i class="ph-bold ph-check mt-1 text-[var(--neon)]"></i>{{ l }}</li>
        </ul>
        <div class="mt-auto pt-10">
          <Link :href="p.href" class="boton w-full" :class="p.destacado ? 'boton-lleno' : 'boton-linea'"><span>{{ p.boton }}</span></Link>
        </div>
      </article>
      <article v-if="medida" class="tarjeta relative flex flex-col p-8 sm:p-10">
        <h3 class="titular text-4xl">A la medida</h3>
        <p class="mt-6 flex items-baseline gap-2">
          <span class="titular text-7xl not-italic">Cotiza</span>
        </p>
        <ul class="mt-8 space-y-3">
          <li v-for="l in ['Empresas, escuelas y ligas', 'Staff que sube los replays', 'Reglas, premios y código privado', 'Transmisión con la tabla en pantalla']" :key="l" class="flex gap-3 text-gray-300"><i class="ph-bold ph-check mt-1 text-[var(--neon)]"></i>{{ l }}</li>
        </ul>
        <div class="mt-auto pt-10">
          <a :href="waVentas" target="_blank" rel="noopener" class="boton boton-linea w-full"><span>Cotizar por WhatsApp</span></a>
        </div>
      </article>
    </div>

    <p class="mt-8 text-center text-gray-400">¿Prefieres que te lo expliquemos? <a :href="contacto.whatsapp" target="_blank" rel="noopener" class="font-semibold text-white underline decoration-[var(--neon)] underline-offset-4">Escríbenos por WhatsApp</a></p>
  </section>
</template>
