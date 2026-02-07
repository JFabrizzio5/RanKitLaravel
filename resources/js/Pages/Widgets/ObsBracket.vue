<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'

interface TournamentInfo {
  id: number;
  name: string;
  scoring_format?: any;
  bracket_data?: any;
}

const props = defineProps<{
    tournamentId: number;
    initialTournament?: TournamentInfo;
}>()

const tournamentData = ref<TournamentInfo>(props.initialTournament || { id: props.tournamentId, name: '' })
const isLoading = ref(true)

const parsedBracket = computed(() => {
    let raw = tournamentData.value.bracket_data;
    if (!raw) return null;
    if (typeof raw === 'string') {
        try { return JSON.parse(raw); } catch { return null; }
    }
    return raw;
})

const bracketRounds = computed(() => {
    return parsedBracket.value?.rounds || [];
})

let pollInterval: number | undefined

const loadData = async () => {
    try {
        const id = props.tournamentId
        const url = `/api/live/${id}/data`
        const res = await axios.get(url)
        
        if(res.data.tournament) {
             tournamentData.value = { ...tournamentData.value, ...res.data.tournament }
        }
    } catch (e) {
        console.error("Error polling bracket data:", e)
    } finally {
        isLoading.value = false
    }
}

onMounted(() => {
    loadData()
    // Poll every 30 seconds
    pollInterval = window.setInterval(loadData, 30000)
    
    // Inject Phosphor Icons
    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script')
        script.src = 'https://unpkg.com/@phosphor-icons/web'
        script.async = true
        document.head.appendChild(script)
    }
})

onUnmounted(() => {
    if(pollInterval) clearInterval(pollInterval)
})
</script>

<template>
    <Head title="OBS Bracket" />
    
    <div class="min-h-screen bg-transparent p-4 font-sans text-white overflow-hidden">
        <div v-if="!parsedBracket" class="flex items-center justify-center h-full">
            <div v-if="isLoading" class="text-white animate-pulse font-bold uppercase">Cargando Bracket...</div>
            <div v-else class="text-white font-bold uppercase bg-black/50 p-4 rounded">No hay bracket activo</div>
        </div>

        <div v-else class="flex flex-col h-full">
             <div class="mb-4 text-center">
                <h1 class="text-2xl font-black uppercase font-display text-white drop-shadow-md">
                    {{ tournamentData.name }} <span class="text-[var(--rankit-neon)]">Bracket</span>
                </h1>
            </div>

            <div class="flex gap-8 pb-4 overflow-x-auto no-scrollbar justify-center">
                  <div v-for="round in bracketRounds" :key="round.name" class="flex flex-col gap-4 min-w-[200px]">
                      <div class="text-sm font-bold uppercase text-center bg-black/80 py-2 rounded mb-2 text-[var(--rankit-neon)] tracking-widest border-b-2 border-[var(--rankit-neon)] box-shadow-neon">
                          {{ round.name }}
                      </div>
                      <div class="flex flex-col justify-around h-full gap-4"> 
                          <div v-for="match in round.matches" :key="match.id" 
                               class="relative bg-black/80 border border-white/20 rounded p-3 transition box-shadow-card">
                              
                              <div class="flex justify-between items-center mb-2 border-b border-dashed border-white/10 pb-1">
                                  <span class="text-[10px] font-mono text-gray-400 opacity-50">{{ match.id }}</span>
                                  <span v-if="match.winner" class="text-[9px] font-bold text-green-400">FINALIZADO</span>
                                  <span v-else class="text-[9px] font-bold text-yellow-400 animate-pulse">EN JUEGO</span>
                              </div>

                              <div class="flex flex-col gap-2">
                                  <!-- P1 -->
                                  <div class="flex justify-between items-center p-1 rounded transition"
                                       :class="match.winner === match.p1 ? 'bg-[var(--rankit-neon)]/20 text-[var(--rankit-neon)] font-bold border-l-2 border-[var(--rankit-neon)]' : 'text-gray-300'">
                                      <span class="truncate max-w-[140px] text-sm">{{ match.p1 }}</span>
                                      <span class="font-mono text-sm font-bold">{{ match.score1 || '-' }}</span>
                                  </div>
                                  <!-- P2 -->
                                  <div class="flex justify-between items-center p-1 rounded transition"
                                       :class="match.winner === match.p2 ? 'bg-[var(--rankit-neon)]/20 text-[var(--rankit-neon)] font-bold border-l-2 border-[var(--rankit-neon)]' : 'text-gray-300'">
                                      <span class="truncate max-w-[140px] text-sm">{{ match.p2 }}</span>
                                      <span class="font-mono text-sm font-bold">{{ match.score2 || '-' }}</span>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
        </div>
    </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap');

:root { --rankit-neon: #bf00ff; }
.font-display { font-family: "Chakra Petch", sans-serif; }
.font-sans { font-family: "Archivo", sans-serif; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.box-shadow-neon { box-shadow: 0 0 10px rgba(191, 0, 255, 0.3); }
.box-shadow-card { box-shadow: 0 4px 6px rgba(0,0,0,0.5); }
</style>
