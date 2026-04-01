<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

interface Team {
  id: number
  name: string
  logo: string | null
  seed: number | null
  wins: number
  losses: number
  swiss_status: 'active' | 'advanced' | 'eliminated'
  de_bracket: 'wb' | 'lb' | 'out'
  points: number
}
interface LolMatch {
  id: number
  phase: 'swiss' | 'elimination' | 'winner' | 'loser' | 'grand_final' | 'league'
  round: number
  team1_id: number
  team2_id: number | null
  winner_id: number | null
  score1: number
  score2: number
  status: 'pending' | 'done'
  team1: Team | null
  team2: Team | null
  winner: Team | null
}
interface Tournament {
  id: number
  name: string
  game: string
  format: 'elimination' | 'swiss_elimination' | 'double_elimination' | 'league'
  phase: 'pending' | 'swiss' | 'elimination' | 'league' | 'done'
  swiss_rounds_total: number
  elimination_teams: number
  swiss_wins_to_advance: number
  swiss_losses_to_eliminate: number
  swiss_first_round_manual: boolean
}

const props = defineProps<{
  tournament: Tournament
  teams: Team[]
  matches: LolMatch[]
}>()

const gameNeon = '#bf00ff'
const gameLabel = computed(() => props.tournament.game === 'valorant' ? 'Valorant' : 'League of Legends')
const gameIcon = computed(() => props.tournament.game === 'valorant' ? '🎯' : '⚔️')

// --- Computed match groups ---
const swissMatches = computed(() => props.matches.filter(m => m.phase === 'swiss'))
const elimMatches = computed(() => props.matches.filter(m => m.phase === 'elimination'))
const wbMatches = computed(() => props.matches.filter(m => m.phase === 'winner'))
const lbMatches = computed(() => props.matches.filter(m => m.phase === 'loser'))
const gfMatch = computed(() => props.matches.find(m => m.phase === 'grand_final') ?? null)
const leagueMatches = computed(() => props.matches.filter(m => m.phase === 'league'))

const swissRounds = computed(() => {
  const r: Record<number, LolMatch[]> = {}
  for (const m of swissMatches.value) { if (!r[m.round]) r[m.round] = []; r[m.round].push(m) }
  return r
})
const elimRounds = computed(() => {
  const r: Record<number, LolMatch[]> = {}
  for (const m of elimMatches.value) { if (!r[m.round]) r[m.round] = []; r[m.round].push(m) }
  return r
})
const wbRounds = computed(() => {
  const r: Record<number, LolMatch[]> = {}
  for (const m of wbMatches.value) { if (!r[m.round]) r[m.round] = []; r[m.round].push(m) }
  return r
})
const lbRounds = computed(() => {
  const r: Record<number, LolMatch[]> = {}
  for (const m of lbMatches.value) { if (!r[m.round]) r[m.round] = []; r[m.round].push(m) }
  return r
})
const leagueRounds = computed(() => {
  const r: Record<number, LolMatch[]> = {}
  for (const m of leagueMatches.value) { if (!r[m.round]) r[m.round] = []; r[m.round].push(m) }
  return r
})

const maxElimRound = computed(() => elimMatches.value.reduce((m, x) => Math.max(m, x.round), 0))

const leagueStandings = computed(() => [...props.teams].sort((a, b) => {
  if (b.points !== a.points) return b.points - a.points
  if (b.wins !== a.wins) return b.wins - a.wins
  return a.name.localeCompare(b.name)
}))

const champion = computed(() => {
  if (props.tournament.phase !== 'done') return null
  if (props.tournament.format === 'double_elimination') return gfMatch.value?.winner ?? null
  if (props.tournament.format === 'league') return leagueStandings.value[0] ?? null
  const lastRound = elimMatches.value.filter(m => m.round === maxElimRound.value)
  return lastRound.length === 1 && lastRound[0].winner ? lastRound[0].winner : null
})

function formatLabel(f: string) {
  if (f === 'swiss_elimination') return 'Suiza + Eliminación'
  if (f === 'double_elimination') return 'Doble Eliminación'
  if (f === 'league') return 'Liga'
  return 'Eliminación Directa'
}

function phaseLabel() {
  const t = props.tournament
  if (t.format === 'league') {
    if (t.phase === 'done') return '🏆 Finalizada'
    if (t.phase === 'league') return '⚽ En curso'
    return '⏳ Sin iniciar'
  }
  if (t.format === 'double_elimination') {
    if (t.phase === 'done') return '🏆 Finalizado'
    if (t.phase === 'elimination') return '🔴🔵 WB/LB en curso'
    return '⏳ Sin iniciar'
  }
  if (t.phase === 'pending') return '⏳ Sin iniciar'
  if (t.phase === 'swiss') return '⚔️ Fase Suiza'
  if (t.phase === 'elimination') return '🔵 Eliminación'
  if (t.phase === 'done') return '🏆 Finalizado'
  return t.phase
}

