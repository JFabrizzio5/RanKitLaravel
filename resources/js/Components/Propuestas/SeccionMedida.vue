<script setup lang="ts">
/**
 * "A la medida": para eventos grandes (empresas, escuelas, ligas). La venta va
 * por WhatsApp; Instagram y CometaX quedan como en la landing actual.
 */
import { contacto } from './medios'

const waVentas = 'https://wa.me/525532351392?text=' + encodeURIComponent('Hola Rankit, quiero cotizar un evento a la medida.')
const servicios = ['Staff que sube los replays por ti', 'Reglas, premios y código privado', 'Transmisión con la tabla en pantalla', 'Ligas por temporadas y finales presenciales']
</script>

<template>
  <section id="contacto" class="mx-auto max-w-[1600px] scroll-mt-24 px-5 py-24 sm:px-8 lg:py-32">
    <div class="grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:items-end">
      <div>
        <p class="kicker" data-anim="subir">Empresas, escuelas y ligas</p>
        <h2 data-anim="lineas" class="titular mt-3 text-[12vw] sm:text-7xl lg:text-[96px]">¿Algo <span class="text-[var(--neon)]">a la medida?</span></h2>
        <p data-anim="subir" class="mt-5 max-w-lg text-lg text-[var(--gris)]">Te armamos el torneo completo: tú pones el evento y nosotros lo operamos.</p>
      </div>
      <ul data-anim="escalonar" class="grid gap-3">
        <li v-for="s in servicios" :key="s" class="flex items-center gap-3 border-b border-[var(--linea)] pb-3 text-lg"><i class="ph-bold ph-check text-[var(--neon)]"></i>{{ s }}</li>
      </ul>
    </div>

    <div data-anim="escalonar" class="mt-14 grid gap-4 md:grid-cols-3">
      <a :href="waVentas" target="_blank" rel="noopener" class="tarjeta group flex flex-col !border-[var(--neon)] p-8">
        <i class="ph-fill ph-whatsapp-logo text-4xl text-[var(--neon)]"></i>
        <h3 class="titular mt-5 text-3xl">Cotizar por WhatsApp</h3>
        <p class="mt-2 text-[var(--gris)]">Te respondemos con una propuesta para tu evento.</p>
        <span class="boton boton-lleno mt-8 w-full"><span>Escribir ahora</span></span>
      </a>
      <a :href="contacto.instagram" target="_blank" rel="noopener" class="tarjeta group flex flex-col p-8">
        <i class="ph-fill ph-instagram-logo text-4xl text-white/70"></i>
        <h3 class="titular mt-5 text-3xl">DM a @rankit.pro</h3>
        <p class="mt-2 text-[var(--gris)]">Te armamos una demo o una propuesta.</p>
        <span class="ui mt-auto flex items-center gap-2 pt-8 group-hover:text-[var(--neon)]">Abrir Instagram <i class="ph-bold ph-arrow-up-right"></i></span>
      </a>
      <a href="https://cometax.click" target="_blank" rel="noopener" class="tarjeta group flex flex-col p-8">
        <i class="ph-fill ph-buildings text-4xl text-white/70"></i>
        <h3 class="titular mt-5 text-3xl">Software a medida</h3>
        <p class="mt-2 text-[var(--gris)]">Sistemas propios o integraciones fuera de Rankit: los construye CometaX.</p>
        <span class="ui mt-auto flex items-center gap-2 pt-8 group-hover:text-[var(--neon)]">Ver CometaX <i class="ph-bold ph-arrow-up-right"></i></span>
      </a>
    </div>
  </section>
</template>
