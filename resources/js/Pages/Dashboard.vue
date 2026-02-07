<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

const props = defineProps<{
    myTournaments?: any[];
    publicTournaments?: any[];
}>();

const user = usePage().props.auth.user;
const isOrganizer = computed(() => user.role === 'admin' || user.role === 'organizer' || user.email === '18jangel18@gmail.com');

const searchQuery = ref('');
const filteredPublicTournaments = computed(() => {
    if (!props.publicTournaments) return [];
    if (!searchQuery.value) return props.publicTournaments;
    const q = searchQuery.value.toLowerCase();
    return props.publicTournaments.filter(t => 
        t.name.toLowerCase().includes(q) || 
        (t.game && t.game.toLowerCase().includes(q)) ||
        (t.creator_name && t.creator_name.toLowerCase().includes(q))
    );
});

onMounted(() => {
    document.documentElement.classList.add('dark');
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <div class="min-h-screen bg-[#050505] text-white py-12 font-sans selection:bg-[var(--rankit-neon)] selection:text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
                
                <!-- HEADER & ACTIONS -->
                <div class="brutal-card bg-[#0a0a0a] p-8 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-[var(--rankit-neon)]/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
                    
                    <div class="relative z-10 text-center md:text-left">
                        <h2 class="text-4xl md:text-5xl font-display font-black italic uppercase tracking-tighter mb-2">
                            Hola, <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">{{ user.name }}</span> <span class="not-italic">👋</span>
                        </h2>
                        <p class="text-gray-400 font-bold uppercase tracking-widest text-xs">
                            Bienvenido a tu panel de control
                        </p>
                    </div>

                    <div v-if="isOrganizer" class="flex flex-wrap justify-center gap-4 relative z-10">
                        <Link :href="route('jangel.indexdos')" class="btn-skew px-6 py-3 bg-[var(--rankit-neon)] hover:bg-white hover:text-black text-white font-black uppercase tracking-wider text-xs transition-all duration-300 shadow-[4px_4px_0px_rgba(255,255,255,0.2)] hover:shadow-[6px_6px_0px_rgba(255,255,255,0.4)] hover:-translate-y-1">
                            <div class="btn-content flex items-center gap-2">
                                <i class="ph-bold ph-kanban text-lg"></i>
                                Panel Organizador
                            </div>
                        </Link>
                        <Link :href="route('profile.edit')" class="btn-skew px-6 py-3 bg-[#1a1a1a] border border-white/10 hover:border-[var(--rankit-neon)] text-gray-300 hover:text-[var(--rankit-neon)] font-bold uppercase tracking-wider text-xs transition-all duration-300">
                            <div class="btn-content flex items-center gap-2">
                                <i class="ph-bold ph-credit-card text-lg"></i>
                                Pagos
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- PUBLIC TOURNAMENTS SECTION -->
                <div class="space-y-6">
                    <div class="flex flex-col md:flex-row justify-between items-end gap-4 border-b border-white/10 pb-4">
                        <div>
                            <h3 class="text-2xl font-display font-black italic uppercase tracking-tighter text-white flex items-center gap-3">
                                <i class="ph-bold ph-globe text-[var(--rankit-neon)]"></i> Torneos Públicos
                            </h3>
                            <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Explora y compite</p>
                        </div>
                        <div class="relative w-full md:w-72">
                            <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                            <input v-model="searchQuery" type="text" placeholder="BUSCAR TORNEO..." 
                                   class="w-full pl-10 pr-4 py-3 bg-[#0a0a0a] border border-white/10 focus:border-[var(--rankit-neon)] focus:ring-0 text-xs font-bold uppercase text-white placeholder-gray-600 transition-all outline-none" />
                        </div>
                    </div>

                    <div v-if="filteredPublicTournaments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="tournament in filteredPublicTournaments" :key="tournament.id" 
                             class="brutal-card group bg-[#0a0a0a] p-0 h-full flex flex-col justify-between hover:z-10 relative overflow-hidden transition-all duration-500">
                            
                            <!-- Background Image -->
                            <div v-if="tournament.image_path" class="absolute inset-0 z-0">
                                <img :src="tournament.image_path" class="w-full h-full object-cover opacity-40 group-hover:opacity-60 transition-opacity duration-500 transform group-hover:scale-110" />
                                <div class="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/90 to-transparent"></div>
                            </div>

                            <div class="relative z-10 p-5 flex flex-col h-full justify-between">
                                <div>
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="flex gap-1 flex-wrap">
                                            <span class="bg-white/5 border border-white/10 text-[var(--rankit-neon)] text-[9px] font-black px-2 py-1 uppercase tracking-wider backdrop-blur-sm">
                                                {{ tournament.game_type || tournament.game || 'GENERAL' }}
                                            </span>
                                            <span v-if="tournament.entry_fee > 0" class="bg-green-500/10 border border-green-500/30 text-green-500 text-[9px] font-black px-2 py-1 uppercase tracking-wider backdrop-blur-sm">
                                                ${{ tournament.entry_fee }}
                                            </span>
                                            <span v-if="tournament.has_prizes" class="bg-yellow-500/10 border border-yellow-500/30 text-yellow-500 text-[9px] font-black px-2 py-1 uppercase tracking-wider backdrop-blur-sm">
                                                <i class="ph-bold ph-trophy"></i>
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-gray-500 font-mono font-bold">{{ new Date(tournament.created_at).toLocaleDateString() }}</span>
                                    </div>
                                    
                                    <h4 class="text-xl font-display font-black italic uppercase text-white mb-2 leading-none group-hover:text-[var(--rankit-neon)] transition-colors truncate">
                                        {{ tournament.name }}
                                    </h4>
                                    <p class="text-[9px] text-gray-500 font-bold uppercase tracking-wider mb-6 flex items-center gap-1">
                                        CREATED BY {{ tournament.creator_name || 'ANONYMOUS' }}
                                    </p>
                                </div>

                                <Link :href="`/t/${tournament.slug || tournament.id}`"
                                   class="block w-full text-center py-3 bg-[#111]/80 backdrop-blur-md border border-white/10 text-gray-400 text-[10px] font-black uppercase tracking-widest hover:bg-[var(--rankit-neon)] hover:text-white hover:border-[var(--rankit-neon)] transition-all duration-200 shadow-lg">
                                    VER DETALLES
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div v-else class="py-12 text-center border border-dashed border-white/10 bg-[#0a0a0a]">
                        <p class="text-gray-500 text-xs font-bold uppercase sticky">No se encontraron torneos públicos.</p>
                    </div>
                </div>

                <!-- MY TOURNAMENTS SECTION -->
                <div v-if="myTournaments && myTournaments.length > 0" class="space-y-6 pt-8 border-t border-dashed border-white/10">
                    <div>
                        <h3 class="text-2xl font-display font-black italic uppercase tracking-tighter text-white flex items-center gap-3">
                            <i class="ph-bold ph-trophy text-yellow-500"></i> Mis Torneos
                        </h3>
                        <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Gestiona tus competencias</p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="tournament in myTournaments" :key="tournament.id" 
                             class="brutal-card bg-[#0a0a0a] p-0 hover:bg-[#0f0f0f] overflow-hidden relative group">
                            
                            <!-- Background Image -->
                            <div v-if="tournament.image_path" class="absolute inset-0 z-0">
                                <img :src="tournament.image_path" class="w-full h-full object-cover opacity-30 group-hover:opacity-50 transition-opacity duration-500" />
                                <div class="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/90 to-transparent"></div>
                            </div>

                            <div class="relative z-10 p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <h4 class="text-xl font-display font-black italic uppercase text-white truncate pr-2" :title="tournament.name">
                                        {{ tournament.name }}
                                    </h4>
                                    <span class="px-2 py-1 text-[9px] font-black uppercase tracking-wider border backdrop-blur-sm"
                                          :class="tournament.is_private ? 'border-red-500/30 text-red-500 bg-red-500/10' : 'border-green-500/30 text-green-500 bg-green-500/10'">
                                        {{ tournament.is_private ? 'PRIVADO' : 'PÚBLICO' }}
                                    </span>
                                </div>
                                
                                <div class="space-y-2 mb-6">
                                    <p class="text-xs text-gray-400 font-bold uppercase">
                                        <span class="text-gray-600">JUEGO:</span> {{ tournament.game || tournament.game_type || 'GENERAL' }}
                                    </p>
                                    <p v-if="tournament.twitch_channel" class="text-xs text-[#a970ff] font-bold uppercase flex items-center gap-1">
                                        <i class="ph-bold ph-twitch-logo"></i> {{ tournament.twitch_channel }}
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <Link :href="route('public.live', tournament.id)" 
                                          class="py-2 px-3 bg-[#1a1a1a]/80 backdrop-blur hover:bg-white hover:text-black text-white text-[10px] font-bold uppercase tracking-wider text-center transition-colors border border-white/5">
                                        LIVE VIEW
                                    </Link>
                                    <Link v-if="isOrganizer" :href="route('jangel.indexdos')" 
                                          class="py-2 px-3 border border-white/20 hover:border-[var(--rankit-neon)] hover:text-[var(--rankit-neon)] text-gray-400 text-[10px] font-bold uppercase tracking-wider text-center transition-colors backdrop-blur-sm">
                                        GESTIONAR
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-else-if="isOrganizer" class="py-16 text-center bg-[#0a0a0a] border border-dashed border-white/10 brutal-card group">
                    <i class="ph-duotone ph-game-controller text-5xl text-gray-700 mb-4 group-hover:text-[var(--rankit-neon)] transition-colors"></i>
                    <p class="text-gray-500 text-sm font-bold uppercase mb-6">Aún no has creado ningún torneo.</p>
                    <Link :href="route('jangel.indexdos')" class="inline-block px-6 py-3 bg-white text-black font-black uppercase text-xs tracking-wider hover:bg-[var(--rankit-neon)] hover:text-white transition-all transform hover:-translate-y-1">
                        Crear mi primer torneo
                    </Link>
                </div>


                <!-- FEATURED EVENT (BellzCup) -->
                <div class="pt-12 border-t border-white/10">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em]">Evento Destacado</h3>
                        <span class="w-full h-px bg-gradient-to-r from-gray-800 to-transparent ml-4"></span>
                    </div>
                    
                    <div class="brutal-card w-full bg-[#0a0a0a] border border-white/10 overflow-hidden relative group h-[250px] md:h-[350px]">
                        <!-- Imagen de Fondo -->
                        <div class="absolute inset-0">
                            <img src="/BellzCupBeta/BannerBellzCup.png" 
                                 alt="Banner BellzCup"
                                 class="w-full h-full object-cover opacity-50 grayscale group-hover:grayscale-0 group-hover:opacity-80 group-hover:scale-105 transition-all duration-[1.5s] ease-out" 
                                 onerror="this.src='https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80'"/>
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-transparent mix-blend-multiply"></div>
                            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-transparent to-transparent"></div>
                        </div>
                        
                        <div class="absolute bottom-0 left-0 p-8 md:p-12 w-full max-w-2xl">
                            <div class="overflow-hidden mb-2">
                                <span class="bg-[var(--rankit-neon)] text-white text-[10px] font-black uppercase px-3 py-1 inline-block transform -skew-x-12">Oficial</span>
                            </div>
                            <h2 class="text-5xl md:text-7xl font-display font-black text-white uppercase italic tracking-tighter mb-4 leading-[0.9]">
                                Bellz<span class="text-transparent text-stroke">Cup</span>
                            </h2>
                            <p class="text-gray-300 font-bold uppercase text-xs tracking-wider mb-6 max-w-md line-clamp-2">
                                Participa en el torneo más competitivo de la temporada. Premios exclusivos y transmisión en vivo.
                            </p>
                            
                            <div class="flex gap-4">
                                <Link :href="route('tournaments.show', 1)" class="btn-skew px-8 py-4 bg-white hover:bg-[var(--rankit-neon)] hover:text-white text-black font-black uppercase text-sm transition-all shadow-[6px_6px_0px_rgba(0,0,0,0.5)] hover:shadow-[8px_8px_0px_rgba(0,0,0,0.5)] hover:-translate-y-1">
                                    <span class="btn-content">Ver Detalles</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700;900&display=swap');

.font-display { font-family: 'Outfit', sans-serif; }

.brutal-card {
  border: 1px solid rgba(255,255,255,0.05);
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  position: relative;
}

.brutal-card:hover {
  transform: translate(-4px, -4px);
  box-shadow: 6px 6px 0px var(--rankit-neon), 6px 6px 20px rgba(0,0,0,0.5);
  border-color: var(--rankit-neon);
}

.text-stroke {
  -webkit-text-stroke: 1px white;
  color: transparent;
}

.btn-skew {
    transform: skewX(-10deg);
}

.btn-content {
    transform: skewX(10deg);
}
</style>