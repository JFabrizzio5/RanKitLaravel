<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const groups = ref([]);
const matches = ref([]);
const teams = ref([]);
const loading = ref(true);
const activeTab = ref('groups');

onMounted(async () => {
    try {
        const [resGroups, resMatches, resTeams] = await Promise.all([
            fetch('https://worldcup26.ir/get/groups').then(res => res.json()).catch(() => ({ groups: [] })),
            fetch('https://worldcup26.ir/get/games').then(res => res.json()).catch(() => ({ games: [] })),
            fetch('https://worldcup26.ir/get/teams').then(res => res.json()).catch(() => ({ teams: [] }))
        ]);
        
        // El API podría devolver algo distinto si falla o cambia formato
        groups.value = resGroups.groups || resGroups || [];
        matches.value = resMatches.games || resMatches || [];
        teams.value = resTeams.teams || resTeams || [];
    } catch (e) {
        console.error("Error fetching World Cup Data", e);
    } finally {
        loading.value = false;
    }
});

const getTeamInfo = (teamIdOrName) => {
    return teams.value.find(t => t.id === teamIdOrName || t.name === teamIdOrName) || { name: teamIdOrName };
};
</script>

<template>
    <Head title="Mundial 2026 | Stats en Vivo" />
    <div class="min-h-screen bg-[#10031c] text-gray-300 font-sans selection:bg-fuchsia-500/30">
        <!-- Header Premium -->
        <header class="bg-[#1a082c] border-b border-fuchsia-900/50 py-6 sticky top-0 z-40 backdrop-blur-xl bg-opacity-80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="text-4xl text-fuchsia-500">🏆</div>
                    <div>
                        <h1 class="text-3xl font-black text-white leading-none tracking-tighter uppercase font-mono">FIFA WORLD CUP <span class="text-fuchsia-500">2026</span></h1>
                        <p class="text-xs text-fuchsia-400 font-bold tracking-widest uppercase mt-1 flex items-center gap-2">
                            <span class="w-2 h-2 bg-fuchsia-500 rounded-full animate-ping"></span> Live Stats
                        </p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-4">
                    <Link href="/" class="text-sm font-bold text-gray-400 hover:text-white transition-colors border-r border-gray-700 pr-4">Inicio</Link>
                    <Link href="/dashboard" class="text-sm font-bold text-gray-400 hover:text-white transition-colors">Dashboard</Link>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            
            <div v-if="loading" class="flex flex-col items-center justify-center py-32">
                <div class="w-16 h-16 border-4 border-fuchsia-900 border-t-fuchsia-500 rounded-full animate-spin mb-4"></div>
                <p class="text-fuchsia-500 font-bold tracking-widest uppercase">Cargando Datos del Mundial...</p>
            </div>

            <div v-else>
                <!-- Tabs -->
                <div class="flex border-b border-gray-800 mb-8 overflow-x-auto">
                    <button @click="activeTab = 'groups'" :class="['px-6 py-4 font-bold uppercase tracking-wider text-sm transition-colors', activeTab === 'groups' ? 'text-fuchsia-400 border-b-2 border-fuchsia-500 bg-fuchsia-900/10' : 'text-gray-500 hover:text-gray-300 hover:bg-gray-900/50']">
                        Fase de Grupos
                    </button>
                    <button @click="activeTab = 'matches'" :class="['px-6 py-4 font-bold uppercase tracking-wider text-sm transition-colors', activeTab === 'matches' ? 'text-fuchsia-400 border-b-2 border-fuchsia-500 bg-fuchsia-900/10' : 'text-gray-500 hover:text-gray-300 hover:bg-gray-900/50']">
                        Partidos (Live)
                    </button>
                    <button @click="activeTab = 'teams'" :class="['px-6 py-4 font-bold uppercase tracking-wider text-sm transition-colors', activeTab === 'teams' ? 'text-fuchsia-400 border-b-2 border-fuchsia-500 bg-fuchsia-900/10' : 'text-gray-500 hover:text-gray-300 hover:bg-gray-900/50']">
                        Equipos Clasificados
                    </button>
                </div>

                <!-- Grupos -->
                <div v-if="activeTab === 'groups'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <div v-for="g in groups" :key="g.letter" class="bg-[#1f0d36] border border-fuchsia-900/30 rounded-2xl p-5 shadow-xl relative overflow-hidden group">
                        <div class="absolute -right-10 -top-10 w-32 h-32 bg-fuchsia-500/10 rounded-full blur-2xl group-hover:bg-fuchsia-500/20 transition-all"></div>
                        <h3 class="text-2xl font-black text-white mb-4 flex items-center gap-2 relative z-10 font-mono">
                            GRUPO <span class="text-fuchsia-500">{{ g.letter }}</span>
                        </h3>
                        
                        <div class="overflow-x-auto relative z-10">
                            <table class="w-full text-left text-sm">
                                <thead class="text-[10px] text-gray-500 uppercase border-b border-gray-800">
                                    <tr>
                                        <th class="py-2">Pos</th>
                                        <th class="py-2">Equipo</th>
                                        <th class="py-2 text-center">PJ</th>
                                        <th class="py-2 text-center">GF</th>
                                        <th class="py-2 text-center">GC</th>
                                        <th class="py-2 text-center text-fuchsia-400">PTS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(team, index) in g.teams" :key="team.name" class="border-b border-gray-800/30 hover:bg-[#2a134a] transition-colors">
                                        <td class="py-2.5 font-bold" :class="index < 2 ? 'text-fuchsia-400' : 'text-gray-500'">{{ index + 1 }}</td>
                                        <td class="py-2.5 font-bold text-gray-200">{{ team.name_en || team.name }}</td>
                                        <td class="py-2.5 text-center text-gray-400">{{ team.games_played || 0 }}</td>
                                        <td class="py-2.5 text-center text-gray-400">{{ team.goals_for || 0 }}</td>
                                        <td class="py-2.5 text-center text-gray-400">{{ team.goals_against || 0 }}</td>
                                        <td class="py-2.5 text-center font-bold text-fuchsia-400">{{ team.pts || team.points || 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-if="!groups || groups.length === 0" class="col-span-full text-center py-20 text-gray-500 font-bold uppercase tracking-widest">
                        Grupos aún no disponibles / Error en API
                    </div>
                </div>

                <!-- Partidos -->
                <div v-if="activeTab === 'matches'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div v-for="m in matches" :key="m.id || Math.random()" class="bg-[#1f0d36] border border-fuchsia-900/30 rounded-xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
                        <div class="text-[10px] uppercase font-bold text-fuchsia-500 tracking-wider">
                            {{ m.type === 'group' ? 'Grupo ' + m.group : m.type }} <br>
                            <span class="text-gray-500">{{ m.local_date }}</span>
                        </div>
                        
                        <div class="flex-1 flex justify-end items-center gap-3 w-full sm:w-auto">
                            <span class="font-bold text-white text-right text-lg">{{ m.home_team_name_en || 'TBD' }}</span>
                        </div>
                        
                        <div class="px-6 py-2 bg-[#120422] rounded-lg border border-fuchsia-900/30 text-center min-w-[120px]">
                            <div v-if="m.finished === 'TRUE' || (m.time_elapsed && m.time_elapsed !== 'notstarted')" class="text-2xl font-bold font-mono text-white flex justify-center gap-3">
                                <span :class="Number(m.home_score) > Number(m.away_score) ? 'text-fuchsia-400' : ''">{{ m.home_score || 0 }}</span>
                                <span class="text-gray-600">-</span>
                                <span :class="Number(m.away_score) > Number(m.home_score) ? 'text-fuchsia-400' : ''">{{ m.away_score || 0 }}</span>
                            </div>
                            <div v-else class="text-sm text-gray-500 font-bold uppercase tracking-wider">VS</div>
                            <div v-if="m.finished !== 'TRUE' && m.time_elapsed && m.time_elapsed !== 'notstarted'" class="text-[10px] text-red-500 mt-1 uppercase font-bold tracking-widest animate-pulse">EN VIVO</div>
                        </div>
                        
                        <div class="flex-1 flex justify-start items-center gap-3 w-full sm:w-auto">
                            <span class="font-bold text-white text-left text-lg">{{ m.away_team_name_en || 'TBD' }}</span>
                        </div>
                    </div>
                    <div v-if="!matches || matches.length === 0" class="col-span-full text-center py-20 text-gray-500 font-bold uppercase tracking-widest">
                        Partidos aún no disponibles / Error en API
                    </div>
                </div>

                <!-- Equipos -->
                <div v-if="activeTab === 'teams'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <div v-for="team in teams" :key="team.id || team.name" class="bg-[#1f0d36] border border-gray-800 rounded-xl p-4 text-center hover:border-fuchsia-500/50 transition-colors">
                        <div class="w-12 h-12 rounded-full bg-[#2a134a] mx-auto mb-3 flex items-center justify-center shadow-[0_0_15px_rgba(217,70,239,0.2)] overflow-hidden">
                            <img v-if="team.flag" :src="team.flag" :alt="team.name_en || team.name" class="w-full h-full object-cover">
                            <span v-else class="text-xl">🏳️</span>
                        </div>
                        <div class="text-sm font-bold text-white truncate">{{ team.name_en || team.name }}</div>
                        <div class="text-[10px] text-gray-500 uppercase mt-1">{{ team.fifa_code || 'FIFA' }}</div>
                    </div>
                    <div v-if="!teams || teams.length === 0" class="col-span-full text-center py-20 text-gray-500 font-bold uppercase tracking-widest">
                        Equipos aún no disponibles / Error en API
                    </div>
                </div>
            </div>
            
        </main>
        
        <footer class="bg-[#120422] border-t border-gray-900 py-8 mt-12 text-center">
            <p class="text-xs text-gray-500 font-bold uppercase tracking-wider">
                Powered by Open WorldCup26 API | <a href="/" class="text-fuchsia-500 hover:text-fuchsia-400">Rankit.pro</a>
            </p>
        </footer>
    </div>
</template>
