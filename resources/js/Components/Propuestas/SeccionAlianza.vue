<script setup lang="ts">
/** "Partners": el programa de la landing actual (vendedores de tecnología y creadores), mismo texto y mismos enlaces. */
const programas = [
  { icon: 'ph-handshake', titulo: 'Vendedores de tecnología', desc: '¿Vendes software o hardware a canchas deportivas? Añade Rankit a tu portafolio y gana comisiones recurrentes.', cta: 'Aplicar al programa' },
  { icon: 'ph-video-camera', titulo: 'Creadores de contenido', desc: 'Streamers y YouTubers: usa Rankit para tus torneos comunitarios y obtén beneficios exclusivos y revenue share.', cta: 'Unirme como creador' },
]
</script>

<template>
  <section id="partners" class="reticula relative scroll-mt-24 border-y border-[var(--linea)]">
    <div class="mx-auto max-w-[1600px] px-5 py-24 sm:px-8 lg:py-32">
      <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
        <div>
          <p class="kicker" data-anim="subir">Rankit Alliance</p>
          <h2 data-anim="letras" class="titular mt-3 text-[12vw] sm:text-7xl lg:text-[96px]">Gana con <span class="text-[var(--neon)]">Rankit</span></h2>
        </div>
        <p data-anim="subir" class="max-w-sm text-lg text-[var(--gris)]">Monetiza tu influencia o tu red de contactos.</p>
      </div>
      <div data-anim="escalonar" class="mt-12 grid gap-6 md:grid-cols-2">
        <a v-for="p in programas" :key="p.titulo" href="https://instagram.com/cometaxcompany" target="_blank" rel="noopener" class="tarjeta group flex flex-col p-8 sm:p-10">
          <i class="ph-fill text-4xl text-[var(--neon)]" :class="p.icon"></i>
          <h3 class="titular mt-6 text-3xl sm:text-4xl">{{ p.titulo }}</h3>
          <p class="mt-3 max-w-md text-[var(--gris)]">{{ p.desc }}</p>
          <span class="ui mt-8 flex items-center gap-2 text-white group-hover:text-[var(--neon)]">{{ p.cta }} <i class="ph-bold ph-arrow-up-right"></i></span>
        </a>
      </div>
    </div>
  </section>
</template>
