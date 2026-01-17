<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard - BellzCup" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-display font-bold text-xl text-gray-800 dark:text-white leading-tight uppercase">
                Panel Principal
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="brutal-card bg-white dark:bg-[#0a0a0a] overflow-hidden">
                    
                    <div class="relative h-72 bg-gray-900 overflow-hidden group">
                         <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" 
                              class="w-full h-full object-cover opacity-50 group-hover:scale-105 transition duration-700 grayscale mix-blend-multiply" />
                         
                         <div class="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-transparent to-transparent"></div>
                         
                         <div class="absolute bottom-0 left-0 p-8 w-full">
                            <div class="flex justify-between items-end">
                                <div>
                                    <span class="inline-block py-1 px-3 bg-[var(--rankit-neon)] text-white text-[10px] font-bold uppercase tracking-widest mb-3 btn-skew">
                                        <span class="btn-content">Torneo Oficial</span>
                                    </span>
                                    <h3 class="text-5xl md:text-6xl font-display font-black text-white uppercase italic tracking-tighter mb-2">
                                        Bellz<span class="text-[var(--rankit-neon)]">Cup</span>
                                    </h3>
                                    <p class="text-gray-300 max-w-xl text-sm md:text-base border-l-4 border-[var(--rankit-neon)] pl-4 font-light">
                                        La competencia definitiva. Consulta los brackets, resultados en vivo y estadísticas de tu equipo favorito.
                                    </p>
                                </div>
                                
                                <div class="hidden md:block text-right">
                                    <div class="text-[10px] font-bold uppercase text-gray-400 tracking-widest mb-1">Estado</div>
                                    <div class="flex items-center gap-2 justify-end">
                                        <span class="w-3 h-3 bg-red-500 rounded-full animate-pulse shadow-[0_0_10px_#ef4444]"></span>
                                        <span class="text-white font-bold uppercase tracking-wider">En Vivo</span>
                                    </div>
                                </div>
                            </div>
                         </div>
                    </div>

                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-10 items-center">
                        <div>
                             <h4 class="text-xl font-bold text-black dark:text-white uppercase font-display mb-6 border-b border-gray-200 dark:border-gray-800 pb-2">
                                Resumen del Evento
                             </h4>
                             <ul class="space-y-4">
                                <li class="flex items-center gap-4 group">
                                    <div class="w-10 h-10 rounded bg-gray-100 dark:bg-white/5 flex items-center justify-center text-[var(--rankit-neon)] group-hover:bg-[var(--rankit-neon)] group-hover:text-white transition">
                                        <i class="ph-fill ph-trophy text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-[10px] uppercase font-bold text-gray-500">Prize Pool</div>
                                        <div class="text-sm font-bold text-black dark:text-white">$25,000 USD</div>
                                    </div>
                                </li>
                                <li class="flex items-center gap-4 group">
                                    <div class="w-10 h-10 rounded bg-gray-100 dark:bg-white/5 flex items-center justify-center text-[var(--rankit-neon)] group-hover:bg-[var(--rankit-neon)] group-hover:text-white transition">
                                        <i class="ph-fill ph-users text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-[10px] uppercase font-bold text-gray-500">Equipos</div>
                                        <div class="text-sm font-bold text-black dark:text-white">16 Participantes</div>
                                    </div>
                                </li>
                             </ul>
                        </div>
                        
                        <div class="flex flex-col gap-4">
                            <Link :href="route('tournaments.show', 1)" class="w-full py-5 text-center btn-skew font-bold text-white uppercase tracking-widest text-sm group">
                                <span class="btn-content flex items-center justify-center gap-2">
                                    Ver Torneo Completo <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition"></i>
                                </span>
                            </Link>
                            
                            <p class="text-center text-[10px] text-gray-500 uppercase tracking-wider mt-2">
                                Acceso para jugadores y espectadores
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* ESTILOS COPIADOS DE TU PROYECTO PARA MANTENER LA CONSISTENCIA */
:root { --rankit-neon: #bf00ff; }
.font-display { font-family: "Chakra Petch", sans-serif; }

/* Brutal Card Style */
.brutal-card {
  border: 1px solid;
  transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.dark .brutal-card {
  background: #0a0a0a;
  border-color: #333;
}
html:not(.dark) .brutal-card {
  background: #ffffff;
  border-color: #e5e5e5;
  box-shadow: 4px 4px 0px #00000010;
}
.brutal-card:hover {
  border-color: var(--rankit-neon);
  transform: translate(-2px, -2px);
}
.dark .brutal-card:hover {
  box-shadow: 0 0 20px rgba(191, 0, 255, 0.15);
}
html:not(.dark) .brutal-card:hover {
  box-shadow: 6px 6px 0px var(--rankit-neon);
}

/* Button Skew Style */
.btn-skew {
  background-color: var(--rankit-neon);
  transform: skewX(-10deg);
  transition: all 0.2s;
  display: inline-block;
  cursor: pointer;
}
.btn-skew:hover {
  background-color: white;
  color: black;
  box-shadow: 0 0 15px var(--rankit-neon);
}
html:not(.dark) .btn-skew:hover {
  background-color: black;
  color: white;
  box-shadow: 4px 4px 0px rgba(0,0,0,0.2);
}
.btn-content {
  display: inline-block;
  transform: skewX(10deg);
}
</style>