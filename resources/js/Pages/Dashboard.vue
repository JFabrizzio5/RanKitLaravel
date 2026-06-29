<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    misTorneos: any[];
    torneosPublicos: any[];
}>();

const searchQuery = ref('');

const filteredTorneos = computed(() => {
    if (!props.torneosPublicos) return [];
    if (!searchQuery.value) return props.torneosPublicos;
    return props.torneosPublicos.filter(t => 
        t.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || 
        (t.slug && t.slug.toLowerCase().includes(searchQuery.value.toLowerCase()))
    );
});
</script>

<template>
    <Head title="Hub del Jugador - Dashboard" />

    <AuthenticatedLayout>
        <div class="min-h-[calc(100vh-80px)] p-6 md:p-12 relative overflow-hidden">
            <!-- Fondo Decorativo -->
            <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-[var(--rankit-neon)]/10 rounded-full blur-[120px] pointer-events-none"></div>

            <div class="max-w-7xl mx-auto relative z-10 space-y-16">
                
                <!-- HEADER DASHBOARD -->
                <div>
                    <h1 class="text-4xl md:text-5xl font-display font-black text-white uppercase italic tracking-tighter mb-2">
                        Panel <span class="text-transparent text-stroke">Jugador</span>
                    </h1>
                    <p class="text-gray-400 font-chakra tracking-widest text-sm uppercase">Gestiona tus participaciones y descubre nuevos torneos</p>
                </div>

                <!-- SECCIÓN MIS TORNEOS -->
                <section v-if="misTorneos && misTorneos.length > 0">
                    <div class="flex items-center gap-4 mb-6">
                        <h2 class="text-2xl font-bold text-white uppercase tracking-widest font-mono">Mis Torneos (Registrado)</h2>
                        <div class="flex-1 h-px bg-gray-800"></div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="t in misTorneos" :key="t.id" class="bg-[#120422] border border-fuchsia-900/30 p-6 rounded-xl shadow-xl hover:border-fuchsia-500/50 transition-colors flex flex-col justify-between">
                            <div>
                                <h3 class="text-xl font-bold text-white uppercase font-display italic tracking-wide mb-2">{{ t.name }}</h3>
                                <p class="text-gray-500 text-sm mb-4 line-clamp-2">{{ t.rules ? 'Torneo con reglas personalizadas' : 'Torneo Estándar' }}</p>
                            </div>
                            <Link :href="route('tournaments.show', t.slug || t.id)" class="inline-block text-center bg-white text-black font-chakra font-bold text-xs py-3 px-6 uppercase tracking-widest hover:bg-fuchsia-500 hover:text-white transition-all btn-skew">
                                <span class="btn-content">Ver Mi Tabla</span>
                            </Link>
                        </div>
                    </div>
                </section>
                
                <!-- BUSCADOR PÚBLICO -->
                <section>
                    <div class="flex items-center gap-4 mb-6">
                        <h2 class="text-2xl font-bold text-white uppercase tracking-widest font-mono">Buscador de Torneos Públicos</h2>
                        <div class="flex-1 h-px bg-gray-800"></div>
                    </div>
                    
                    <div class="mb-8 relative max-w-2xl">
                        <input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="BUSCAR TORNEO POR NOMBRE..." 
                            class="w-full bg-black/40 border border-white/10 px-6 py-4 text-white font-chakra text-sm uppercase tracking-widest focus:outline-none focus:border-[var(--rankit-neon)] transition-colors placeholder-gray-600 rounded-lg"
                        >
                        <i class="ph ph-magnifying-glass absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 text-xl"></i>
                    </div>

                    <div v-if="filteredTorneos.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="t in filteredTorneos" :key="t.id" class="bg-[#0a0514] border border-gray-800 p-5 rounded-lg hover:border-white/30 transition-all group cursor-pointer flex flex-col justify-between h-full">
                            <div>
                                <div class="text-[10px] text-[var(--rankit-neon)] font-bold uppercase tracking-widest mb-2">{{ t.is_serialized ? 'Liga Seriada' : 'Torneo Público' }}</div>
                                <h3 class="text-lg font-bold text-white uppercase font-display tracking-wide mb-3">{{ t.name }}</h3>
                            </div>
                            <Link :href="route('tournaments.show', t.slug || t.id)" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest group-hover:text-white transition-colors flex items-center gap-2 mt-4">
                                Ver Detalles <i class="ph-bold ph-arrow-right"></i>
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 font-chakra font-bold text-sm uppercase tracking-widest py-10 text-center border border-gray-800 border-dashed rounded-xl">
                        No se encontraron torneos públicos
                    </div>
                </section>

                <!-- BELLZCUP (Secundario/Fondo) -->
                <section class="mt-20 pt-10 border-t border-gray-800/50">
                    <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest mb-4">Eventos Patrocinados Destacados</div>
                    
                    <div class="w-full max-w-4xl bg-[#0a0a0a] border border-gray-800 rounded-2xl overflow-hidden relative group opacity-80 hover:opacity-100 transition-opacity">
                        <!-- Imagen de Fondo -->
                        <div class="relative h-[250px] overflow-hidden">
                            <img src="public/BellzCupBeta/BannerBellzCup.png" 
                                 alt="Banner BellzCup"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-[1.5s] ease-out blur-[1px]" 
                                 onerror="this.src='https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80'"/>
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/80 to-transparent"></div>

                            <div class="absolute bottom-0 left-0 flex items-end justify-between w-full p-6">
                                <div>
                                    <h1 class="text-4xl font-display font-black text-white uppercase italic tracking-tighter leading-none mb-2">
                                        Bellz<span class="text-transparent text-stroke">Cup</span>
                                    </h1>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="bg-red-500 text-white text-[9px] font-bold uppercase px-2 py-0.5 rounded-sm flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> En Vivo
                                        </span>
                                        <span class="bg-yellow-400 text-black text-[9px] font-black uppercase px-2 py-0.5 rounded-sm">
                                            🎁 $600 Viewers + Skins
                                        </span>
                                    </div>
                                </div>
                                
                                <Link :href="route('tournaments.show', 1)" class="bg-white text-black font-chakra font-bold text-xs py-2 px-4 uppercase tracking-widest hover:bg-yellow-400 transition-colors rounded-sm">
                                    Ver Stream
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </AuthenticatedLayout>
</template>