function labelRound(phase: string, round: number) {
  if (phase === 'swiss') return `Ronda Swiss ${round}`
  if (phase === 'league') return `Jornada ${round}`
  if (phase === 'winner') {
    const keys = Object.keys(wbRounds.value)
    const total = keys.length
    const pos = keys.indexOf(String(round))
    if (total - pos === 1) return 'WB FINAL'
    if (total - pos === 2) return 'WB SEMIFINALES'
    if (total - pos === 3) return 'WB CUARTOS'
    return `WB Ronda ${round}`
  }
  if (phase === 'loser') {
    const keys = Object.keys(lbRounds.value)
    const total = keys.length
    const pos = keys.indexOf(String(round))
    if (total - pos === 1) return 'LB FINAL'
    if (total - pos === 2) return 'LB SEMIFINALES'
    return `LB Ronda ${round}`
  }
  const rounds = Object.keys(elimRounds.value)
  const total = rounds.length
  const pos = rounds.indexOf(String(round))
  if (total - pos === 1) return 'LA FINAL'
  if (total - pos === 2) return 'SEMIFINALES'
  if (total - pos === 3) return 'CUARTOS DE FINAL'
  return `RONDA ${round}`
}

// --- Auto-refresh every 30 seconds ---
let refreshTimer: ReturnType<typeof setInterval> | null = null
const lastRefresh = ref(new Date())

function refresh() {
  window.location.reload()
}

onMounted(() => {
  refreshTimer = setInterval(() => {
    lastRefresh.value = new Date()
    // Silent reload via Inertia would be preferable; for simplicity we reload
    window.location.reload()
  }, 30000)
})

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer)
})

// --- Share ---
const linkCopied = ref(false)
function copyLink() {
  navigator.clipboard.writeText(window.location.href)
  linkCopied.value = true
  setTimeout(() => { linkCopied.value = false }, 2000)
}
</script>

