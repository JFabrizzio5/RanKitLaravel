<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { ref, onMounted } from "vue";
import axios from "axios";

const activeView = ref("home");
const standings = ref([]);
const loading = ref(true);

const navigate = (view) => {
    activeView.value = view;
};

onMounted(async () => {
    try {
        const res = await axios.get('/api/league/standings');
        standings.value = res.data;
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
                <button onclick="scrollCarousel(-1)" class="absolute left-0 top-1/2 -translate-y-1/2 -ml-4 z-30 bg-black/80 border border-white/20 text-white w-14 h-14 flex items-center justify-center rounded-full hover:bg-white hover:text-black transition-all hidden md:flex opacity-0 group-hover:opacity-100 backdrop-blur-md">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button onclick="scrollCarousel(1)" class="absolute right-0 top-1/2 -translate-y-1/2 -mr-4 z-30 bg-black/80 border border-white/20 text-white w-14 h-14 flex items-center justify-center rounded-full hover:bg-white hover:text-black transition-all hidden md:flex opacity-0 group-hover:opacity-100 backdrop-blur-md">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>

                <div id="league-carousel" class="flex gap-6 overflow-x-auto snap-x snap-mandatory hide-scrollbar py-8 px-4 items-center scroll-smooth">
                    
                    <!-- Póster Semana 1 -->
                    <div onclick="selectWeek(1)" id="card-w1" class="shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden opacity-100 shadow-glowPurple">
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
                        <div id="sel-w1" class="absolute inset-0 border border-fnBrightPurple opacity-100 transition-opacity duration-300 pointer-events-none z-30"></div>
                    </div>

                    <!-- Póster Semana 2 -->
                    <div onclick="selectWeek(2)" id="card-w2" class="shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden opacity-75 hover:opacity-100">
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
                        <div id="sel-w2" class="absolute inset-0 border border-fnEmerald opacity-0 transition-opacity duration-300 pointer-events-none z-30"></div>
                    </div>

                    <!-- Póster Semana 3 (Repechaje) -->
                    <div onclick="selectWeek(3)" id="card-w3" class="shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden opacity-75 hover:opacity-100">
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
                        <div id="sel-w3" class="absolute inset-0 border border-fnCrimson opacity-0 transition-opacity duration-300 pointer-events-none z-30"></div>
                    </div>

                    <!-- Póster Semana 4 (Final) -->
                    <div onclick="selectWeek(4)" id="card-w4" class="shrink-0 snap-center w-[85vw] max-w-[340px] h-[500px] bg-[#05020a] relative cursor-pointer transition-all duration-500 hover:-translate-y-3 group border border-white/10 rounded-lg overflow-hidden opacity-75 hover:opacity-100">
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
                        <div id="sel-w4" class="absolute inset-0 border border-fnGold opacity-0 transition-opacity duration-300 pointer-events-none z-30"></div>
                    </div>

                </div>
            </div>

            <!-- Panel de detalles Premium (Menos cajas sólidas, tipografía abierta) -->
            <div class="premium-card p-8 sm:p-12 relative overflow-hidden">
                <div class="flex flex-col md:flex-row gap-12 relative z-10">
                    
                    <div class="md:w-1/3 border-r border-white/10 pr-8">
                        <span id="panel-badge" class="text-fnBrightPurple font-chakra font-bold text-xs uppercase tracking-widest mb-4 block">Fase Inicial</span>
                        <h3 id="panel-title" class="font-block text-4xl text-white uppercase leading-none mb-6">SEMANA 1</h3>
                        <p id="panel-desc-short" class="text-gray-400 text-sm leading-relaxed mb-6">
                            El inicio de la liga regular. Sobrevive, acumula puntos y asegura tu lugar en el Top 15% para saltarte el repechaje.
                        </p>
                        <div class="pt-4 border-t border-white/10">
                            <span class="text-[10px] font-chakra font-bold uppercase tracking-widest text-gray-500 mb-3 block">Póster de la fase</span>
                            <img id="panel-poster" src="/public/league/clasificatorio-1.png" alt="Póster oficial de la fase seleccionada" class="w-full rounded-md border border-white/10 bg-black/30 object-cover">
                        </div>
                    </div>

                    <div class="md:w-2/3 grid grid-cols-1 sm:grid-cols-2 gap-8">
                        <div>
                            <h4 class="text-xs font-chakra font-bold uppercase tracking-widest text-gray-500 mb-4 border-b border-white/10 pb-2">Reglas de Emparejamiento</h4>
                            <div id="panel-desc1" class="text-gray-300 text-sm leading-relaxed space-y-3">
                                <p>Las primeras 3 partidas son <strong class="text-white">GRATIS</strong> para buscar clasificación. Con el Upgrade Premium, juegas <strong class="text-white">5 partidas</strong> y participas por el pozo de dinero semanal.</p>
                                <p>El lobby es privado. Prohibido el teaming o griefing intencional. Formato puramente Solos (Sin suplentes).</p>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-xs font-chakra font-bold uppercase tracking-widest text-gray-500 mb-4 border-b border-white/10 pb-2">Sistema de Clasificación</h4>
                            <div id="panel-desc2" class="text-gray-300 text-sm leading-relaxed space-y-3">
                                <p>El <strong class="text-white border-b border-fnBrightPurple">Top 15%</strong> de los jugadores con mayor puntuación de la jornada avanzan directo a la Gran Final.</p>
                                <ul class="space-y-1 mt-2 text-xs font-mono text-gray-400">
                                    <li class="flex justify-between"><span>Victoria Royale</span> <strong class="text-white">+100 Pts</strong></li>
                                    <li class="flex justify-between"><span>Top 2-5</span> <strong class="text-white">+75 a +50 Pts</strong></li>
                                    <li class="flex justify-between"><span>Eliminación</span> <strong class="text-white">+5 Pts</strong></li>
                                </ul>
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
                
                <div v-else-if="standings.length === 0" class="text-center text-gray-500 py-10 font-chakra uppercase tracking-widest font-bold border border-gray-800 border-dashed rounded-xl">
                    Aún no hay tablas de clasificación disponibles.
                </div>

                <div v-else class="space-y-16">
                    <div v-for="(league, index) in standings" :key="index" class="premium-card p-6 md:p-10 relative overflow-hidden">
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
                                    <tr v-if="league.standings.length === 0">
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
                                <span id="player-count-label" class="text-white text-lg font-block">150</span>
                            </div>
                            <input type="range" id="input-players" min="20" max="500" value="150" class="w-full h-1 bg-white/20 appearance-none cursor-pointer accent-white">
                        </div>

                        <div>
                            <div class="flex justify-between items-center font-chakra font-bold uppercase text-white mb-4 text-xs tracking-widest">
                                <span>Costo Pase Upgrade</span>
                                <span id="price-label" class="text-white text-lg font-block">$10</span>
                            </div>
                            <input type="range" id="input-price" min="5" max="30" value="10" step="5" class="w-full h-1 bg-white/20 appearance-none cursor-pointer accent-white">
                        </div>

                        <div class="border-l border-white/20 pl-6 text-sm text-gray-400 space-y-4 font-light">
                            <div class="flex justify-between"><span>Premios Semanales:</span> <strong class="text-white">40%</strong></div>
                            <div class="flex justify-between"><span>Mega Pozo Final:</span> <strong class="text-white">40%</strong></div>
                            <div class="flex justify-between"><span>Sorteos Comunidad:</span> <strong class="text-white">20%</strong></div>
                        </div>
                    </div>

                    <div class="border border-white/10 bg-black/40 p-10 text-center relative flex flex-col justify-center h-full">
                        <span class="text-[10px] text-gray-500 font-chakra font-bold uppercase tracking-widest block mb-2">Recaudación Estimada</span>
                        <span id="output-total-weekly" class="font-block text-6xl text-white tracking-tight block mb-12">$1,500</span>
                        
                        <div class="grid grid-cols-3 gap-2">
                            <div class="text-center">
                                <span class="text-[9px] text-gray-500 font-chakra uppercase block mb-1">Semanales</span>
                                <strong id="output-weekly-prizes" class="text-sm font-block text-white">$600</strong>
                            </div>
                            <div class="text-center border-x border-white/10">
                                <span class="text-[9px] text-gray-500 font-chakra uppercase block mb-1">Bolsa Final</span>
                                <strong id="output-final-pool" class="text-sm font-block text-white">$600</strong>
                            </div>
                            <div class="text-center">
                                <span class="text-[9px] text-gray-500 font-chakra uppercase block mb-1">Sorteos</span>
                                <strong id="output-giveaways" class="text-sm font-block text-white">$300</strong>
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

            <div class="max-w-4xl mx-auto bg-black/40 border border-white/10 p-6 sm:p-10 rounded-xl">
                <!-- Loading State -->
                <div v-if="loading" class="flex justify-center items-center py-20">
                    <div class="w-10 h-10 border-4 border-white/20 border-t-[var(--rankit-neon)] rounded-full animate-spin"></div>
                </div>

                <!-- Leaderboard Table -->
                <div v-else class="overflow-x-auto custom-scrollbar">
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
                            <tr v-for="(player, index) in standings" :key="player.id || index" class="border-b border-white/5 hover:bg-white/5 transition-colors group">
                                <td class="py-4 px-4 text-white font-black font-chakra">
                                    <span :class="{'text-yellow-400': index === 0, 'text-gray-300': index === 1, 'text-yellow-600': index === 2}">
                                        {{ index + 1 }}
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-400">
                                            {{ player.name ? player.name.charAt(0).toUpperCase() : '?' }}
                                        </div>
                                        <span class="text-white font-bold uppercase tracking-wider font-chakra">{{ player.name || 'Desconocido' }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-center text-gray-400 font-chakra">{{ player.matches_played || 0 }}</td>
                                <td class="py-4 px-4 text-center text-gray-400 font-chakra">{{ player.kills || 0 }}</td>
                                <td class="py-4 px-4 text-center text-white font-black text-lg font-chakra">{{ player.total_points || 0 }}</td>
                            </tr>
                            <tr v-if="!standings || standings.length === 0">
                                <td colspan="5" class="py-12 text-center text-gray-500 font-bold uppercase tracking-widest text-xs">
                                    La tabla de clasificación aún no está disponible.
                                </td>
                            </tr>
                        </tbody>
                    </table>
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

    <script>
        // --- NAVEGACIÓN SPA ---
        function navigate(viewId) {
            document.querySelectorAll('.view-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('text-white');
                link.classList.add('text-gray-500');
            });

            const activeSection = document.getElementById(`view-${viewId}`);
            if (activeSection) {
                // Forzar re-flow para reiniciar la animación
                activeSection.style.animation = 'none';
                activeSection.offsetHeight; 
                activeSection.style.animation = null;
                activeSection.classList.add('active');
            }

            const activeLink = document.getElementById(`nav-${viewId}`);
            if (activeLink) {
                activeLink.classList.remove('text-gray-500');
                activeLink.classList.add('text-white');
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // --- CARRUSEL SCROLL ---
        function scrollCarousel(direction) {
            const carousel = document.getElementById('league-carousel');
            // Ancho tarjeta aprox + gap
            const scrollAmount = 360; 
            carousel.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
        }

        // --- DATOS LIGA (NUEVAS REGLAS) ---
        const leagueData = {
            1: { 
                badge: "Fase Inicial", title: "SEMANA 1", 
                desc_short: "El inicio de la liga regular. Compite en el primer corte gratuito o aprovecha el tier Premium para asegurar tu cupo.",
                color: "text-fnBrightPurple", borderColor: "border-fnBrightPurple",
                poster: "/public/league/clasificatorio-1.png", posterAlt: "Póster de la semana 1",
                desc1: "<p>Las primeras 3 partidas son <strong class='text-white'>GRATIS</strong> para buscar clasificación. Con el Upgrade Premium, juegas <strong class='text-white'>2 partidas extras (5 en total)</strong> y participas por el pozo de dinero semanal.</p><p>El lobby es privado. Prohibido el teaming o griefing intencional. Formato puramente Solos (Sin suplentes).</p>",
                desc2: "<p>Hay dos cortes de clasificación hacia la Final:</p><ul class='space-y-2 mt-2 text-sm text-gray-300'><li class='flex items-start gap-2'><i class='fa-solid fa-check text-fnBrightPurple mt-1'></i> <span><strong>Corte Free (Partidas 1-3):</strong> El <strong class='text-white'>Top 10% general</strong> de la jornada avanza.</span></li><li class='flex items-start gap-2'><i class='fa-solid fa-crown text-fnGold mt-1'></i> <span><strong>Corte Premium (Partidas 4-5):</strong> El <strong class='text-white'>Top 10% de los usuarios de pago</strong> avanza, sin contar a los ya clasificados.</span></li></ul>" 
            },
            2: { 
                badge: "Segunda Oportunidad", title: "SEMANA 2", 
                desc_short: "Misma modalidad, nueva tabla de puntos. Segundo llamado para el Top 10% gratuito y premium.",
                color: "text-fnEmerald", borderColor: "border-fnEmerald",
                poster: "/public/league/clasificatorio-2.png", posterAlt: "Póster de la semana 2",
                desc1: "<p>Mantenemos el formato exacto de <strong class='text-white'>Battle Royale Clásico en Solos</strong>. 3 partidas gratis para el primer corte, y 2 adicionales para el corte Premium y la disputa por la bolsa semanal.</p><p>Los jugadores clasificados en la Semana 1 pueden jugar para pelear por el dinero de la semana 2, pero sus cupos de clasificación recorrerán la tabla.</p>",
                desc2: "<p>Se aplican los mismos dos cortes de clasificación hacia la Final:</p><ul class='space-y-2 mt-2 text-sm text-gray-300'><li class='flex items-start gap-2'><i class='fa-solid fa-check text-fnEmerald mt-1'></i> <span><strong>Corte Free (Partidas 1-3):</strong> El <strong class='text-white'>Top 10% general</strong> avanza.</span></li><li class='flex items-start gap-2'><i class='fa-solid fa-crown text-fnGold mt-1'></i> <span><strong>Corte Premium (Partidas 4-5):</strong> El <strong class='text-white'>Top 10% de los usuarios de pago</strong> avanza, recorriendo a los ya clasificados.</span></li></ul>" 
            },
            3: { 
                badge: "La Última Vida", title: "SEMANA 3", 
                desc_short: "El Repechaje Extremo. 4 partidas en total divididas en las dos disciplinas principales de la isla. Solo 8 tickets de clasificación en juego.",
                color: "text-fnCrimson", borderColor: "border-fnCrimson",
                poster: "/public/league/repechaje.png", posterAlt: "Póster de la semana 3",
                desc1: "<p>Este es el filtro final de versatilidad. Jugaremos <strong class='text-white'>4 partidas exactas: 2 de Battle Royale Clásico y 2 de Recarga (Reload)</strong>.</p><p>Ya no hay clasificación por porcentaje. Los lugares son contados y se otorgan por mérito absoluto o agresividad pura.</p>",
                desc2: "<p>Los tickets a la Final se reparten de la siguiente manera (8 Pases en total):</p><ul class='space-y-3 mt-2 text-sm text-gray-300'><li class='flex items-start gap-2'><i class='fa-solid fa-trophy text-fnCrimson mt-1'></i> <span><strong>Ganadores (4 pases):</strong> El jugador que gane cada partida.</span></li><li class='flex items-start gap-2'><i class='fa-solid fa-chart-line text-fnCrimson mt-1'></i> <span><strong>Constancia (3 pases):</strong> Los 3 jugadores con mayor cantidad de puntos totales en las 4 partidas.</span></li><li class='flex items-start gap-2'><i class='fa-solid fa-skull text-fnCrimson mt-1'></i> <span><strong>MVP Kills (1 pase):</strong> El jugador con mayor número de eliminaciones totales que no haya clasificado por los métodos anteriores.</span></li></ul>" 
            },
            4: { 
                badge: "El Cierre Absoluto", title: "GRAN FINAL", 
                desc_short: "Solo los mejores. Top 15% de W1 y W2, más los ganadores del Repechaje W3. El nivel más alto de Solos del servidor.",
                color: "text-fnGold", borderColor: "border-fnGold",
                poster: "/public/league/gran-final.png", posterAlt: "Póster de la gran final",
                desc1: "<p>Volvemos al <strong class='text-white'>Battle Royale Clásico</strong>. Lobbies privados apilados (Stacked) con custom matchmaking. 6 partidas consecutivas donde no hay margen de error. No se permiten suplentes, es el jugador registrado o nada.</p>",
                desc2: "<p>El pago del Mega Pozo. Los puntos de esta serie definen al campeón, que se lleva la gran bolsa.</p><p class='mt-2'>Además, finalizando el torneo, el <strong class='text-white border-b border-fnGold'>20% de toda la recaudación</strong> de la temporada se repartirá en sorteos de V-Bucks o efectivo en vivo para todos los que participaron alguna vez en la liga.</p>" 
            }
        };

        function selectWeek(weekNum) {
            // Estilos Carrusel
            for (let i = 1; i <= 4; i++) {
                const card = document.getElementById(`card-w${i}`);
                const sel = document.getElementById(`sel-w${i}`);
                if (card) {
                    card.classList.remove('opacity-100', 'shadow-glowPurple', 'shadow-glowEmerald', 'shadow-glowCrimson', 'shadow-glowGold');
                    card.classList.add('opacity-75');
                    sel.classList.remove('opacity-100');
                    sel.classList.add('opacity-0');
                }
            }

            const activeCard = document.getElementById(`card-w${weekNum}`);
            const activeSel = document.getElementById(`sel-w${weekNum}`);
            
            if (activeCard && activeSel) {
                activeCard.classList.remove('opacity-75');
                activeCard.classList.add('opacity-100');
                activeSel.classList.remove('opacity-0');
                activeSel.classList.add('opacity-100');

                if (weekNum === 1) activeCard.classList.add('shadow-glowPurple');
                if (weekNum === 2) activeCard.classList.add('shadow-glowEmerald');
                if (weekNum === 3) activeCard.classList.add('shadow-glowCrimson');
                if (weekNum === 4) activeCard.classList.add('shadow-glowGold');
                
                // Centrar en móviles
                if (window.innerWidth < 768) {
                    activeCard.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
                }
            }

            // Actualizar Panel
            const data = leagueData[weekNum];
            document.getElementById('panel-badge').innerText = data.badge;
            document.getElementById('panel-badge').className = `${data.color} font-chakra font-bold text-xs uppercase tracking-widest mb-4 block`;
            
            document.getElementById('panel-title').innerText = data.title;
            document.getElementById('panel-desc-short').innerText = data.desc_short;
            document.getElementById('panel-poster').src = data.poster;
            document.getElementById('panel-poster').alt = data.posterAlt;
            
            document.getElementById('panel-desc1').innerHTML = data.desc1;
            document.getElementById('panel-desc2').innerHTML = data.desc2;
        }

        // --- REGISTRO & SIMULADOR ---
        function selectRegTier(tier) {
            const freeBox = document.getElementById('tier-option-free');
            const premiumBox = document.getElementById('tier-option-premium');
            const rFree = document.getElementById('radio-free');
            const rPremium = document.getElementById('radio-premium');

            if (tier === 'free') {
                rFree.checked = true;
                freeBox.className = "cursor-pointer bg-white/5 p-6 border border-white/30 flex items-start gap-4 transition-all";
                premiumBox.className = "cursor-pointer bg-black/40 p-6 border border-white/5 flex items-start gap-4 transition-all hover:bg-white/5";
            } else {
                rPremium.checked = true;
                premiumBox.className = "cursor-pointer bg-white/5 p-6 border border-white/30 flex items-start gap-4 transition-all";
                freeBox.className = "cursor-pointer bg-black/40 p-6 border border-white/5 flex items-start gap-4 transition-all hover:bg-white/5";
            }
        }

        const inputPlayers = document.getElementById('input-players');
        const inputPrice = document.getElementById('input-price');

        function updatePrizePoolCalculations() {
            const players = parseInt(inputPlayers.value);
            const price = parseInt(inputPrice.value);

            const total = players * price;
            const weeklyPrizes = Math.round(total * 0.40);
            const finalPool = Math.round(total * 0.40);
            const giveaways = Math.round(total * 0.20);

            document.getElementById('player-count-label').innerText = players;
            document.getElementById('price-label').innerText = `$${price}`;

            document.getElementById('output-total-weekly').innerText = `$${total.toLocaleString()}`;
            document.getElementById('output-weekly-prizes').innerText = `$${weeklyPrizes.toLocaleString()}`;
            document.getElementById('output-final-pool').innerText = `$${finalPool.toLocaleString()}`;
            document.getElementById('output-giveaways').innerText = `$${giveaways.toLocaleString()}`;
        }

        inputPlayers.addEventListener('input', updatePrizePoolCalculations);
        inputPrice.addEventListener('input', updatePrizePoolCalculations);

        function handleRegistration(e) {
            e.preventDefault();
            const epic = document.getElementById('reg-epic').value;
            const whatsapp = document.getElementById('reg-whatsapp').value;
            const selectedEvent = document.getElementById('inp-event').options[document.getElementById('inp-event').selectedIndex].text;
            const plan = document.querySelector('input[name="reg-tier"]:checked').value === 'free' ? 'ESTÁNDAR (GRATIS)' : 'UPGRADE PREMIUM';
            
            const msgArea = document.getElementById('status-message');
            msgArea.classList.remove('hidden', 'border-white', 'border-fnGold', 'text-white', 'text-fnGold');

            if (plan.includes('GRATIS')) {
                msgArea.classList.add('border-white', 'text-white');
                msgArea.innerHTML = `✓ REGISTRO CONFIRMADO: ${epic} <br><span class="text-xs text-gray-500 font-normal mt-2 block tracking-normal normal-case">Plan: ${plan} | Evento: ${selectedEvent}</span>`;
            } else {
                msgArea.classList.add('border-fnGold', 'text-fnGold');
                msgArea.innerHTML = `⚡ UPGRADE RESERVADO: ${epic} <br><span class="text-xs text-gray-500 font-normal mt-2 block tracking-normal normal-case">Sigue las instrucciones en Discord para validar el pago. Evento: ${selectedEvent}</span>`;
            }
            msgArea.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        window.onload = function() {
            updatePrizePoolCalculations();
            selectRegTier('free');
            selectWeek(1); // Auto-seleccionar W1
        };
    </script>

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
