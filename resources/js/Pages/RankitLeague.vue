<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted, nextTick } from "vue";
import axios from "axios";

// ---- SPA Navigation ----
const activeView = ref("home");

const navigate = (view) => {
    activeView.value = view;
    nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
};

// ---- League Standings ----
const allSerializedTournaments = ref([]);
const activeTournamentIndex = ref(0);
const loading = ref(true);

const selectTournament = (index) => {
    activeTournamentIndex.value = index;
};

// ---- Carousel ----
const selectedWeek = ref(1);

const scrollCarousel = (direction) => {
    const carousel = document.getElementById('league-carousel');
    if (carousel) carousel.scrollBy({ left: direction * 360, behavior: 'smooth' });
};

const leagueData = {
    1: {
        badge: "Fase Inicial", title: "SEMANA 1",
        desc_short: "El inicio de la liga regular. Compite en el primer corte gratuito o aprovecha el tier Premium para asegurar tu cupo.",
        color: "text-fnBrightPurple", borderColor: "border-fnBrightPurple",
        poster: "/public/league/clasificatorio-1.png",
        desc1: "<p>Las primeras 3 partidas son <strong class='text-white'>GRATIS</strong> para buscar clasificación. Con el Upgrade Premium, juegas <strong class='text-white'>2 partidas extras (5 en total)</strong> y participas por el pozo de dinero semanal.</p><p>El lobby es privado. Prohibido el teaming. Formato Solos.</p>",
        desc2: "<p>Hay dos cortes de clasificación:</p><ul class='space-y-2 mt-2 text-sm text-gray-300'><li>✔ <strong>Corte Free (1-3):</strong> Top 10% general avanza.</li><li>👑 <strong>Corte Premium (4-5):</strong> Top 10% de pago avanza.</li></ul>"
    },
    2: {
        badge: "Segunda Oportunidad", title: "SEMANA 2",
        desc_short: "Misma modalidad, nueva tabla de puntos. Segundo llamado para el Top 10%.",
        color: "text-fnEmerald", borderColor: "border-fnEmerald",
        poster: "/public/league/clasificatorio-2.png",
        desc1: "<p>Mismo formato de Battle Royale Clásico en Solos. 3 partidas gratis + 2 Premium.</p>",
        desc2: "<ul class='space-y-2 mt-2 text-sm text-gray-300'><li>✔ <strong>Corte Free (1-3):</strong> Top 10% avanza.</li><li>👑 <strong>Corte Premium (4-5):</strong> Top 10% de pago avanza.</li></ul>"
    },
    3: {
        badge: "La Última Vida", title: "REPECHAJE",
        desc_short: "4 partidas: 2 BR Clásico + 2 Reload. Solo 8 tickets de clasificación en juego.",
        color: "text-fnCrimson", borderColor: "border-fnCrimson",
        poster: "/public/league/repechaje.png",
        desc1: "<p>Filtro final de versatilidad: <strong class='text-white'>4 partidas exactas</strong> (2 BR + 2 Reload).</p>",
        desc2: "<p>8 pases a la final:</p><ul class='space-y-2 mt-2 text-sm text-gray-300'><li>🏆 Ganadores de cada partida (4)</li><li>📈 Top 3 pts totales (3)</li><li>💀 MVP Kills (1)</li></ul>"
    },
    4: {
        badge: "El Cierre Absoluto", title: "GRAN FINAL",
        desc_short: "Solo los mejores. Top 15% de W1/W2 + ganadores del Repechaje.",
        color: "text-fnGold", borderColor: "border-fnGold",
        poster: "/public/league/gran-final.png",
        desc1: "<p>Battle Royale Clásico. Lobbies privados stacked. 6 partidas. No hay suplentes.</p>",
        desc2: "<p>El Mega Pozo se paga aquí. Además, el <strong class='text-white'>20% de toda la recaudación</strong> se sortea entre todos los participantes.</p>"
    }
};

const currentWeekData = ref(leagueData[1]);