<template>
  <Head :title="`${tournament.name} — RanKit`">
    <link
      href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,300;0,400;0,600;0,700;1,700&family=Archivo:wght@300;400;600;800&display=swap"
      rel="stylesheet" />
  </Head>

  <div class="min-h-screen bg-[#050505] text-white pb-20 overflow-x-hidden" style="font-family:'Archivo',sans-serif;">
    <!-- Background grid -->
    <div class="fixed inset-0 pointer-events-none opacity-[0.03]"
      style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:40px 40px;"></div>

    <!-- Top bar -->
    <div class="fixed top-0 left-0 w-full z-50 flex items-center justify-between px-5 lg:px-12 h-14 border-b border-white/5 bg-[#050505]/90 backdrop-blur-md">
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 flex items-center justify-center rounded font-black text-lg italic"
            :style="{ background: gameNeon }">R</div>
          <span class="text-xs font-black uppercase tracking-widest" :style="{ color: gameNeon }">RanKit</span>
        </div>
        <div class="w-px h-4 bg-white/10"></div>
        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ gameIcon }} {{ gameLabel }}</span>
      </div>
      <div class="flex items-center gap-2">
        <!-- Auto-refresh indicator -->
        <span class="text-[9px] text-gray-600 font-mono hidden sm:block">↻ Auto-actualiza cada 30s</span>
        <!-- Copy link button -->
        <button @click="copyLink"
          class="px-3 py-1.5 text-[10px] font-black uppercase rounded border transition-all"
          :class="linkCopied ? 'border-green-500 text-green-400 bg-green-500/10' : 'border-white/10 text-gray-400 hover:border-white/30 hover:text-white'">
          {{ linkCopied ? '✓ ¡Copiado!' : '🔗 Compartir' }}
        </button>
        <!-- Phase badge -->
        <span class="text-[10px] font-black uppercase px-2 py-1 rounded border border-white/10" :style="{ color: gameNeon }">
          {{ phaseLabel() }}
        </span>
      </div>
    </div>

    <!-- Champion banner -->
    <div v-if="champion" class="fixed top-14 left-0 w-full z-40 text-center py-2 text-sm font-black uppercase tracking-widest"
      :style="{ background: `${gameNeon}20`, color: gameNeon }">
      🏆 {{ tournament.format === 'league' ? 'Campeón de Liga' : 'Campeón del Torneo' }}: {{ champion.name }}
    </div>

    <main class="max-w-7xl mx-auto px-5 pt-24 grid grid-cols-1 lg:grid-cols-12 gap-6" :class="{ 'pt-32': !!champion }">

      <!-- LEFT: Tournament info + standings -->
      <aside class="lg:col-span-4 space-y-4">

        <!-- Tournament header card -->
        <div class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="h-[2px] w-full" :style="{ background: gameNeon }"></div>
          <div class="p-5 space-y-3">
            <h1 class="text-2xl font-black uppercase leading-tight tracking-tighter"
              style="font-family:'Chakra Petch',sans-serif">
              {{ tournament.name }}
            </h1>
            <div class="flex flex-wrap gap-2 text-[10px] font-bold uppercase">
              <span class="px-2 py-0.5 rounded bg-white/5 text-gray-400">{{ gameIcon }} {{ gameLabel }}</span>
              <span class="px-2 py-0.5 rounded" :style="{ background: `${gameNeon}20`, color: gameNeon }">
                {{ formatLabel(tournament.format) }}
              </span>
              <span class="px-2 py-0.5 rounded bg-white/5 text-gray-400">{{ teams.length }} equipos</span>
            </div>
          </div>
        </div>

        <!-- League standings -->
        <div v-if="tournament.format === 'league' && leagueMatches.length > 0"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="px-4 py-3 border-b border-white/5">
            <h2 class="text-xs font-black uppercase tracking-widest text-green-400" style="font-family:'Chakra Petch',sans-serif">
              🏆 Tabla de Posiciones</h2>
          </div>
          <div class="divide-y divide-white/5">
            <div class="flex items-center gap-2 px-4 py-1.5 text-[9px] font-bold uppercase text-gray-600">
              <span class="w-4">#</span>
              <span class="flex-1">Equipo</span>
              <span class="w-8 text-center">PJ</span>
              <span class="w-8 text-center">G</span>
              <span class="w-8 text-center">P</span>
              <span class="w-10 text-center text-green-400">PTS</span>
            </div>
            <div v-for="(team, idx) in leagueStandings" :key="team.id"
              class="flex items-center gap-2 px-4 py-2 transition-colors"
              :class="idx === 0 && tournament.phase === 'done' ? 'bg-green-500/5' : ''">
              <span class="text-[10px] font-mono text-gray-600 w-4">
                {{ idx === 0 ? '🥇' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : idx + 1 }}
              </span>
              <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 border border-white/10 flex items-center justify-center text-[8px] font-bold flex-shrink-0"
                :style="{ color: gameNeon }">
                <img v-if="team.logo" :src="team.logo" :alt="team.name[0]" class="w-full h-full object-cover" />
                <span v-else>{{ team.name[0] }}</span>
              </div>
              <span class="flex-1 text-sm font-bold"
                :class="idx === 0 && tournament.phase === 'done' ? 'text-green-400' : 'text-white'">{{ team.name }}</span>
              <span class="w-8 text-center text-[10px] font-mono text-gray-500">{{ team.wins + team.losses }}</span>
              <span class="w-8 text-center text-[10px] font-mono text-green-400">{{ team.wins }}</span>
              <span class="w-8 text-center text-[10px] font-mono text-red-400">{{ team.losses }}</span>
              <span class="w-10 text-center text-sm font-black text-yellow-400">{{ team.points }}</span>
            </div>
          </div>
        </div>

        <!-- Swiss standings -->
        <div v-if="swissMatches.length > 0" class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="px-4 py-3 border-b border-white/5">
            <h2 class="text-xs font-black uppercase tracking-widest" style="font-family:'Chakra Petch',sans-serif">
              Clasificación Swiss</h2>
          </div>
          <div class="divide-y divide-white/5">
            <div v-for="(team, idx) in [...teams].sort((a, b) => b.wins - a.wins || a.losses - b.losses)" :key="team.id"
              class="flex items-center gap-3 px-4 py-2">
              <span class="text-[10px] font-mono text-gray-600 w-4">{{ idx + 1 }}</span>
              <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 border border-white/10 flex items-center justify-center text-[8px] font-bold flex-shrink-0"
                :style="{ color: gameNeon }">
                <img v-if="team.logo" :src="team.logo" :alt="team.name[0]" class="w-full h-full object-cover" />
                <span v-else>{{ team.name[0] }}</span>
              </div>
              <span class="flex-1 text-sm font-bold"
                :class="team.swiss_status === 'advanced' ? 'text-green-400' : team.swiss_status === 'eliminated' ? 'text-red-400 line-through opacity-50' : 'text-white'">
                {{ team.name }}
              </span>
              <span class="text-[10px] font-mono" :style="{ color: gameNeon }">{{ team.wins }}W {{ team.losses }}L</span>
            </div>
          </div>
        </div>

        <!-- DE team status -->
        <div v-if="tournament.format === 'double_elimination' && tournament.phase !== 'pending'"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Estado del Bracket</h3>
          <div class="grid grid-cols-3 gap-2 text-[10px]">
            <div class="bg-purple-900/20 border border-purple-500/20 rounded p-2 text-center">
              <div class="text-gray-500 uppercase text-[8px]">WB</div>
              <div class="text-purple-400 font-black text-base">{{ teams.filter(t => t.de_bracket === 'wb').length }}</div>
            </div>
            <div class="bg-blue-900/20 border border-blue-500/20 rounded p-2 text-center">
              <div class="text-gray-500 uppercase text-[8px]">LB</div>
              <div class="text-blue-400 font-black text-base">{{ teams.filter(t => t.de_bracket === 'lb').length }}</div>
            </div>
            <div class="bg-red-900/20 border border-red-500/20 rounded p-2 text-center">
              <div class="text-gray-500 uppercase text-[8px]">Eliminados</div>
              <div class="text-red-400 font-black text-base">{{ teams.filter(t => t.de_bracket === 'out').length }}</div>
            </div>
          </div>
          <!-- Team list with DE badges -->
          <div class="space-y-1 pt-1">
            <div v-for="team in [...teams].sort((a,b) => a.name.localeCompare(b.name))" :key="team.id"
              class="flex items-center justify-between px-2 py-1 rounded"
              :class="team.de_bracket === 'out' ? 'opacity-40' : ''">
              <span class="text-xs font-bold" :class="team.de_bracket === 'out' ? 'line-through' : 'text-white'">{{ team.name }}</span>
              <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded"
                :class="team.de_bracket === 'wb' ? 'bg-purple-500/20 text-purple-400' : team.de_bracket === 'lb' ? 'bg-blue-500/20 text-blue-400' : 'bg-red-500/20 text-red-400'">
                {{ team.de_bracket === 'wb' ? 'WB' : team.de_bracket === 'lb' ? 'LB' : '❌' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Teams list (simple) when no matches yet -->
        <div v-if="matches.length === 0 && teams.length > 0"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="px-4 py-3 border-b border-white/5">
            <h2 class="text-xs font-black uppercase tracking-widest" style="font-family:'Chakra Petch',sans-serif">
              Equipos <span class="text-gray-500">({{ teams.length }})</span></h2>
          </div>
          <div class="divide-y divide-white/5">
            <div v-for="team in teams" :key="team.id" class="flex items-center gap-3 px-4 py-2">
              <span class="text-[10px] font-mono text-gray-600 w-4">{{ team.seed ?? '—' }}</span>
              <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 border border-white/10 flex items-center justify-center text-[8px] font-bold flex-shrink-0"
                :style="{ color: gameNeon }">
                <img v-if="team.logo" :src="team.logo" :alt="team.name" class="w-full h-full object-cover" />
                <span v-else>{{ team.name[0] }}</span>
              </div>
              <span class="text-sm font-bold text-white">{{ team.name }}</span>
            </div>
          </div>
        </div>

        <!-- Branding -->
        <div class="flex items-center justify-center gap-2 pt-2">
          <span class="text-[9px] font-bold tracking-[0.25em] uppercase text-white/20">POWERED BY</span>
          <span class="text-[11px] font-black uppercase tracking-wider text-white/50">RANKIT<span :style="{ color: gameNeon }">.PRO</span></span>
        </div>
      </aside>

      <!-- CENTER: Bracket / Matches -->
      <div class="lg:col-span-8 space-y-6">

        <!-- Pending start -->
        <div v-if="matches.length === 0"
          class="bg-[#0c0c0c] border border-dashed border-white/10 rounded-xl p-12 text-center space-y-2">
          <div class="text-4xl">{{ gameIcon }}</div>
          <p class="text-gray-400 text-sm font-bold">{{ tournament.name }}</p>
          <p class="text-gray-600 text-xs">El torneo aún no ha comenzado.</p>
        </div>

        <!-- Swiss rounds -->
        <template v-if="swissMatches.length > 0">
          <div v-for="(roundMatches, round) in swissRounds" :key="round">
            <div class="flex items-center gap-3 mb-3">
              <div class="h-px flex-1 bg-white/5"></div>
              <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border"
                :style="{ borderColor: `${gameNeon}40`, color: gameNeon }">
                ⚔️ {{ labelRound('swiss', Number(round)) }}
              </h3>
              <div class="h-px flex-1 bg-white/5"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-for="match in roundMatches" :key="match.id"
                class="bg-[#0c0c0c] border rounded-xl p-4 relative overflow-hidden"
                :class="match.status === 'done' ? 'border-white/5' : 'border-white/10'">
                <div v-if="match.status === 'done'" class="absolute top-0 right-0 w-1 h-full" :style="{ background: gameNeon, opacity: 0.5 }"></div>
                <div v-if="!match.team2" class="text-center text-xs text-gray-500">
                  <span class="font-bold text-white">{{ match.team1?.name }}</span> · BYE ✅
                </div>
                <div v-else>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-1 min-w-0">
                      <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px]"
                        :style="{ color: gameNeon }">
                        <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" />
                        <span v-else>{{ match.team1?.name[0] }}</span>
                      </div>
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id !== match.team1_id ? 'text-gray-500' : ''"
                        :style="match.winner_id === match.team1_id ? { color: gameNeon } : {}">
                        {{ match.team1?.name }}
                      </span>
                    </div>
                    <span class="text-xs text-gray-500 font-bold">VS</span>
                    <div class="flex items-center gap-1.5 flex-1 min-w-0 justify-end">
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id !== match.team2_id ? 'text-gray-500' : ''"
                        :style="match.winner_id === match.team2_id ? { color: gameNeon } : {}">
                        {{ match.team2?.name }}
                      </span>
                      <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px]"
                        :style="{ color: gameNeon }">
                        <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" />
                        <span v-else>{{ match.team2?.name[0] }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-if="match.status === 'done'"
                    class="text-center mt-2 text-lg font-black font-mono tracking-widest" :style="{ color: gameNeon }">
                    {{ match.score1 }} – {{ match.score2 }}
                  </div>
                  <div v-else class="text-center mt-2 text-[10px] text-gray-600 uppercase font-bold">Pendiente</div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Elimination -->
        <template v-if="elimMatches.length > 0">
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-white/5"></div>
            <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-blue-500/30 text-blue-400">
              🔵 Eliminación Directa</h3>
            <div class="h-px flex-1 bg-white/5"></div>
          </div>
          <div v-for="(roundMatches, round) in elimRounds" :key="round" class="space-y-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 text-center">
              {{ labelRound('elimination', Number(round)) }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-for="match in roundMatches" :key="match.id"
                class="bg-[#0c0c0c] rounded-xl p-4 border relative overflow-hidden"
                :class="match.status === 'done' ? 'border-white/5' : 'border-white/10'">
                <div v-if="match.status === 'done'" class="absolute top-0 right-0 w-1 h-full bg-blue-500 opacity-50"></div>
                <div v-if="!match.team2" class="text-xs text-gray-500 text-center">
                  <span class="font-bold text-white">{{ match.team1?.name }}</span> · BYE ✅
                </div>
                <div v-else>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-1 min-w-0">
                      <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px] text-blue-400">
                        <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" />
                        <span v-else>{{ match.team1?.name?.[0] }}</span>
                      </div>
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id != match.team1_id ? 'text-gray-500' : ''"
                        :style="match.status === 'done' && match.winner_id == match.team1_id ? { color: gameNeon } : {}">
                        {{ match.team1?.name ?? 'TBD' }}
                      </span>
                    </div>
                    <span class="text-xs text-gray-500 font-bold">VS</span>
                    <div class="flex items-center gap-1.5 flex-1 min-w-0 justify-end">
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id != match.team2_id ? 'text-gray-500' : ''"
                        :style="match.status === 'done' && match.winner_id == match.team2_id ? { color: gameNeon } : {}">
                        {{ match.team2?.name ?? 'TBD' }}
                      </span>
                      <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px] text-blue-400">
                        <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" />
                        <span v-else>{{ match.team2?.name?.[0] }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-if="match.status === 'done'" class="text-center mt-2 text-lg font-black font-mono tracking-widest text-blue-400">
                    {{ match.score1 }} – {{ match.score2 }}
                  </div>
                  <div v-else class="text-center mt-2 text-[10px] text-gray-600 uppercase font-bold">Pendiente</div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Double Elimination: Winner Bracket -->
        <template v-if="tournament.format === 'double_elimination' && wbMatches.length > 0">
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-white/5"></div>
            <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-purple-500/30 text-purple-400">
              🔴 Winner Bracket (WB)</h3>
            <div class="h-px flex-1 bg-white/5"></div>
          </div>
          <div class="overflow-x-auto pb-2">
            <div class="flex min-w-max">
              <template v-for="(roundMatches, round, roundIdx) in wbRounds" :key="`wb-${round}`">
                <div class="flex flex-col" style="min-width: 210px; width: 210px">
                  <p class="text-[10px] font-bold uppercase tracking-widest text-purple-400/60 text-center mb-3 px-2">
                    {{ labelRound('winner', Number(round)) }}</p>
                  <div class="flex flex-col flex-1 px-2">
                    <template v-for="(match, matchIdx) in roundMatches" :key="match.id">
                      <div v-if="matchIdx > 0 && matchIdx % 2 === 0" class="h-6"></div>
                      <div v-else-if="matchIdx > 0" class="h-2"></div>
                      <div class="relative"
                        :class="{
                          'pub-wb-match-top': matchIdx % 2 === 0 && roundMatches[matchIdx + 1] && roundIdx < Object.keys(wbRounds).length - 1,
                          'pub-wb-match-bottom': matchIdx % 2 === 1 && roundIdx < Object.keys(wbRounds).length - 1,
                          'pub-wb-match-right': roundIdx < Object.keys(wbRounds).length - 1
                        }">
                        <div class="bg-[#0c0c0c] rounded-lg border overflow-hidden"
                          :class="match.status === 'done' ? 'border-purple-500/30' : 'border-white/10'">
                          <div v-if="match.status === 'done'" class="h-0.5 bg-gradient-to-r from-purple-600 to-purple-400 opacity-70"></div>
                          <div v-if="!match.team2" class="px-3 py-3 text-[10px] text-gray-500 text-center">
                            <span class="font-bold text-white text-xs">{{ match.team1?.name }}</span> · BYE ✅
                          </div>
                          <div v-else>
                            <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-white/5"
                              :class="match.status === 'done' && match.winner_id == match.team1_id ? 'bg-purple-500/10' : ''">
                              <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                <div class="w-4 h-4 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[7px]">
                                  <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" />
                                  <span v-else class="text-purple-400/80">{{ match.team1?.name?.[0] ?? '?' }}</span>
                                </div>
                                <span class="text-[11px] font-bold truncate"
                                  :class="match.status === 'done' && match.winner_id != match.team1_id ? 'text-gray-500 line-through opacity-50' : match.status === 'done' && match.winner_id == match.team1_id ? 'text-purple-300' : 'text-white'">
                                  {{ match.team1?.name ?? 'TBD' }}
                                </span>
                              </div>
                              <span class="text-[11px] font-mono font-bold flex-shrink-0"
                                :class="match.status === 'done' ? 'text-purple-400' : 'text-gray-700'">
                                {{ match.status === 'done' ? match.score1 : '–' }}
                              </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 px-3 py-2"
                              :class="match.status === 'done' && match.winner_id == match.team2_id ? 'bg-purple-500/10' : ''">
                              <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                <div class="w-4 h-4 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[7px]">
                                  <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" />
                                  <span v-else class="text-purple-400/80">{{ match.team2?.name?.[0] ?? '?' }}</span>
                                </div>
                                <span class="text-[11px] font-bold truncate"
                                  :class="match.status === 'done' && match.winner_id != match.team2_id ? 'text-gray-500 line-through opacity-50' : match.status === 'done' && match.winner_id == match.team2_id ? 'text-purple-300' : 'text-white'">
                                  {{ match.team2?.name ?? 'TBD' }}
                                </span>
                              </div>
                              <span class="text-[11px] font-mono font-bold flex-shrink-0"
                                :class="match.status === 'done' ? 'text-purple-400' : 'text-gray-700'">
                                {{ match.status === 'done' ? match.score2 : '–' }}
                              </span>
                            </div>
                            <div v-if="match.status === 'done'" class="border-t border-white/5 px-3 py-1 text-[9px] text-gray-600">
                              Perdedor → 🔵 LB
                            </div>
                            <div v-else class="border-t border-white/5 px-3 py-1 text-[9px] text-gray-600 uppercase font-bold">
                              Pendiente
                            </div>
                          </div>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
                <div v-if="roundIdx < Object.keys(wbRounds).length - 1" class="w-3 flex-shrink-0"></div>
              </template>
            </div>
          </div>
        </template>

        <!-- Double Elimination: Loser Bracket -->
        <template v-if="tournament.format === 'double_elimination' && lbMatches.length > 0">
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-white/5"></div>
            <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-blue-500/30 text-blue-400">
              🔵 Loser Bracket (LB)</h3>
            <div class="h-px flex-1 bg-white/5"></div>
          </div>
          <div class="overflow-x-auto pb-2">
            <div class="flex min-w-max">
              <template v-for="(roundMatches, round, roundIdx) in lbRounds" :key="`lb-${round}`">
                <div class="flex flex-col" style="min-width: 210px; width: 210px">
                  <p class="text-[10px] font-bold uppercase tracking-widest text-blue-400/60 text-center mb-3 px-2">
                    {{ labelRound('loser', Number(round)) }}</p>
                  <div class="flex flex-col flex-1 px-2">
                    <template v-for="(match, matchIdx) in roundMatches" :key="match.id">
                      <div v-if="matchIdx > 0 && matchIdx % 2 === 0" class="h-6"></div>
                      <div v-else-if="matchIdx > 0" class="h-2"></div>
                      <div class="relative"
                        :class="{
                          'pub-lb-match-top': matchIdx % 2 === 0 && roundMatches[matchIdx + 1] && roundIdx < Object.keys(lbRounds).length - 1,
                          'pub-lb-match-bottom': matchIdx % 2 === 1 && roundIdx < Object.keys(lbRounds).length - 1,
                          'pub-lb-match-right': roundIdx < Object.keys(lbRounds).length - 1
                        }">
                        <div class="bg-[#0c0c0c] rounded-lg border overflow-hidden"
                          :class="match.status === 'done' ? 'border-blue-500/30' : 'border-white/10'">
                          <div v-if="match.status === 'done'" class="h-0.5 bg-gradient-to-r from-blue-600 to-blue-400 opacity-70"></div>
                          <div v-if="!match.team2" class="px-3 py-3 text-[10px] text-gray-500 text-center">
                            <span class="font-bold text-white text-xs">{{ match.team1?.name }}</span> · BYE ✅
                          </div>
                          <div v-else>
                            <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-white/5"
                              :class="match.status === 'done' && match.winner_id == match.team1_id ? 'bg-blue-500/10' : ''">
                              <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                <div class="w-4 h-4 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[7px]">
                                  <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" />
                                  <span v-else class="text-blue-400/80">{{ match.team1?.name?.[0] ?? '?' }}</span>
                                </div>
                                <span class="text-[11px] font-bold truncate"
                                  :class="match.status === 'done' && match.winner_id != match.team1_id ? 'text-gray-500 line-through opacity-50' : match.status === 'done' && match.winner_id == match.team1_id ? 'text-blue-300' : 'text-white'">
                                  {{ match.team1?.name ?? 'TBD' }}
                                </span>
                              </div>
                              <span class="text-[11px] font-mono font-bold flex-shrink-0"
                                :class="match.status === 'done' ? 'text-blue-400' : 'text-gray-700'">
                                {{ match.status === 'done' ? match.score1 : '–' }}
                              </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 px-3 py-2"
                              :class="match.status === 'done' && match.winner_id == match.team2_id ? 'bg-blue-500/10' : ''">
                              <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                <div class="w-4 h-4 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[7px]">
                                  <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" />
                                  <span v-else class="text-blue-400/80">{{ match.team2?.name?.[0] ?? '?' }}</span>
                                </div>
                                <span class="text-[11px] font-bold truncate"
                                  :class="match.status === 'done' && match.winner_id != match.team2_id ? 'text-gray-500 line-through opacity-50' : match.status === 'done' && match.winner_id == match.team2_id ? 'text-blue-300' : 'text-white'">
                                  {{ match.team2?.name ?? 'TBD' }}
                                </span>
                              </div>
                              <span class="text-[11px] font-mono font-bold flex-shrink-0"
                                :class="match.status === 'done' ? 'text-blue-400' : 'text-gray-700'">
                                {{ match.status === 'done' ? match.score2 : '–' }}
                              </span>
                            </div>
                            <div v-if="match.status === 'done'" class="border-t border-white/5 px-3 py-1 text-[9px] text-gray-600">
                              Perdedor ❌ eliminado
                            </div>
                            <div v-else class="border-t border-white/5 px-3 py-1 text-[9px] text-gray-600 uppercase font-bold">
                              Pendiente
                            </div>
                          </div>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
                <div v-if="roundIdx < Object.keys(lbRounds).length - 1" class="w-3 flex-shrink-0"></div>
              </template>
            </div>
          </div>
        </template>

        <!-- Double Elimination: Grand Final -->
        <template v-if="tournament.format === 'double_elimination' && gfMatch">
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-white/5"></div>
            <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border text-yellow-400"
              style="border-color: rgba(234,179,8,0.4); background: rgba(234,179,8,0.05)">
              🏆 GRAN FINAL — WB vs LB</h3>
            <div class="h-px flex-1 bg-white/5"></div>
          </div>
          <div class="max-w-sm mx-auto">
            <div class="bg-[#0c0c0c] rounded-2xl p-6 border relative overflow-hidden"
              :class="gfMatch.status === 'done' ? 'border-yellow-500/40' : 'border-yellow-500/20'">
              <div v-if="gfMatch.status === 'done'" class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-yellow-500 to-yellow-300"></div>
              <div class="flex items-center justify-between gap-4">
                <div class="flex flex-col items-center gap-2 flex-1">
                  <div class="w-12 h-12 rounded-full overflow-hidden bg-white/5 border-2 border-purple-500/50 flex items-center justify-center text-xl font-black text-purple-400">
                    <img v-if="gfMatch.team1?.logo" :src="gfMatch.team1.logo" class="w-full h-full object-cover" />
                    <span v-else>{{ gfMatch.team1?.name?.[0] }}</span>
                  </div>
                  <span class="text-xs font-bold text-center"
                    :class="gfMatch.status === 'done' && gfMatch.winner_id == gfMatch.team1_id ? 'text-yellow-400' : gfMatch.status === 'done' ? 'text-gray-500 line-through' : 'text-white'">
                    {{ gfMatch.team1?.name ?? 'WB Champion' }}
                  </span>
                  <span class="text-[9px] text-purple-400/70 font-bold uppercase">WB</span>
                </div>
                <div class="text-center">
                  <div v-if="gfMatch.status === 'done'" class="text-2xl font-black font-mono text-yellow-400">
                    {{ gfMatch.score1 }} – {{ gfMatch.score2 }}
                  </div>
                  <div v-else class="text-xl font-black text-gray-500">VS</div>
                  <div v-if="gfMatch.status === 'done'" class="text-[9px] text-yellow-400/70 font-bold uppercase mt-1">
                    🏆 {{ gfMatch.winner?.name }}
                  </div>
                </div>
                <div class="flex flex-col items-center gap-2 flex-1">
                  <div class="w-12 h-12 rounded-full overflow-hidden bg-white/5 border-2 border-blue-500/50 flex items-center justify-center text-xl font-black text-blue-400">
                    <img v-if="gfMatch.team2?.logo" :src="gfMatch.team2.logo" class="w-full h-full object-cover" />
                    <span v-else>{{ gfMatch.team2?.name?.[0] }}</span>
                  </div>
                  <span class="text-xs font-bold text-center"
                    :class="gfMatch.status === 'done' && gfMatch.winner_id == gfMatch.team2_id ? 'text-yellow-400' : gfMatch.status === 'done' ? 'text-gray-500 line-through' : 'text-white'">
                    {{ gfMatch.team2?.name ?? 'LB Champion' }}
                  </span>
                  <span class="text-[9px] text-blue-400/70 font-bold uppercase">LB</span>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Liga: Jornadas -->
        <template v-if="tournament.format === 'league' && leagueMatches.length > 0">
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-white/5"></div>
            <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-green-500/30 text-green-400">
              ⚽ Jornadas de Liga</h3>
            <div class="h-px flex-1 bg-white/5"></div>
          </div>
          <div v-for="(roundMatches, round) in leagueRounds" :key="`jornada-${round}`" class="space-y-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-green-400/70 text-center">
              {{ labelRound('league', Number(round)) }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-for="match in roundMatches" :key="match.id"
                class="bg-[#0c0c0c] rounded-xl p-4 border relative overflow-hidden"
                :class="match.status === 'done' ? 'border-green-500/20' : 'border-white/10'">
                <div v-if="match.status === 'done'" class="absolute top-0 right-0 w-1 h-full bg-green-500 opacity-50"></div>
                <div class="flex items-center justify-between gap-2">
                  <div class="flex items-center gap-1.5 flex-1 min-w-0">
                    <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px] text-green-400">
                      <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" />
                      <span v-else>{{ match.team1?.name?.[0] }}</span>
                    </div>
                    <div>
                      <span class="font-bold text-sm truncate block"
                        :class="match.status === 'done' && match.winner_id != match.team1_id ? 'text-gray-500' : ''"
                        :style="match.status === 'done' && match.winner_id == match.team1_id ? { color: '#4ade80' } : {}">
                        {{ match.team1?.name ?? 'TBD' }}
                      </span>
                      <span class="text-[9px] text-gray-600">{{ match.team1?.points ?? 0 }} pts</span>
                    </div>
                  </div>
                  <div class="text-center flex-shrink-0">
                    <div v-if="match.status === 'done'" class="text-base font-black font-mono text-green-400">
                      {{ match.score1 }} – {{ match.score2 }}
                    </div>
                    <span v-else class="text-xs text-gray-500 font-bold">VS</span>
                  </div>
                  <div class="flex items-center gap-1.5 flex-1 min-w-0 justify-end">
                    <div class="text-right">
                      <span class="font-bold text-sm truncate block"
                        :class="match.status === 'done' && match.winner_id != match.team2_id ? 'text-gray-500' : ''"
                        :style="match.status === 'done' && match.winner_id == match.team2_id ? { color: '#4ade80' } : {}">
                        {{ match.team2?.name ?? 'TBD' }}
                      </span>
                      <span class="text-[9px] text-gray-600">{{ match.team2?.points ?? 0 }} pts</span>
                    </div>
                    <div class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px] text-green-400">
                      <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" />
                      <span v-else>{{ match.team2?.name?.[0] }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

      </div>
    </main>
  </div>
</template>

<style scoped>
/* ── Winner Bracket connector lines (public view) ── */
.pub-wb-match-right::before {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  width: 12px;
  height: 1px;
  background: rgba(192, 132, 252, 0.3);
  transform: translateX(100%) translateY(-50%);
}

.pub-wb-match-top::after {
  content: '';
  position: absolute;
  right: -12px;
  top: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(192, 132, 252, 0.25);
}

.pub-wb-match-bottom::after {
  content: '';
  position: absolute;
  right: -12px;
  bottom: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(192, 132, 252, 0.25);
}

/* ── Loser Bracket connector lines (public view) ── */
.pub-lb-match-right::before {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  width: 12px;
  height: 1px;
  background: rgba(96, 165, 250, 0.3);
  transform: translateX(100%) translateY(-50%);
}

.pub-lb-match-top::after {
  content: '';
  position: absolute;
  right: -12px;
  top: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(96, 165, 250, 0.25);
}

.pub-lb-match-bottom::after {
  content: '';
  position: absolute;
  right: -12px;
  bottom: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(96, 165, 250, 0.25);
}
</style>
