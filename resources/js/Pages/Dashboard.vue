<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="BellzCup - Dashboard" />

    <AuthenticatedLayout>
        <!-- Contenedor Principal Centrado -->
        <div class="min-h-[calc(100vh-80px)] flex flex-col items-center justify-center p-4 relative overflow-hidden">
            
            <!-- Elementos de fondo decorativos -->
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-[var(--rankit-neon)]/10 rounded-full blur-[100px] pointer-events-none"></div>

            <!-- Tarjeta Principal "BellzCup" -->
            <div class="brutal-card w-full max-w-5xl bg-[#0a0a0a] border border-gray-800 overflow-hidden relative group">
                
                <!-- Imagen de Fondo (Con desenfoque y colores originales) -->
                <div class="relative h-[400px] md:h-[500px] overflow-hidden">
                    <img src="public/BellzCupBeta/BannerBellzCup.png" 
                         alt="Banner BellzCup"
                         class="w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-[1.5s] ease-out blur-[2px]" 
                         onerror="this.src='https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80'"/>
                    
                    <!-- Overlay Gradiente -->
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent"></div>

                    <!-- Contenido Sobre la Imagen -->
                    <div class="absolute bottom-0 left-0 flex flex-col items-end justify-between w-full gap-8 p-8 md:p-12 md:flex-row">
                        
                        <!-- Texto Principal -->
                        <div class="relative z-10 max-w-2xl">
                            <div class="flex flex-wrap items-center gap-3 mb-4">
                                <span class="bg-[var(--rankit-neon)] text-white text-[10px] font-bold uppercase px-3 py-1 btn-skew tracking-widest">
                                    <span class="btn-content">Evento Principal</span>
                                </span>
                                <span class="flex items-center gap-2 text-[10px] font-bold uppercase text-red-500 tracking-widest bg-black/50 px-3 py-1 border border-red-500/30 backdrop-blur-sm">
                                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-[0_0_10px_red]"></span>
                                    En Vivo
                                </span>
                                <!-- Badge de recompensas MUY VISIBLE -->
                                <span class="bg-yellow-400 text-black text-[11px] font-black uppercase px-4 py-1 btn-skew tracking-tighter animate-bounce shadow-[0_0_20px_rgba(250,204,21,0.5)]">
                                    <span class="btn-content">🎁 SKINS + PREMIOS EN STREAM</span>
                                </span>
                            </div>

                            <h1 class="text-6xl md:text-8xl font-display font-black text-white uppercase italic tracking-tighter leading-[0.9] mb-4">
                                Bellz<span class="text-transparent text-stroke">Cup</span>
                            </h1>

                            <p class="max-w-lg pl-4 text-sm font-medium leading-relaxed text-gray-100 border-l-4 border-yellow-500 md:text-lg drop-shadow-lg">
                                Ve la BellzCup y gana <span class="italic font-bold text-yellow-400 underline">Skins exclusivas y dinero en efectivo</span> solo por ver el stream. Creadores y actores de doblaje compitiendo en modalidades que nunca han jugado.
                            </p>
                        </div>

                        <!-- Botón de Acción -->
                        <div class="relative z-10 w-full md:w-auto">
                            <Link :href="route('tournaments.show', 1)" class="block w-full px-8 py-5 text-center text-black transition-colors bg-white md:w-auto btn-skew group hover:bg-yellow-400">
                                <span class="flex flex-col items-center btn-content">
                                    <span class="flex items-center gap-2 text-xl font-bold tracking-widest uppercase">
                                        VER Y GANAR <i class="transition-transform ph-bold ph-play-circle group-hover:scale-110"></i>
                                    </span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider mt-1">Sintoniza el Directo</span>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Footer de la Tarjeta (RECALCADO) -->
                <div class="grid grid-cols-2 gap-4 p-6 border-t bg-black/95 border-white/10 md:grid-cols-4 backdrop-blur-xl">
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Premio Jugadores</span>
                        <span class="text-xl font-bold text-white font-display">$1,200</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Formato</span>
                        <span class="text-xl font-bold text-white font-display">Battle Royale</span>
                    </div>
                    <!-- SECCIÓN VIEWERS EN GRANDE -->
                    <div class="flex flex-col p-2 rounded-lg border border-yellow-500/30 bg-yellow-500/5 shadow-[inset_0_0_15px_rgba(234,179,8,0.1)]">
                        <span class="text-[10px] text-yellow-500 uppercase font-black tracking-widest flex items-center gap-1">
                            <i class="ph-fill ph-gift"></i> Premio Viewers
                        </span>
                        <span class="text-2xl font-black leading-tight text-yellow-400 font-display">
                            $600 + <span class="italic text-white">SKINS</span>
                        </span>
                    </div>
                    <div class="flex flex-col justify-center">
                         <div class="text-[10px] text-gray-500 uppercase font-bold tracking-wider mb-1">Patrocinado por</div>
                         <div class="flex flex-wrap items-center gap-3">
                             <span class="text-xs font-bold text-white/80">RanKit</span>
                             <span class="text-xs font-bold text-[var(--rankit-neon)]">bellz_z11</span>
                             <span class="text-xs font-bold text-white/80">GameStarXpo</span>
                         </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>