const selectWeek = (weekNum) => {
    selectedWeek.value = weekNum;
    currentWeekData.value = leagueData[weekNum];
    // Scroll carousel card into view on mobile
    nextTick(() => {
        const card = document.getElementById(`card-w${weekNum}`);
        if (card && window.innerWidth < 768) {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    });
};

// ---- Prize Pool Calculator ----
const inputPlayers = ref(50);
const inputPrice = ref(10);

const prizeTotal = ref(0);
const prizeWeekly = ref(0);
const prizeFinal = ref(0);
const prizeGiveaways = ref(0);

const updatePrize = () => {
    const total = inputPlayers.value * inputPrice.value;
    prizeTotal.value = total;
    prizeWeekly.value = Math.round(total * 0.40);
    prizeFinal.value = Math.round(total * 0.40);
    prizeGiveaways.value = Math.round(total * 0.20);
};

// ---- Registration Tier ----
const selectedTier = ref('free');

onMounted(async () => {
    updatePrize();
    try {
        const res = await axios.get('/api/league/standings');
        allSerializedTournaments.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Rankit.Pro | League" />
    <div class="font-sans min-h-screen flex flex-col justify-between overflow-x-hidden bg-[#030108] text-[#e2e8f0]">
        

    <!-- Navbar Premium Ultra Clean -->
    <nav class="border-b border-white/5 bg-fnDark/80 backdrop-blur-xl sticky top-0 z-50 px-4 sm:px-8 py-4 transition-all">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            
            <div class="flex items-center gap-3">
                <img src="/public/league/logo.png" alt="Logo Rankit Pro" class="w-10 h-10 sm:w-12 sm:h-12 object-contain">
                <div class="flex flex-col">
                    <span class="font-block text-2xl sm:text-3xl tracking-wide text-white uppercase italic leading-none">
                        RANKIT<span class="text-fnBrightPurple">.PRO</span>
                    </span>
                    <span class="text-[9px] text-fnBrightPurple font-chakra font-bold tracking-[0.3em] uppercase ml-1 mt-1">Official League</span>
                </div>
            </div>

            <div class="hidden lg:flex space-x-8 font-chakra font-bold uppercase tracking-widest text-xs">
                <button @click="navigate('home')" id="nav-home" class="nav-link text-white transition-colors hover:text-white">INICIO</button>
                <button @click="navigate('league')" id="nav-league" class="nav-link text-gray-500 transition-colors hover:text-white">RANKIT.PRO LEAGUE</button>
                <button @click="navigate('others')" id="nav-others" class="nav-link text-gray-500 transition-colors hover:text-white">OTROS TORNEOS</button>
                <button @click="navigate('simulator')" id="nav-simulator" class="nav-link text-gray-500 transition-colors hover:text-white">PREMIOS</button>
                <a href="#" @click.prevent="navigate('register')" id="nav-register" class="nav-link text-gray-500 transition-colors hover:text-white">CLASIFICACIÓN</a>
            </div>

            <div>
                <a href="#" @click.prevent="navigate('register')" class="fn-btn inline-block bg-white text-black px-8 py-2.5 hover:bg-gray-200 transition-colors group relative overflow-hidden">
                    <span class="block font-chakra font-bold text-sm uppercase tracking-widest relative z-10">
                        COMPETIR AHORA
                    </span>
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/50 to-transparent translate-x-[-100%] group-hover:animate-[shimmer_1.5s_infinite]"></div>
                </a>
            </div>
        </div>
        
        <!-- Móvil -->
        <div class="flex lg:hidden justify-around mt-4 pt-4 border-t border-white/5 text-[10px] font-chakra font-bold uppercase tracking-wider text-gray-500">
            <button @click="navigate('home')" class="hover:text-white transition-colors"><i class="fa-solid fa-house block text-center mb-1 text-base"></i>Inicio</button>
            <button @click="navigate('league')" class="hover:text-white transition-colors"><i class="fa-solid fa-trophy block text-center mb-1 text-base"></i>League</button>
            <button @click="navigate('others')" class="hover:text-white transition-colors"><i class="fa-solid fa-gamepad block text-center mb-1 text-base"></i>Otros</button>
            <button @click="navigate('simulator')" class="hover:text-white transition-colors"><i class="fa-solid fa-coins block text-center mb-1 text-base"></i>Premios</button>
            <a href="#" @click.prevent="navigate('register')" class="hover:text-white transition-colors text-center"><i class="fa-solid fa-list-ol block text-center mb-1 text-base"></i>Clasific.</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-8 py-12 flex-grow w-full relative z-10">

        <!-- ================= PÁGINA 1: INICIO ================= -->
        <section id="view-home" class="view-section" v-show="activeView === 'home'">
            <div class="max-w-6xl mx-auto space-y-8 relative z-10 my-12 lg:my-24">
                
                <!-- Badge Elegante -->
                <div class="inline-flex items-center gap-3 border border-white/10 bg-white/5 px-4 py-1.5 rounded-full backdrop-blur-sm mb-6">
                    <span class="w-2 h-2 bg-white rounded-full animate-pulse shadow-[0_0_10px_white]"></span>
                    <span class="text-xs font-chakra font-bold text-gray-300 uppercase tracking-widest">ECOSISTEMA COMPETITIVO OFICIAL • SOLOS</span>
                </div>

                <!-- Tipografía colosal, limpia y épica -->
                <div class="font-block text-[5rem] sm:text-[8rem] lg:text-[11rem] leading-[0.8] tracking-tight text-white uppercase italic mb-10">
                    <div>GESTIONA.</div>
                    <div class="text-gray-500">COMPITE.</div>
                    <div class="gradient-text-epic">ESCALA.</div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-16 mt-20 items-center">
                    <div class="space-y-8">
                        <p class="text-gray-300 text-xl font-light leading-relaxed">
                            Rankit.Pro redefine el competitivo en <strong class="text-white font-semibold">Solitario</strong>. Un circuito de 4 semanas diseñado para filtrar a los verdaderos monstruos mecánicos y tácticos de la isla.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="#" @click.prevent="navigate('register')" class="fn-btn inline-block bg-fnBrightPurple text-white px-10 py-5 hover:bg-purple-600 transition-all font-chakra font-bold tracking-widest uppercase text-base text-center shadow-glowPurple relative group overflow-hidden">
                                <span class="relative z-10">¡INSCRIBETE AHORA!</span>
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent translate-x-[-100%] group-hover:animate-[shimmer_1.5s_infinite]"></div>
                            </a>
                            <button @click="navigate('league')" class="fn-btn border border-white/20 bg-transparent text-white px-8 py-5 hover:bg-white/5 transition-all font-chakra font-bold tracking-widest uppercase text-sm">
                                Descubrir Formato
                            </button>
                        </div>
                    </div>

                    <!-- Cuadro Info menos saturado, más tech/vidrio -->
                    <div class="premium-card p-10 relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-fnBrightPurple/20 blur-[50px] transition-all group-hover:bg-fnBrightPurple/40"></div>
                        
                        <h3 class="font-chakra font-bold italic text-2xl text-white mb-8 uppercase tracking-widest border-b border-white/10 pb-4">
                            Reglas de Clasificación
                        </h3>
                        <ul class="space-y-8 text-sm text-gray-400 font-medium relative z-10">
                            <li class="flex items-start gap-5">
                                <div class="text-white text-xl"><i class="fa-solid fa-crosshairs"></i></div>
                                <div>
                                    <strong class="text-white block font-chakra text-lg uppercase tracking-wider mb-1">Cortes de Clasificación (Sem 1 y 2)</strong>
                                    <span>Se evalúa en dos cortes: Las primeras 3 partidas gratis clasifican al <strong class="text-white">Top 10% general</strong>. Las partidas 4 y 5 (solo Premium) clasifican al <strong class="text-white">Top 10% exclusivo de pago</strong>.</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-5">
                                <div class="text-white text-xl"><i class="fa-solid fa-ticket"></i></div>
                                <div>
                                    <strong class="text-white block font-chakra text-lg uppercase tracking-wider mb-1">Repechaje (Semana 3)</strong>
                                    <span>Son 4 partidas (<strong class="text-white">2 Battle Royale y 2 Recarga</strong>). Clasifican: El ganador de cada partida, los 3 con mayor puntaje global, y el jugador con más kills que no haya clasificado aún.</span>
                                </div>
                            </li>
                            <li class="flex items-start gap-5">
                                <div class="text-white text-xl"><i class="fa-solid fa-sack-dollar"></i></div>
                                <div>
                                    <strong class="text-white block font-chakra text-lg uppercase tracking-wider mb-1">Final y Sorteos de Pozo</strong>
                                    <span>En la Gran Final (Semana 4), el mega pozo acumulado se reparte: 80% para premiación a los campeones y 20% en sorteos para toda la comunidad participante.</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================= PÁGINA 2: LIGA PRINCIPAL ================= -->
        <section id="view-league" class="view-section" v-show="activeView === 'league'">
            <div class="text-center mb-16">
                <h2 class="font-block text-5xl sm:text-7xl uppercase text-white tracking-wide mb-6">
                    CIRCUITO <span class="text-gray-500">PRO</span>
                </h2>
                <p class="text-gray-400 text-base max-w-2xl mx-auto leading-relaxed font-light">
                    Semanas 1 y 2 buscan consistencia. La Semana 3 busca adaptabilidad extrema. La Final corona al campeón absoluto. Todo en estricto formato Solitario.
                </p>
            </div>

            <!-- Carrusel Épico de Pósters -->
            <div class="relative w-full mb-16 group">
                <button @click="scrollCarousel(-1)" class="absolute left-0 top-1/2 -translate-y-1/2 -ml-4 z-30 bg-black/80 border border-white/20 text-white w-14 h-14 flex items-center justify-center rounded-full hover:bg-white hover:text-black transition-all hidden md:flex opacity-0 group-hover:opacity-100 backdrop-blur-md">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button @click="scrollCarousel(1)" class="absolute right-0 top-1/2 -translate-y-1/2 -mr-4 z-30 bg-black/80 border border-white/20 text-white w-14 h-14 flex items-center justify-center rounded-full hover:bg-white hover:text-black transition-all hidden md:flex opacity-0 group-hover:opacity-100 backdrop-blur-md">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

                <div id="league-carousel" class="flex gap-6 overflow-x-auto snap-x snap-mandatory hide-scrollbar py-8 px-4 items-center scroll-smooth">
                    
                    <!-- Póster Semana 1 -->
                    <div @click="selectWeek(1)" id="card-w1" :class="['shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden', selectedWeek === 1 ? 'opacity-100 shadow-glowPurple' : 'opacity-75 hover:opacity-100']">
                        <div class="noise-overlay"></div>
                        <img src="/public/league/clasificatorio-1.png" alt="Póster de fase clasificatoria de la semana 1 de Fortnite" class="absolute inset-0 w-full h-full object-cover opacity-75">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent z-10"></div>
                        
                        <div class="relative z-20 h-full flex flex-col p-8">
                            <div class="flex justify-between items-start mb-auto">
                                <span class="font-chakra font-bold text-white tracking-widest text-sm uppercase">SEMANA 01</span>
                                <span class="bg-white/10 backdrop-blur-md border border-white/20 text-white text-[10px] px-2 py-1 uppercase font-bold"><i class="fa-solid fa-user mr-1"></i> Solos</span>
                            </div>

                            <div class="mt-auto">
                                <div class="w-8 h-1 bg-fnBrightPurple mb-4 transition-all duration-300 group-hover:w-16"></div>
                                <h3 class="font-block text-4xl text-white uppercase leading-[0.9] mb-3">BATTLE<br>ROYALE</h3>
                                <p class="text-gray-400 text-xs font-chakra uppercase tracking-widest">Fase de Clasificación</p>
                                
                                <div class="mt-6 pt-6 border-t border-white/10">
                                    <span class="block text-[10px] text-gray-500 font-chakra uppercase mb-1">Clasificación</span>
                                    <span class="text-white text-sm font-bold uppercase">Top 10% Free / Top 10% Pro</span>
                                </div>
                            </div>
                        </div>
                        <div id="sel-w1" :class="['absolute inset-0 border border-fnBrightPurple transition-opacity duration-300 pointer-events-none z-30', selectedWeek === 1 ? 'opacity-100' : 'opacity-0']"></div>
                    </div>

                    <!-- Póster Semana 2 -->
                    <div @click="selectWeek(2)" id="card-w2" :class="['shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden', selectedWeek === 2 ? 'opacity-100 shadow-glowEmerald' : 'opacity-75 hover:opacity-100']">
                        <div class="noise-overlay"></div>
                        <img src="/public/league/clasificatorio-2.png" alt="Póster de segunda clasificatoria de la semana 2 de Fortnite" class="absolute inset-0 w-full h-full object-cover opacity-75">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent z-10"></div>
                        
                        <div class="relative z-20 h-full flex flex-col p-8">
                            <div class="flex justify-between items-start mb-auto">
                                <span class="font-chakra font-bold text-white tracking-widest text-sm uppercase">SEMANA 02</span>
                                <span class="bg-white/10 backdrop-blur-md border border-white/20 text-white text-[10px] px-2 py-1 uppercase font-bold"><i class="fa-solid fa-user mr-1"></i> Solos</span>
                            </div>

                            <div class="mt-auto">
                                <div class="w-8 h-1 bg-fnEmerald mb-4 transition-all duration-300 group-hover:w-16"></div>
                                <h3 class="font-block text-4xl text-white uppercase leading-[0.9] mb-3">BATTLE<br>ROYALE</h3>
                                <p class="text-fnEmerald/90 text-xs font-chakra uppercase tracking-widest">Segunda Oportunidad</p>
                                
                                <div class="mt-6 pt-6 border-t border-white/10">
                                    <span class="block text-[10px] text-gray-500 font-chakra uppercase mb-1">Clasificación</span>
                                    <span class="text-white text-sm font-bold uppercase">Top 10% Free / Top 10% Pro</span>
                                </div>
                            </div>
                        </div>
                        <div id="sel-w2" :class="['absolute inset-0 border border-fnEmerald transition-opacity duration-300 pointer-events-none z-30', selectedWeek === 2 ? 'opacity-100' : 'opacity-0']"></div>
                    </div>

                    <!-- Póster Semana 3 (Repechaje) -->
                    <div @click="selectWeek(3)" id="card-w3" :class="['shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden', selectedWeek === 3 ? 'opacity-100 shadow-glowCrimson' : 'opacity-75 hover:opacity-100']">
                        <div class="noise-overlay"></div>
                        <img src="/public/league/repechaje.png" alt="Póster de repechaje de la semana 3 de Fortnite" class="absolute inset-0 w-full h-full object-cover opacity-75">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent z-10"></div>
                        
                        <div class="relative z-20 h-full flex flex-col p-8">
                            <div class="flex justify-between items-start mb-auto">
                                <span class="font-chakra font-bold text-white tracking-widest text-sm uppercase">SEMANA 03</span>
                                <span class="bg-fnCrimson/10 backdrop-blur-md border border-fnCrimson/30 text-fnCrimson text-[10px] px-2 py-1 uppercase font-bold"><i class="fa-solid fa-bolt mr-1"></i> BR & RECARGA</span>
                            </div>

                            <div class="mt-auto">
                                <div class="w-8 h-1 bg-fnCrimson mb-4 transition-all duration-300 group-hover:w-16"></div>
                                <h3 class="font-block text-4xl text-white uppercase leading-[0.9] mb-3">REPECHAJE<br>ABSOLUTO</h3>
                                <p class="text-fnCrimson/80 text-xs font-chakra uppercase tracking-widest">Doble Disciplina</p>
                                
                                <div class="mt-6 pt-6 border-t border-white/10">
                                    <span class="block text-[10px] text-gray-500 font-chakra uppercase mb-1">Condición de Pase</span>
                                    <span class="text-white text-xs font-bold uppercase leading-tight">Wins, Puntos y Kills</span>
                                </div>
                            </div>
                        </div>
                        <div id="sel-w3" :class="['absolute inset-0 border border-fnCrimson transition-opacity duration-300 pointer-events-none z-30', selectedWeek === 3 ? 'opacity-100' : 'opacity-0']"></div>
                    </div>

                    <!-- Póster Semana 4 (Final) -->
                    <div @click="selectWeek(4)" id="card-w4" :class="['shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden', selectedWeek === 4 ? 'opacity-100 shadow-glowGold' : 'opacity-75 hover:opacity-100']">
                        <div class="noise-overlay"></div>
                        <img src="/public/league/gran-final.png" alt="Póster de gran final de la semana 4 de Fortnite" class="absolute inset-0 w-full h-full object-cover opacity-75">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/25 to-transparent z-10"></div>
                        
                        <div class="relative z-20 h-full flex flex-col p-8">
                            <div class="flex justify-between items-start mb-auto">
                                <span class="font-chakra font-bold text-fnGold tracking-widest text-sm uppercase">FINAL</span>
                                <span class="bg-fnGold/10 backdrop-blur-md border border-fnGold/30 text-fnGold text-[10px] px-2 py-1 uppercase font-bold"><i class="fa-solid fa-trophy mr-1"></i> Solos</span>
                            </div>

                            <div class="mt-auto">
                                <div class="w-8 h-1 bg-fnGold mb-4 transition-all duration-300 group-hover:w-16"></div>
                                <h3 class="font-block text-4xl text-white uppercase leading-[0.9] mb-3">GRAN<br>FINAL</h3>
                                <p class="text-fnGold/80 text-xs font-chakra uppercase tracking-widest">Custom Lobbies</p>
                                
                                <div class="mt-6 pt-6 border-t border-white/10 grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="block text-[10px] text-gray-500 font-chakra uppercase mb-1">Pozo Base</span>
                                        <span class="text-white text-sm font-bold uppercase">80% Bolsa</span>
                                    </div>
                                    <div>
                                        <span class="block text-[10px] text-gray-500 font-chakra uppercase mb-1">Sorteos</span>
                                        <span class="text-white text-sm font-bold uppercase">20% Bolsa</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="sel-w4" :class="['absolute inset-0 border border-fnGold transition-opacity duration-300 pointer-events-none z-30', selectedWeek === 4 ? 'opacity-100' : 'opacity-0']"></div>
                    </div>

                </div>
            </div>

            <!-- Panel de detalles Premium (Menos cajas sólidas, tipografía abierta) -->
            <div class="premium-card p-8 sm:p-12 relative overflow-hidden">
                <div class="flex flex-col md:flex-row gap-12 relative z-10">
                    
                    <div class="md:w-1/3 border-r border-white/10 pr-8">
                        <span :class="[currentWeekData.color, 'font-chakra font-bold text-xs uppercase tracking-widest mb-4 block']">{{ currentWeekData.badge }}</span>
                        <h3 class="font-block text-4xl text-white uppercase leading-none mb-6">{{ currentWeekData.title }}</h3>
                        <p class="text-gray-400 text-sm leading-relaxed mb-6">
                            {{ currentWeekData.desc_short }}
                        </p>
                        <div class="pt-4 border-t border-white/10">
                            <span class="text-[10px] font-chakra font-bold uppercase tracking-widest text-gray-500 mb-3 block">Póster de la fase</span>
                            <img :src="currentWeekData.poster" alt="Póster oficial de la fase seleccionada" class="w-full rounded-md border border-white/10 bg-black/30 object-cover">
                        </div>
                    </div>

                    <div class="md:w-2/3 grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div>
                            <h4 class="text-xs font-chakra font-bold uppercase tracking-widest text-gray-500 mb-4 border-b border-white/10 pb-2">Reglas de Emparejamiento</h4>
                            <div class="text-gray-300 text-sm leading-relaxed space-y-3" v-html="currentWeekData.desc1">
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-xs font-chakra font-bold uppercase tracking-widest text-gray-500 mb-4 border-b border-white/10 pb-2">Sistema de Clasificación</h4>
                            <div class="text-gray-300 text-sm leading-relaxed space-y-3" v-html="currentWeekData.desc2">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tablas Seriadas (Clasificación Actual) -->
            <div class="mt-20 max-w-6xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="font-block text-4xl sm:text-5xl uppercase text-white tracking-wide mb-4">
                        CLASIFICACIÓN <span class="text-fnBrightPurple">ACTUAL</span>
                    </h2>
                    <p class="text-gray-400 text-sm max-w-2xl mx-auto font-chakra uppercase tracking-widest">
                        Ranking en vivo de los torneos seriados de la liga.
                    </p>
                </div>

                <div v-if="loading" class="text-center text-fnBrightPurple py-10 font-chakra uppercase tracking-widest font-bold animate-pulse">
                    Cargando Clasificación...
                </div>
                
                <div v-else-if="allSerializedTournaments.length === 0" class="text-center text-gray-500 py-10 font-chakra uppercase tracking-widest font-bold border border-gray-800 border-dashed rounded-xl">
                    Aún no hay tablas de clasificación disponibles.
                </div>

                <div v-else class="space-y-16">
                    <div v-for="(league, index) in allSerializedTournaments" :key="index" class="premium-card p-6 md:p-10 relative overflow-hidden">
                        <h3 class="font-block text-3xl text-white uppercase italic mb-6 border-b border-white/10 pb-4">
                            {{ league.tournament.name }}
                        </h3>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left font-chakra text-sm">
                                <thead class="text-xs uppercase text-gray-500 border-b border-white/10">
                                    <tr>
                                        <th class="py-4 px-2">Pos</th>
                                        <th class="py-4 px-2">Jugador</th>
                                        <th class="py-4 px-2 text-center">Partidas</th>
                                        <th class="py-4 px-2 text-center">Kills</th>
                                        <th class="py-4 px-2 text-center">Daño</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(player, pIdx) in league.standings" :key="player.player_name" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                        <td class="py-4 px-2 font-bold" :class="pIdx < 3 ? 'text-fnBrightPurple' : 'text-gray-400'">#{{ pIdx + 1 }}</td>
                                        <td class="py-4 px-2 font-bold text-white uppercase">{{ player.player_name }}</td>
                                        <td class="py-4 px-2 text-center text-gray-400">{{ player.matches_played }}</td>
                                        <td class="py-4 px-2 text-center text-white font-bold">{{ player.total_kills || 0 }}</td>
                                        <td class="py-4 px-2 text-center text-gray-400">{{ player.total_damage || 0 }}</td>
                                    </tr>
                                    <tr v-if="!league.standings || league.standings.length === 0">
                                        <td colspan="5" class="text-center py-8 text-gray-500 uppercase tracking-widest">Sin registros de partidas</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <!-- ================= PÁGINA 3: OTROS TORNEOS ================= -->
        <section id="view-others" class="view-section" v-show="activeView === 'others'">
            <div class="text-center mb-16">
                <h2 class="font-block text-5xl sm:text-7xl uppercase text-white tracking-wide mb-6">
                    EVENTOS <span class="text-gray-500">COMUNIDAD</span>
                </h2>
                <p class="text-gray-400 text-base max-w-2xl mx-auto leading-relaxed font-light">
                    Torneos rápidos entre semana. Inscripciones bajas, bolsas de premio directas y acción garantizada en tus otros títulos favoritos.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <div class="premium-card p-8 flex flex-col justify-between group h-[300px] relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-1 h-full bg-red-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                    <div>
                        <span class="text-[10px] font-chakra font-bold uppercase text-red-400 mb-2 block tracking-widest">VALORANT</span>
                        <h3 class="font-block text-2xl text-white mb-4 uppercase">DM Showdown</h3>
                        <p class="text-gray-400 text-xs leading-relaxed">Deathmatch de todos contra todos. El jugador con más bajas tras 3 rondas gana la bolsa.</p>
                    </div>
                    <div class="pt-4 border-t border-white/10 flex items-center justify-between">
                        <span class="text-xs text-white font-chakra font-bold">$5 USD Entrada</span>
                        <a href="#" @click.prevent="navigate('register')" class="text-xs text-red-400 hover:text-white transition-colors uppercase font-bold tracking-widest">Entrar</a>
                    </div>
                </div>

                <div class="premium-card p-8 flex flex-col justify-between group h-[300px] relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-1 h-full bg-blue-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                    <div>
                        <span class="text-[10px] font-chakra font-bold uppercase text-blue-400 mb-2 block tracking-widest">L. OF LEGENDS</span>
                        <h3 class="font-block text-2xl text-white mb-4 uppercase">Rey ARAM</h3>
                        <p class="text-gray-400 text-xs leading-relaxed">Duelos 1v1 oficiales. Primera sangre, 100 súbditos o primera torreta define al ganador.</p>
                    </div>
                    <div class="pt-4 border-t border-white/10 flex items-center justify-between">
                        <span class="text-xs text-white font-chakra font-bold">$5 USD Entrada</span>
                        <a href="#" @click.prevent="navigate('register')" class="text-xs text-blue-400 hover:text-white transition-colors uppercase font-bold tracking-widest">Entrar</a>
                    </div>
                </div>

                <div class="premium-card p-8 flex flex-col justify-between group h-[300px] relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-1 h-full bg-cyan-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                    <div>
                        <span class="text-[10px] font-chakra font-bold uppercase text-cyan-400 mb-2 block tracking-widest">ROCKET LEAGUE</span>
                        <h3 class="font-block text-2xl text-white mb-4 uppercase">Hoops 2v2</h3>
                        <p class="text-gray-400 text-xs leading-relaxed">Bracket rápido de eliminación en la cancha de baloncesto. Domina el aire.</p>
                    </div>
                    <div class="pt-4 border-t border-white/10 flex items-center justify-between">
                        <span class="text-xs text-white font-chakra font-bold">$10 USD Entrada</span>
                        <a href="#" @click.prevent="navigate('register')" class="text-xs text-cyan-400 hover:text-white transition-colors uppercase font-bold tracking-widest">Entrar</a>
                    </div>
                </div>

                <div class="premium-card p-8 flex flex-col justify-between group h-[300px] relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-1 h-full bg-orange-500 opacity-20 group-hover:opacity-100 transition-opacity"></div>
                    <div>
                        <span class="text-[10px] font-chakra font-bold uppercase text-orange-400 mb-2 block tracking-widest">OVERWATCH 2</span>
                        <h3 class="font-block text-2xl text-white mb-4 uppercase">Copa Defensora</h3>
                        <p class="text-gray-400 text-xs leading-relaxed">Arma tu escuadra de 5 (Cola de roles) y domina los mapas en eliminación directa.</p>
                    </div>
                    <div class="pt-4 border-t border-white/10 flex items-center justify-between">
                        <span class="text-xs text-white font-chakra font-bold">$15 USD Entrada</span>
                        <a href="#" @click.prevent="navigate('register')" class="text-xs text-orange-400 hover:text-white transition-colors uppercase font-bold tracking-widest">Entrar</a>
                    </div>
                </div>

            </div>
        </section>

        <!-- ================= PÁGINA 4: CALCULADORA ================= -->
        <section id="view-simulator" class="view-section" v-show="activeView === 'simulator'">
            <div class="text-center mb-16">
                <h2 class="font-block text-5xl sm:text-7xl uppercase text-white tracking-wide mb-6">
                    REPARTO <span class="text-gray-500">BOLSA</span>
                </h2>
                <p class="text-gray-400 text-base max-w-2xl mx-auto leading-relaxed font-light">
                    Transparencia total. Proyecta el pozo de premios final y semanal basándote en la venta de pases Premium de la comunidad.
                </p>
            </div>

            <div class="premium-card p-10 max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
                    
                    <div class="space-y-10">
                        <div>
                            <div class="flex justify-between items-center font-chakra font-bold uppercase text-white mb-4 text-xs tracking-widest">
                                <span>Usuarios Premium</span>
                                <span class="text-white text-lg font-block">{{ inputPlayers }}</span>
                            </div>
                            <input type="range" v-model.number="inputPlayers" @input="updatePrize" min="20" max="500" class="w-full h-1 bg-white/20 appearance-none cursor-pointer accent-white">
                        </div>

                        <div>
                            <div class="flex justify-between items-center font-chakra font-bold uppercase text-white mb-4 text-xs tracking-widest">
                                <span>Costo Pase Upgrade</span>
                                <span class="text-white text-lg font-block">${{ inputPrice }}</span>
                            </div>
                            <input type="range" v-model.number="inputPrice" @input="updatePrize" min="5" max="30" step="5" class="w-full h-1 bg-white/20 appearance-none cursor-pointer accent-white">
                        </div>

                        <div class="border-l border-white/20 pl-6 text-sm text-gray-400 space-y-4 font-light">
                            <div class="flex justify-between"><span>Premios Semanales:</span> <strong class="text-white">40%</strong></div>
                            <div class="flex justify-between"><span>Mega Pozo Final:</span> <strong class="text-white">40%</strong></div>
                            <div class="flex justify-between"><span>Sorteos Comunidad:</span> <strong class="text-white">20%</strong></div>
                        </div>
                    </div>

                    <div class="border border-white/10 bg-black/40 p-10 text-center relative flex flex-col justify-center h-full">
                        <span class="text-[10px] text-gray-500 font-chakra font-bold uppercase tracking-widest block mb-2">Recaudación Estimada</span>
                        <span class="font-block text-6xl text-white tracking-tight block mb-12">${{ prizeTotal.toLocaleString() }}</span>
                        
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-center">
                                <span class="text-[9px] text-gray-500 font-chakra uppercase block mb-1">Semanales</span>
                                <strong class="text-sm font-block text-white">${{ prizeWeekly.toLocaleString() }}</strong>
                            </div>
                            <div class="text-center border-x border-white/10">
                                <span class="text-[9px] text-gray-500 font-chakra uppercase block mb-1">Bolsa Final</span>
                                <strong class="text-sm font-block text-white">${{ prizeFinal.toLocaleString() }}</strong>
                            </div>
                            <div class="text-center">
                                <span class="text-[9px] text-gray-500 font-chakra uppercase block mb-1">Sorteos</span>
                                <strong class="text-sm font-block text-white">${{ prizeGiveaways.toLocaleString() }}</strong>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ================= PÁGINA 5: CLASIFICACIÓN (ANTES REGISTRO) ================= -->
        <section id="view-register" class="view-section" v-show="activeView === 'register'">
            <div class="text-center mb-16">
                <h2 class="font-block text-5xl sm:text-7xl uppercase text-white tracking-wide mb-6">
                    TABLA DE <span class="text-[var(--rankit-neon)]">CLASIFICACIÓN</span>
                </h2>
                <p class="text-gray-400 text-base max-w-2xl mx-auto leading-relaxed font-light">
                    Sigue en tiempo real las posiciones del torneo. Solo los mejores asegurarán su lugar en la Gran Final.
                </p>
            </div>

            <div class="max-w-5xl mx-auto bg-black/40 border border-white/10 p-6 sm:p-10 rounded-xl">
                <!-- Selector de Torneos Seriados -->
                <div v-if="!loading && allSerializedTournaments.length > 0" class="flex flex-wrap justify-center gap-4 mb-8">
                    <button 
                        v-for="(item, index) in allSerializedTournaments" 
                        :key="index"
                        @click="selectTournament(index)"
                        :class="[
                            'px-6 py-2 rounded-full font-chakra font-bold text-[11px] uppercase tracking-widest transition-all',
                            activeTournamentIndex === index 
                                ? 'bg-[var(--rankit-neon)] text-black shadow-[0_0_15px_rgba(var(--rankit-neon-rgb),0.5)]' 
                                : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                        ]"
                    >
                        {{ item.tournament.name }}
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="flex justify-center items-center py-20">
                    <div class="w-10 h-10 border-4 border-white/20 border-t-[var(--rankit-neon)] rounded-full animate-spin"></div>
                </div>

                <!-- Leaderboard Table -->
                <div v-else-if="allSerializedTournaments.length > 0" class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="border-b border-white/10 text-[10px] font-bold text-gray-500 uppercase tracking-widest font-chakra">
                                <th class="py-4 px-4">#</th>
                                <th class="py-4 px-4">Jugador</th>
                                <th class="py-4 px-4 text-center">Partidas Jugadas</th>
                                <th class="py-4 px-4 text-center">Eliminaciones</th>
                                <th class="py-4 px-4 text-center text-[var(--rankit-neon)]">Total Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(player, index) in allSerializedTournaments[activeTournamentIndex]?.standings" :key="index" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                <td class="py-4 px-4 text-white font-black font-chakra">
                                    <span :class="{'text-yellow-400': index === 0, 'text-gray-300': index === 1, 'text-yellow-600': index === 2}">
                                        {{ index + 1 }}
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-400">
                                            {{ player.player_name ? player.player_name.charAt(0).toUpperCase() : '?' }}
                                        </div>
                                        <span class="text-white font-bold uppercase tracking-wider font-chakra">{{ player.player_name || 'Desconocido' }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-center text-gray-400 font-chakra">{{ player.matches_played || 0 }}</td>
                                <td class="py-4 px-4 text-center text-gray-400 font-chakra">{{ player.total_kills || 0 }}</td>
                                <td class="py-4 px-4 text-center text-white font-black text-lg font-chakra">{{ (player.total_kills * 10) || 0 }}</td>
                            </tr>
                            <tr v-if="!allSerializedTournaments[activeTournamentIndex]?.standings || allSerializedTournaments[activeTournamentIndex].standings.length === 0">
                                <td colspan="5" class="py-12 text-center text-gray-500 font-bold uppercase tracking-widest text-xs">
                                    No hay jugadores clasificados para este torneo aún.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-else class="py-16 text-center text-gray-500 font-bold uppercase tracking-widest text-sm border border-white/5 border-dashed rounded-xl">
                    No hay torneos seriados activos en este momento.
                </div>

                <!-- Botón para ir a inscribirse al torneo seleccionado -->
                <div v-if="!loading && allSerializedTournaments.length > 0" class="mt-10 text-center">
                    <div class="text-xs text-gray-500 uppercase font-bold tracking-widest mb-4 font-chakra">
                        ¿Quieres competir en <span class="text-white">{{ allSerializedTournaments[activeTournamentIndex]?.tournament?.name }}</span>?
                    </div>
                    <a
                        :href="`/t/${allSerializedTournaments[activeTournamentIndex]?.tournament?.id}`"
                        class="inline-flex items-center gap-3 px-10 py-4 bg-[var(--rankit-neon)] text-black font-black uppercase tracking-widest text-sm hover:bg-white transition-all font-chakra"
                        style="clip-path: polygon(8px 0%, 100% 0%, calc(100% - 8px) 100%, 0% 100%)"
                    >
                        <i class="ph-bold ph-ticket text-lg"></i>
                        Inscríbete Aquí
                        <i class="ph-bold ph-arrow-right text-lg"></i>
                    </a>
                    <p class="text-[10px] text-gray-600 uppercase tracking-widest mt-3">Inscripciones y resultados en tiempo real</p>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer Elegante -->
    <footer class="border-t border-white/5 bg-[#010003] py-16 px-4 text-center relative z-10">
        <div class="flex justify-center gap-8 text-lg text-gray-600 mb-8">
            <a href="#" class="hover:text-white transition-colors"><i class="fa-brands fa-discord"></i></a>
            <a href="#" class="hover:text-white transition-colors"><i class="fa-brands fa-twitter"></i></a>
            <a href="https://www.instagram.com/rankit.pro/followers/mutualOnly" target="_blank" class="hover:text-white transition-colors"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" class="hover:text-white transition-colors"><i class="fa-brands fa-youtube"></i></a>
        </div>
        <p class="font-block text-sm text-gray-600 tracking-widest uppercase mb-4">&copy; 2026 RANKIT PRO LEAGUE.</p>
        <p class="max-w-2xl mx-auto leading-relaxed text-[10px] text-gray-700">
            Esta liga competitiva no está afiliada ni patrocinada por Epic Games. Todo el circuito es en formato solitario. Los pases Premium alimentan directamente las bolsas de premio semanales y la Gran Final. La Gran Final no tiene costo de inscripción para los clasificados.
        </p>
    </footer>



    </div>
</template>

<style scoped>

        body {
            background-color: #030108;
            background-image: 
                linear-gradient(180deg, rgba(3, 1, 8, 0.85) 0%, rgba(3, 1, 8, 0.93) 100%), 
                url('https://s2.elespanol.com/2022/02/17/invertia/disruptores/ecosistema-startup/startups/650945168_221956806_1706x1280.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: #e2e8f0;
        }

        /* Degradado metálico/neón sutil y elegante */
        .gradient-text-epic {
            background: linear-gradient(to bottom, #ffffff 0%, #d8b4fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 10px 20px rgba(155,48,255,0.2));
        }

        /* Botón de alto contraste */
        .fn-btn {
            clip-path: polygon(15px 0, 100% 0, 100% calc(100% - 15px), calc(100% - 15px) 100%, 0 100%, 0 15px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fn-skew { transform: skewX(-10deg); }
        .fn-unskew { transform: skewX(10deg); }

        /* SPA navigation */
        .view-section {
            
            animation: fadeUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Tarjetas Premium - Menos saturación, más cristal/borde sutil */
        .premium-card {
            background: rgba(10, 5, 20, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.4s ease;
        }
        .premium-card:hover {
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }

        /* Carrusel escondiendo scrollbar */
        .hide-scrollbar::-webkit-scrollbar {  }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* Efecto de ruido/grano fotográfico para fondos épicos */
        .noise-overlay {
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        /* Scrollbar elegante */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #030108; }
        ::-webkit-scrollbar-thumb { background: #3b1c6b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9b30ff; }
    
</style>
