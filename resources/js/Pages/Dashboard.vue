<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="BellzCup - Dashboard" />

    <AuthenticatedLayout>
        <!-- Contenedor Principal Centrado -->
        <div class="min-h-[calc(100vh-80px)] flex flex-col items-center justify-center p-4 relative overflow-hidden">
            
            <!-- Elementos de fondo decorativos (Opcional, para dar profundidad) -->
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-[var(--rankit-neon)]/10 rounded-full blur-[100px] pointer-events-none"></div>

            <!-- Tarjeta Principal "BellzCup" -->
            <div class="brutal-card w-full max-w-5xl bg-[#0a0a0a] border border-gray-800 overflow-hidden relative group">
                
                <!-- Imagen de Fondo con Efectos -->
                <div class="relative h-[400px] md:h-[500px] overflow-hidden">
                    <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80" 
                         class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-[1.5s] ease-out grayscale mix-blend-luminosity" />
                    
                    <!-- Overlay Gradiente -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>

                    <!-- Contenido Sobre la Imagen -->
                    <div class="absolute bottom-0 left-0 w-full p-8 md:p-12 flex flex-col md:flex-row items-end justify-between gap-8">
                        
                        <!-- Texto Principal -->
                        <div class="relative z-10 max-w-2xl">
                            <div class="flex items-center gap-3 mb-4">
                                <span class="bg-[var(--rankit-neon)] text-white text-[10px] font-bold uppercase px-3 py-1 btn-skew tracking-widest">
                                    <span class="btn-content">Evento Principal</span>
                                </span>
                                <span class="flex items-center gap-2 text-[10px] font-bold uppercase text-red-500 tracking-widest bg-black/50 px-3 py-1 border border-red-500/30 backdrop-blur-sm">
                                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-[0_0_10px_red]"></span>
                                    En Vivo
                                </span>
                            </div>

                            <h1 class="text-6xl md:text-8xl font-display font-black text-white uppercase italic tracking-tighter leading-[0.9] mb-4">
                                Bellz<span class="text-stroke text-transparent">Cup</span>
                            </h1>

                            <p class="text-gray-300 font-light text-sm md:text-lg border-l-2 border-[var(--rankit-neon)] pl-4 max-w-lg leading-relaxed drop-shadow-md">
                                La competencia de alto nivel ha comenzado. Consulta los brackets, estadísticas en tiempo real y el rendimiento de los equipos.
                            </p>
                        </div>

                        <!-- Botón de Acción -->
                        <div class="relative z-10 w-full md:w-auto">
                            <!-- Enlace al detalle del torneo -->
                            <!-- Ajusta el ID '1' al ID real de tu torneo si lo tienes dinámico -->
                            <Link :href="route('tournaments.show', 1)" class="w-full md:w-auto btn-skew px-8 py-5 text-center group block">
                                <span class="btn-content flex flex-col items-center">
                                    <span class="text-xl font-bold uppercase tracking-widest flex items-center gap-2">
                                        Ver Torneo <i class="ph-bold ph-arrow-right group-hover:translate-x-1 transition-transform"></i>
                                    </span>
                                    <span class="text-[10px] opacity-70 uppercase tracking-wider mt-1">Acceder al Panel</span>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Footer de la Tarjeta (Datos Rápidos) -->
                <div class="bg-black/80 border-t border-white/10 p-6 grid grid-cols-2 md:grid-cols-4 gap-4 backdrop-blur-md">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Prize Pool</span>
                        <span class="text-xl text-white font-display font-bold">$25,000</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Equipos</span>
                        <span class="text-xl text-white font-display font-bold">16 / 16</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Formato</span>
                        <span class="text-xl text-white font-display font-bold">Battle Royale</span>
                    </div>
                    <div class="flex flex-col justify-center">
                         <div class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Patrocinado por</div>
                         <div class="flex gap-2 opacity-50 grayscale hover:grayscale-0 transition-all duration-500">
                             <!-- Logos placeholder pequeños -->
                             <div class="w-6 h-6 bg-white rounded-full"></div>
                             <div class="w-6 h-6 bg-white rounded-full"></div>
                             <div class="w-6 h-6 bg-white rounded-full"></div>
                         </div>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>