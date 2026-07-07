<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps<{
    misTorneos: any[];
    torneosPublicos: any[];
}>();

const rankitLeagueTournaments = [
    { id: 1, name: 'Semana 1: Qualifiers', date: 'PROXIMAMENTE', image: 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80&w=800' },
    { id: 2, name: 'Semana 2: Qualifiers', date: 'PROXIMAMENTE', image: 'https://images.unsplash.com/photo-1534423861386-85a16f5d13fd?auto=format&fit=crop&q=80&w=800' },
    { id: 3, name: 'Repechaje (Wildcard)', date: 'PROXIMAMENTE', image: 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&q=80&w=800' },
    { id: 4, name: 'Gran Final', date: 'PROXIMAMENTE', image: 'https://images.unsplash.com/photo-1518609878373-06d740f60d8b?auto=format&fit=crop&q=80&w=800' }
];

const searchQuery = ref('');
const filterGame = ref('all');

const filteredTorneos = computed(() => {
    if (!props.torneosPublicos) return [];
    
    return props.torneosPublicos.filter(t => {
        const matchesSearch = !searchQuery.value || t.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || (t.slug && t.slug.toLowerCase().includes(searchQuery.value.toLowerCase()));
        
        const matchesGame = filterGame.value === 'all' || 
                           (t.name && t.name.toLowerCase().includes(filterGame.value.toLowerCase())) ||
                           (t.slug && t.slug.toLowerCase().includes(filterGame.value.toLowerCase()));
                           
        // Filtrar para no mostrar los de la liga aquí si ya se muestran en su sección
        const isLeague = t.slug && t.slug.startsWith('v0.1-rankit-league-');
                           
        return matchesSearch && matchesGame && !isLeague;
    });
});

// Enlazar dinámicamente las 4 cartas de la Pro League con la Base de Datos
const dynamicLeagueTournaments = computed(() => {
    const list = [
        { key: 'clasificatorio-1', defaultName: 'Semana 1: Qualifiers', defaultImage: 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80&w=800' },
        { key: 'clasificatorio-2', defaultName: 'Semana 2: Qualifiers', defaultImage: 'https://images.unsplash.com/photo-1534423861386-85a16f5d13fd?auto=format&fit=crop&q=80&w=800' },
        { key: 'repechaje', defaultName: 'Repechaje (Wildcard)', defaultImage: 'https://images.unsplash.com/photo-1511512578047-dfb367046420?auto=format&fit=crop&q=80&w=800' },
        { key: 'gran-final', defaultName: 'Gran Final', defaultImage: 'https://images.unsplash.com/photo-1518609878373-06d740f60d8b?auto=format&fit=crop&q=80&w=800' }
    ];

    return list.map(item => {
        const match = props.torneosPublicos?.find(t => t.slug === `v0.1-rankit-league-${item.key}`);
        if (match) {
            return {
                id: match.id,
                name: match.name,
                date: match.is_private ? '🔒 PRIVADO' : '🟢 ABIERTO',
                image: match.banner_image ? `/storage/${match.banner_image}` : item.defaultImage,
                link: `/t/${match.id}`
            };
        }
        return {
            id: null,
            name: item.defaultName,
            date: 'PROXIMAMENTE',
            image: item.defaultImage,
            link: '/league'
        };
    });
});
</script>

<template>
    <Head title="Rankit Dashboard" />

    <AuthenticatedLayout>
        <div class="min-h-[calc(100vh-80px)] bg-[#050505] text-white overflow-hidden" style="font-family: 'Archivo', sans-serif;">
            
            <!-- Hero Section con Crear Torneo -->
            <div class="relative py-20 px-6 lg:px-16 border-b border-white/5 bg-[#0a0a0a]">
                <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1538481199705-c710c4e965fc?auto=format&fit=crop&q=80')] bg-cover bg-center opacity-[0.03] pointer-events-none"></div>
                <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-l from-[var(--rankit-neon)]/10 to-transparent pointer-events-none blur-[100px]"></div>
                
                <div class="max-w-7xl mx-auto relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                    <div>
                        <h1 class="text-4xl md:text-5xl font-black uppercase italic tracking-tighter mb-4" style="font-family: 'Chakra Petch', sans-serif;">
                            RANKIT <span class="text-[var(--rankit-neon)]">DASHBOARD</span>
                        </h1>
                        <p class="text-gray-400 text-sm md:text-base max-w-xl">
                            Gestiona tus torneos, explora ligas públicas y compite al más alto nivel. Todo tu ecosistema competitivo en un solo lugar.
                        </p>
                    </div>
                    <div class="shrink-0 flex gap-4" v-if="$page.props.auth?.user?.email === '18jangel18@gmail.com' || ['admin', 'superadmin'].includes($page.props.auth?.user?.role || '')">
                        <Link href="/game-selector" class="px-8 py-4 bg-white text-black font-black uppercase tracking-widest text-sm rounded hover:bg-gray-200 transition-all transform hover:scale-105 shadow-[0_0_20px_rgba(255,255,255,0.2)]" style="transform: skewX(-10deg);">
                            <span style="transform: skewX(10deg);" class="block flex items-center gap-2">
                                <i class="ph-bold ph-plus"></i> CREAR TORNEO
                            </span>
                        </Link>
                    </div>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-6 lg:px-16 py-16 space-y-20">
                
                <!-- Rankit League Carousel -->
                <section>
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-2xl font-black uppercase tracking-tighter" style="font-family: 'Chakra Petch', sans-serif;">
                                RANKIT<span class="text-[var(--rankit-neon)]">.PRO</span> LEAGUE
                            </h2>
                            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">El Circuito Oficial</p>
                        </div>
                        <Link href="/league" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-[var(--rankit-neon)] transition-colors flex items-center gap-2">
                            Ver Portal <i class="ph-bold ph-arrow-right"></i>
                        </Link>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="torneo in dynamicLeagueTournaments" :key="torneo.name" class="group relative overflow-hidden rounded-xl border border-white/5 bg-[#111] hover:border-[var(--rankit-neon)]/50 transition-all duration-300">
                            <div class="aspect-video overflow-hidden">
                                <img :src="torneo.image" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 group-hover:scale-110 transition-all duration-700" alt="Banner del torneo">
                            </div>
                            <div class="p-5">
                                <div class="text-[10px] text-[var(--rankit-neon)] font-black uppercase tracking-widest mb-1">{{ torneo.date }}</div>
                                <h3 class="text-lg font-bold uppercase font-display tracking-tight text-white mb-3">{{ torneo.name }}</h3>
                                <Link :href="torneo.link" class="inline-block px-4 py-2 bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-bold uppercase tracking-wider rounded text-white transition-colors">
                                    Ver Clasificación
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Buscador de Torneos Públicos -->
                <section>
                    <div class="flex items-center gap-4 mb-8">
                        <div>
                            <h2 class="text-2xl font-black uppercase tracking-tighter" style="font-family: 'Chakra Petch', sans-serif;">
                                EXPLORAR <span class="text-gray-400">EVENTOS</span>
                            </h2>
                            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Torneos de la Comunidad</p>
                        </div>
                        <div class="flex-1 h-px bg-white/5 ml-4"></div>
                    </div>
                    
                    <div class="mb-8 flex flex-col sm:flex-row gap-4 relative max-w-3xl">
                        <div class="relative flex-1">
                            <input 
                                v-model="searchQuery" 
                                type="text" 
                                placeholder="Buscar torneo por nombre..." 
                                class="w-full bg-[#111] border border-white/10 px-6 py-4 text-white text-sm uppercase tracking-widest focus:outline-none focus:border-[var(--rankit-neon)] transition-colors placeholder-gray-600 rounded"
                            >
                            <i class="ph ph-magnifying-glass absolute right-6 top-1/2 -translate-y-1/2 text-gray-400 text-xl"></i>
                        </div>
                        <select v-model="filterGame" class="bg-[#111] border border-white/10 px-6 py-4 text-white text-sm uppercase tracking-widest focus:outline-none focus:border-[var(--rankit-neon)] transition-colors rounded sm:w-48 appearance-none cursor-pointer">
                            <option value="all">TODOS LOS JUEGOS</option>
                            <option value="fortnite">FORTNITE</option>
                            <option value="valorant">VALORANT</option>
                            <option value="lol">LEAGUE OF LEGENDS</option>
                            <option value="fc24">EA FC 24</option>
                        </select>
                    </div>

                    <div v-if="filteredTorneos.length > 0" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <div v-for="t in filteredTorneos" :key="t.id" class="bg-[#111] border border-white/5 p-5 rounded hover:border-white/30 transition-all group cursor-pointer flex flex-col justify-between">
                            <div>
                                <div class="text-[10px] text-[var(--rankit-neon)] font-bold uppercase tracking-widest mb-2">{{ t.is_serialized ? 'Liga Seriada' : 'Torneo Público' }}</div>
                                <h3 class="text-base font-bold text-white uppercase font-display tracking-wide mb-3 line-clamp-2">{{ t.name }}</h3>
                            </div>
                            <Link :href="`/t/${t.id}`" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest group-hover:text-white transition-colors flex items-center gap-2 mt-4">
                                Ver Detalles <i class="ph-bold ph-arrow-right"></i>
                            </Link>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 font-bold text-sm uppercase tracking-widest py-12 text-center border border-white/5 border-dashed rounded">
                        No se encontraron torneos públicos
                    </div>
                </section>
                
                <!-- Mis Torneos -->
                <section v-if="misTorneos && misTorneos.length > 0">
                    <div class="flex items-center gap-4 mb-8">
                        <div>
                            <h2 class="text-2xl font-black uppercase tracking-tighter" style="font-family: 'Chakra Petch', sans-serif;">
                                MIS <span class="text-gray-400">TORNEOS</span>
                            </h2>
                            <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mt-1">Donde estás compitiendo</p>
                        </div>
                        <div class="flex-1 h-px bg-white/5 ml-4"></div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div v-for="t in misTorneos" :key="t.id" class="bg-gradient-to-br from-[#1a0a2e] to-[#0a0414] border border-purple-500/20 p-6 rounded hover:border-purple-500/50 transition-colors flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-white uppercase font-display italic tracking-wide mb-2">{{ t.name }}</h3>
                                
                                <div class="grid grid-cols-2 gap-2 my-4">
                                    <div class="bg-black/30 p-2 rounded text-center">
                                        <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Partidas</div>
                                        <div class="text-lg font-black text-white font-chakra">0</div>
                                    </div>
                                    <div class="bg-black/30 p-2 rounded text-center">
                                        <div class="text-[10px] text-gray-500 uppercase font-bold tracking-widest">Win Rate</div>
                                        <div class="text-lg font-black text-white font-chakra">0%</div>
                                    </div>
                                </div>
                            </div>
                            <Link :href="`/t/${t.id}`" class="inline-block text-center bg-white text-black font-bold text-xs py-2 px-4 uppercase tracking-widest hover:bg-purple-500 hover:text-white transition-all rounded w-full">
                                Ver Mi Posición
                            </Link>
                        </div>
                    </div>
                </section>
                
            </div>
        </div>
    </AuthenticatedLayout>
</template>