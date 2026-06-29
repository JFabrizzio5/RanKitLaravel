<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    tournament: Object,
    teams: Array,
    players: Array,
    matches: Array,
});

const getMatchPlayers = (teamId) => {
    return props.players.filter(p => p.football_team_id === teamId);
};
</script>

<template>
    <Head :title="tournament.name + ' | Rankit Fútbol'" />
    <div class="min-h-screen bg-gray-950 text-gray-300 font-sans selection:bg-emerald-500/30">
        <!-- Header Público -->
        <header class="bg-gray-900 border-b border-gray-800 py-6 sticky top-0 z-40 backdrop-blur-xl bg-gray-900/80">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="text-3xl">⚽</div>
                    <div>
                        <h1 class="text-2xl font-bold text-white leading-none">{{ tournament.name }}</h1>
                        <p class="text-sm text-emerald-400 font-bold tracking-wider uppercase mt-1">
                            {{ tournament.format === 'elimination' ? 'Eliminación Directa' : 'Liga Regular' }}
                        </p>
                    </div>
                </div>
                <div class="hidden sm:flex items-center gap-4">
                    <span class="px-3 py-1 bg-gray-800 rounded-full text-xs font-bold border border-gray-700">
                        {{ teams.length }} Equipos
                    </span>
                    <Link href="/" class="text-sm font-bold text-gray-500 hover:text-white transition-colors">Crear torneo en Rankit</Link>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex flex-col lg:flex-row gap-8">
            
            <!-- CONTENIDO PRINCIPAL -->
            <div class="w-full lg:w-2/3 space-y-8">
                
                <!-- TABLA LIGA -->
                <div v-if="tournament.format === 'league'" class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-40 h-40 bg-emerald-500/10 blur-3xl rounded-full pointer-events-none"></div>
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2 relative z-10"><i class="fa-solid fa-table-list text-emerald-500"></i> Tabla de Posiciones</h3>
                    
                    <div class="overflow-x-auto relative z-10">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs text-gray-500 uppercase border-b border-gray-800">
                                <tr>
                                    <th class="py-3 px-2">Pos</th>
                                    <th class="py-3">Equipo</th>
                                    <th class="py-3 text-center">PJ</th>
                                    <th class="py-3 text-center hidden sm:table-cell">G</th>
                                    <th class="py-3 text-center hidden sm:table-cell">E</th>
                                    <th class="py-3 text-center hidden sm:table-cell">P</th>
                                    <th class="py-3 text-center">DG</th>
                                    <th class="py-3 text-center text-emerald-400 font-bold text-base">PTS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(team, index) in teams" :key="team.id" class="border-b border-gray-800/50 hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-2 font-bold" :class="index === 0 ? 'text-emerald-400' : (index < 3 ? 'text-white' : 'text-gray-500')">{{ index + 1 }}</td>
                                    <td class="py-3 flex items-center gap-3">
                                        <div v-if="!team.logo" class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-400">{{ team.name.charAt(0) }}</div>
                                        <img v-else :src="team.logo" class="w-6 h-6 rounded-full object-cover">
                                        <span class="font-bold text-gray-200">{{ team.name }}</span>
                                    </td>
                                    <td class="py-3 text-center text-gray-400">{{ team.wins + team.draws + team.losses }}</td>
                                    <td class="py-3 text-center hidden sm:table-cell text-gray-400">{{ team.wins }}</td>
                                    <td class="py-3 text-center hidden sm:table-cell text-gray-400">{{ team.draws }}</td>
                                    <td class="py-3 text-center hidden sm:table-cell text-gray-400">{{ team.losses }}</td>
                                    <td class="py-3 text-center text-gray-400">{{ team.goals_for - team.goals_against }}</td>
                                    <td class="py-3 text-center font-bold text-emerald-400 text-base bg-emerald-500/5 rounded-r-lg">{{ team.points }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FIXTURE / RESULTADOS -->
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="fa-solid fa-calendar-days text-emerald-500"></i> Partidos y Resultados</h3>
                    
                    <div v-if="matches.length === 0" class="text-center py-12 text-gray-500">
                        El calendario de partidos aún no ha sido generado.
                    </div>
                    
                    <div v-else class="space-y-4">
                        <div v-for="m in matches" :key="m.id" class="bg-gray-950 border border-gray-800 rounded-xl p-4 flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="text-[10px] uppercase font-bold text-emerald-500 tracking-wider">
                                {{ m.phase === 'league' ? 'Jornada' : 'Ronda' }} {{ m.round }}
                            </div>
                            
                            <!-- Team 1 -->
                            <div class="flex-1 flex justify-end items-center gap-3 w-full md:w-auto">
                                <span class="font-bold text-gray-300 text-right">{{ m.team1 ? m.team1.name : 'TBD' }}</span>
                                <div v-if="m.team1 && !m.team1.logo" class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-bold">{{ m.team1.name.charAt(0) }}</div>
                                <img v-if="m.team1 && m.team1.logo" :src="m.team1.logo" class="w-8 h-8 rounded-full object-cover">
                            </div>
                            
                            <!-- Score -->
                            <div class="px-6 py-2 bg-gray-900 rounded-lg border border-gray-800 text-center min-w-[120px]">
                                <div v-if="m.status === 'done'" class="text-2xl font-bold font-mono text-white flex justify-center gap-4">
                                    <span :class="m.score1 > m.score2 ? 'text-emerald-400' : ''">{{ m.score1 }}</span>
                                    <span class="text-gray-600">-</span>
                                    <span :class="m.score2 > m.score1 ? 'text-emerald-400' : ''">{{ m.score2 }}</span>
                                </div>
                                <div v-else class="text-sm text-gray-600 font-bold">VS</div>
                                <div v-if="m.status === 'done' && m.winner_id && m.score1 === m.score2" class="text-[9px] text-yellow-500 mt-1 uppercase font-bold tracking-widest">Penales</div>
                            </div>
                            
                            <!-- Team 2 -->
                            <div class="flex-1 flex justify-start items-center gap-3 w-full md:w-auto">
                                <div v-if="m.team2 && !m.team2.logo" class="w-8 h-8 rounded-full bg-gray-800 flex items-center justify-center text-xs font-bold">{{ m.team2.name.charAt(0) }}</div>
                                <img v-if="m.team2 && m.team2.logo" :src="m.team2.logo" class="w-8 h-8 rounded-full object-cover">
                                <span class="font-bold text-gray-300 text-left">{{ m.team2 ? m.team2.name : (m.status === 'done' ? 'BYE' : 'TBD') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SIDEBAR: STATS Y EQUIPOS -->
            <div class="w-full lg:w-1/3 space-y-8">
                
                <!-- Goleadores (Top 5) -->
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-futbol text-emerald-500"></i> Tabla de Goleadores</h3>
                    <div class="space-y-3">
                        <div v-for="(p, index) in [...players].sort((a,b) => b.goals - a.goals).slice(0, 5)" :key="p.id" class="flex items-center gap-3 bg-gray-950 p-3 rounded-lg border border-gray-800">
                            <div class="w-6 text-center font-bold text-gray-500">{{ index + 1 }}</div>
                            <div class="flex-1">
                                <div class="font-bold text-sm text-gray-200">{{ p.name }}</div>
                                <div class="text-[10px] text-gray-500 uppercase">{{ teams.find(t => t.id === p.football_team_id)?.name }}</div>
                            </div>
                            <div class="text-xl font-bold font-mono text-emerald-400">{{ p.goals }}</div>
                        </div>
                        <div v-if="players.filter(p => p.goals > 0).length === 0" class="text-sm text-gray-500 text-center py-4">No hay goles registrados.</div>
                    </div>
                </div>

                <!-- Equipos (Listado visual) -->
                <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-500"></i> Equipos Participantes</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="team in teams" :key="team.id" class="bg-gray-950 border border-gray-800 rounded-lg p-3 text-center hover:border-emerald-500/50 transition-colors">
                            <img v-if="team.logo" :src="team.logo" class="w-10 h-10 rounded-full mx-auto mb-2 object-cover">
                            <div v-else class="w-10 h-10 rounded-full bg-gray-800 mx-auto mb-2 flex items-center justify-center font-bold text-gray-400">{{ team.name.charAt(0) }}</div>
                            <div class="text-xs font-bold text-gray-300 truncate">{{ team.name }}</div>
                        </div>
                    </div>
                </div>

            </div>

        </main>
        
        <footer class="bg-gray-900 border-t border-gray-800 py-8 mt-12 text-center">
            <p class="text-sm text-gray-500 font-bold uppercase tracking-wider">
                Desarrollado en <a href="/" class="text-emerald-500 hover:text-emerald-400">Rankit.pro</a>
            </p>
        </footer>
    </div>
</template>
