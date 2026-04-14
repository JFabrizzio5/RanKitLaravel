<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'

// --- Types ---
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
  scheduled_at: string | null
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
  league_points_win: number
  league_points_loss: number
}

// --- Props ---
const props = defineProps<{
  tournament: Tournament
  teams: Team[]
  matches: LolMatch[]
}>()

// --- Computed ---
const gameNeon = computed(() => '#bf00ff')
const gameLabel = computed(() => props.tournament.game === 'valorant' ? 'Valorant' : 'LoL')
const gameIcon = computed(() => props.tournament.game === 'valorant' ? '🎯' : '⚔️')

const swissMatches = computed(() => props.matches.filter(m => m.phase === 'swiss'))
const elimMatches = computed(() => props.matches.filter(m => m.phase === 'elimination'))
const wbMatches = computed(() => props.matches.filter(m => m.phase === 'winner'))
const lbMatches = computed(() => props.matches.filter(m => m.phase === 'loser'))
const gfMatch = computed(() => props.matches.find(m => m.phase === 'grand_final') ?? null)
const leagueMatches = computed(() => props.matches.filter(m => m.phase === 'league'))

const maxSwissRound = computed(() => swissMatches.value.reduce((m, x) => Math.max(m, x.round), 0))
const maxElimRound = computed(() => elimMatches.value.reduce((m, x) => Math.max(m, x.round), 0))

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

const pendingSwiss = computed(() => swissMatches.value.filter(m => m.status === 'pending').length)
const allSwissDone = computed(() => swissMatches.value.length > 0 && pendingSwiss.value === 0)
const currentSwissRound = computed(() => maxSwissRound.value)

// Teams by status
const activeTeams = computed(() => props.teams.filter(t => (t.swiss_status ?? 'active') === 'active'))
const advancedTeams = computed(() => props.teams.filter(t => t.swiss_status === 'advanced'))
const eliminatedTeams = computed(() => props.teams.filter(t => t.swiss_status === 'eliminated'))

// League standings (sorted by points, then wins, then name)
const leagueStandings = computed(() => [...props.teams].sort((a, b) => {
  if (b.points !== a.points) return b.points - a.points
  if (b.wins !== a.wins) return b.wins - a.wins
  return a.name.localeCompare(b.name)
}))

const champion = computed(() => {
  if (props.tournament.phase !== 'done') return null
  if (props.tournament.format === 'double_elimination') {
    return gfMatch.value?.winner ?? null
  }
  if (props.tournament.format === 'league') {
    return leagueStandings.value[0] ?? null
  }
  const lastRound = elimMatches.value.filter(m => m.round === maxElimRound.value)
  return lastRound.length === 1 && lastRound[0].winner ? lastRound[0].winner : null
})

const canGenerateSwiss = computed(() => {
  if (props.tournament.format !== 'swiss_elimination') return false
  if (['elimination', 'done'].includes(props.tournament.phase)) return false
  if (props.tournament.swiss_first_round_manual && swissMatches.value.length === 0) return false
  if (props.tournament.swiss_rounds_total > 0 && currentSwissRound.value >= props.tournament.swiss_rounds_total) return false
  if (currentSwissRound.value > 0 && pendingSwiss.value > 0) return false
  if (activeTeams.value.length < 2) return false
  return true
})

const canAdvanceManually = computed(() => {
  if (props.tournament.format !== 'swiss_elimination') return false
  if (props.tournament.phase !== 'swiss') return false
  return allSwissDone.value
})

const canGenerateDE = computed(() =>
  props.tournament.format === 'double_elimination' &&
  props.tournament.phase === 'pending' &&
  props.teams.length >= 2
)

const canGenerateLeague = computed(() =>
  props.tournament.format === 'league' &&
  props.tournament.phase === 'pending' &&
  props.teams.length >= 2
)

// Round 1 Manual
const needsManualRound1 = computed(() =>
  props.tournament.swiss_first_round_manual &&
  props.tournament.format === 'swiss_elimination' &&
  swissMatches.value.length === 0 &&
  props.teams.length >= 2
)

// --- Widget URLs ---
const widgetUrl = computed(() => `${window.location.origin}/lol/${props.tournament.id}/widget`)
const bracketUrl = computed(() => `${window.location.origin}/lol/${props.tournament.id}/bracket`)
const publicUrl = computed(() => `${window.location.origin}/lol/${props.tournament.id}/ver`)

const widgetCopied = ref<string | null>(null)
function copyWidget(phase: string = 'all') {
  const url = phase === 'all' ? widgetUrl.value : `${widgetUrl.value}?phase=${phase}`
  navigator.clipboard.writeText(url)
  widgetCopied.value = phase
  setTimeout(() => { widgetCopied.value = null }, 2000)
}
function copyBracket() {
  navigator.clipboard.writeText(bracketUrl.value)
  widgetCopied.value = 'bracket'
  setTimeout(() => { widgetCopied.value = null }, 2000)
}
function copyPublicLink() {
  navigator.clipboard.writeText(publicUrl.value)
  widgetCopied.value = 'public'
  setTimeout(() => { widgetCopied.value = null }, 2000)
}

// --- State ---
const newTeamName = ref('')
const newTeamLogo = ref('')
const resultModal = ref<LolMatch | null>(null)
const isEditingResult = ref(false)
const editTeam = ref<Team | null>(null)
const scheduleModal = ref<LolMatch | null>(null)

const resultForm = useForm({ match_id: 0, winner_id: 0, score1: 0, score2: 0 })
const editForm = useForm({ name: '', logo: '' })
const scheduleForm = useForm({ match_id: 0, scheduled_at: '' })

// --- Manual Round 1 state ---
const manualPairs = ref<{ t1_id: number; t2_id: number | null }[]>([])
const showManualSetup = ref(false)

function initManualPairs() {
  const shuffled = [...props.teams]
  manualPairs.value = []
  for (let i = 0; i < shuffled.length - 1; i += 2) {
    manualPairs.value.push({ t1_id: shuffled[i].id, t2_id: shuffled[i + 1].id })
  }
  if (shuffled.length % 2 !== 0) {
    manualPairs.value.push({ t1_id: shuffled[shuffled.length - 1].id, t2_id: null })
  }
  showManualSetup.value = true
}

function submitManualRound1() {
  router.post(
    route('lol.manual.round1', props.tournament.id),
    { pairs: manualPairs.value.map(p => ({ team1_id: p.t1_id, team2_id: p.t2_id })) },
    { preserveScroll: true, onSuccess: () => { showManualSetup.value = false } }
  )
}

// --- Actions ---
function addTeam() {
  if (!newTeamName.value.trim()) return
  router.post(route('lol.team.add', props.tournament.id), {
    name: newTeamName.value.trim(),
    logo: newTeamLogo.value.trim() || null,
  }, { preserveScroll: true, onSuccess: () => { newTeamName.value = ''; newTeamLogo.value = '' } })
}

function removeTeam(teamId: number) {
  if (!confirm('¿Eliminar este equipo?')) return
  router.delete(route('lol.team.remove', { id: props.tournament.id, teamId }), { preserveScroll: true })
}

function openEdit(team: Team) {
  editTeam.value = team
  editForm.name = team.name
  editForm.logo = team.logo ?? ''
}

function saveEdit() {
  if (!editTeam.value) return
  editForm.put(route('lol.team.update', { id: props.tournament.id, teamId: editTeam.value.id }), {
    onSuccess: () => { editTeam.value = null },
    preserveScroll: true,
  })
}

function shuffle() { router.post(route('lol.shuffle', props.tournament.id), {}, { preserveScroll: true }) }
function sortByName() { router.post(route('lol.sort', props.tournament.id), {}, { preserveScroll: true }) }
function generate() { router.post(route('lol.generate', props.tournament.id), {}, { preserveScroll: true }) }

function advancePhase() {
  if (!confirm('¿Avanzar manualmente a la fase de Eliminación? Los equipos con status "activo" no participarán.')) return
  router.post(route('lol.advance', props.tournament.id), {}, { preserveScroll: true })
}

function openResult(match: LolMatch) {
  isEditingResult.value = false
  resultModal.value = match
  resultForm.match_id = match.id
  resultForm.winner_id = 0
  resultForm.score1 = 0
  resultForm.score2 = 0
}

function openEditResult(match: LolMatch) {
  isEditingResult.value = true
  resultModal.value = match
  resultForm.match_id = match.id
  resultForm.winner_id = match.winner_id ?? 0
  resultForm.score1 = match.score1
  resultForm.score2 = match.score2
}

function submitResult() {
  if (!resultForm.winner_id) { alert('Selecciona un ganador'); return }
  const routeName = isEditingResult.value ? 'lol.edit.result' : 'lol.result'
  resultForm.post(route(routeName, props.tournament.id), {
    onSuccess: () => { resultModal.value = null },
    preserveScroll: true,
  })
}

function openSchedule(match: LolMatch) {
  scheduleModal.value = match
  scheduleForm.match_id = match.id
  scheduleForm.scheduled_at = match.scheduled_at
    ? new Date(match.scheduled_at).toISOString().slice(0, 16)
    : ''
}

function submitSchedule() {
  if (!scheduleForm.scheduled_at) { alert('Selecciona una fecha y hora'); return }
  scheduleForm.post(route('lol.schedule', props.tournament.id), {
    onSuccess: () => { scheduleModal.value = null },
    preserveScroll: true,
  })
}

function labelRound(phase: string, round: number) {
  if (phase === 'swiss') return `Ronda Swiss ${round}`
  if (phase === 'league') return `Jornada ${round}`
  if (phase === 'winner') {
    const wbKeys = Object.keys(wbRounds.value)
    const total = wbKeys.length
    const pos = wbKeys.indexOf(String(round))
    if (total - pos === 1) return 'WB FINAL'
    if (total - pos === 2) return 'WB SEMIFINALES'
    if (total - pos === 3) return 'WB CUARTOS'
    return `WB Ronda ${round}`
  }
  if (phase === 'loser') {
    const lbKeys = Object.keys(lbRounds.value)
    const total = lbKeys.length
    const pos = lbKeys.indexOf(String(round))
    if (total - pos === 1) return 'LB FINAL'
    if (total - pos === 2) return 'LB SEMIFINALES'
    return `LB Ronda ${round}`
  }
  const rounds = phase === 'elimination' ? Object.keys(elimRounds.value) : []
  const total = rounds.length
  const pos = rounds.indexOf(String(round))
  if (total - pos === 1) return 'LA FINAL'
  if (total - pos === 2) return 'SEMIFINALES'
  if (total - pos === 3) return 'CUARTOS DE FINAL'
  return `RONDA ${round}`
}

function teamBadgeStyle(team: Team) {
  const s = team.swiss_status ?? 'active'
  if (s === 'advanced') return { background: '#15803d20', color: '#4ade80', border: '1px solid #15803d50' }
  if (s === 'eliminated') return { background: '#dc262620', color: '#f87171', border: '1px solid #dc262650' }
  return { background: '#ffffff08', color: '#9ca3af', border: '1px solid #ffffff15' }
}

function availableOpponents(pairIdx: number, currentT2: number | null) {
  const usedIds = manualPairs.value
    .flatMap((p, i) => i === pairIdx ? [] : [p.t1_id, ...(p.t2_id ? [p.t2_id] : [])])
  return props.teams.filter(t => !usedIds.includes(t.id) || t.id === currentT2)
}

function getTeamById(id: number) {
  return props.teams.find(t => t.id === id)
}

function formatScheduledAt(scheduledAt: string | null): string {
  if (!scheduledAt) return ''
  const d = new Date(scheduledAt)
  return d.toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<template>

  <Head :title="`${tournament.name} — RanKit`">
    <link
      href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,300;0,400;0,600;0,700;1,700&family=Archivo:wght@300;400;600;800&display=swap"
      rel="stylesheet" />
  </Head>

  <div class="min-h-screen bg-[#050505] text-white pb-20 overflow-x-hidden" style="font-family:'Archivo',sans-serif;">
    <div class="fixed inset-0 pointer-events-none opacity-[0.03]"
      style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:40px 40px;">
    </div>

    <!-- Top bar -->
    <div
      class="fixed top-0 left-0 w-full z-50 flex items-center justify-between px-5 lg:px-12 h-14 border-b border-white/5 bg-[#050505]/90 backdrop-blur-md">
      <div class="flex items-center gap-3">
        <Link :href="`/lol?game=${tournament.game}`"
          class="text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white transition">← Torneos</Link>
        <div class="w-px h-4 bg-white/10"></div>
        <span class="text-xs font-bold uppercase tracking-widest" :style="{ color: gameNeon }">{{ gameIcon }} {{
          tournament.name }}</span>
      </div>
      <div class="flex items-center gap-2">
        <!-- Share public link -->
        <button @click="copyPublicLink"
          class="px-2 py-1 text-[9px] font-bold uppercase rounded border transition"
          :class="widgetCopied === 'public' ? 'border-green-500 bg-green-500/10 text-green-400' : 'border-white/10 text-gray-400 hover:border-white/30 hover:text-white'">
          {{ widgetCopied === 'public' ? '✓ Link Copiado' : '🔗 Página Pública' }}
        </button>

        <!-- Widget OBS buttons — format-aware -->
        <div class="flex bg-white/5 p-0.5 rounded border border-white/10">
          <!-- Swiss + Elimination -->
          <template v-if="tournament.format === 'swiss_elimination'">
            <button @click="copyWidget('swiss')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'swiss' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'swiss' ? '¡Listo!' : 'OBS Swiss' }}
            </button>
            <button @click="copyWidget('elimination')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'elimination' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'elimination' ? '¡Listo!' : 'OBS Elim' }}
            </button>
          </template>
          <!-- Single Elimination -->
          <template v-else-if="tournament.format === 'elimination'">
            <button @click="copyWidget('elimination')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'elimination' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'elimination' ? '¡Listo!' : 'OBS Bracket' }}
            </button>
            <button @click="copyBracket" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'bracket' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'bracket' ? '¡Listo!' : 'OBS Bracket v2' }}
            </button>
          </template>
          <!-- Double Elimination -->
          <template v-else-if="tournament.format === 'double_elimination'">
            <button @click="copyWidget('winner')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'winner' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'winner' ? '¡Listo!' : 'OBS WB' }}
            </button>
            <button @click="copyWidget('de_bracket')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'de_bracket' ? 'bg-yellow-500 text-black' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'de_bracket' ? '¡Listo!' : 'OBS Bracket DE' }}
            </button>
            <button @click="copyWidget('loser')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'loser' ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'loser' ? '¡Listo!' : 'OBS LB' }}
            </button>
            <button @click="copyWidget('all')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'all' ? 'bg-yellow-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'all' ? '¡Listo!' : 'OBS Todo' }}
            </button>
          </template>
          <!-- League -->
          <template v-else-if="tournament.format === 'league'">
            <button @click="copyWidget('league')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'league' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'league' ? '¡Listo!' : 'OBS Tabla Liga' }}
            </button>
            <button @click="copyWidget('all')" class="px-2 py-1 text-[9px] font-bold uppercase rounded transition"
              :class="widgetCopied === 'all' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white'">
              {{ widgetCopied === 'all' ? '¡Listo!' : 'OBS Partidos' }}
            </button>
          </template>
        </div>

        <a :href="widgetUrl" target="_blank" class="p-1.5 text-gray-500 hover:text-white transition"
          title="Vista previa widget OBS">👁</a>

        <!-- Phase badge -->
        <span class="text-[10px] font-black uppercase px-2 py-1 rounded border border-white/10"
          :style="{ color: gameNeon }">
          {{ tournament.phase === 'pending' ? 'Sin Iniciar' :
             tournament.phase === 'swiss' ? `Swiss R${currentSwissRound}` :
             tournament.phase === 'elimination' && tournament.format === 'double_elimination' ? '🔴🔵 WB/LB' :
             tournament.phase === 'elimination' ? 'Eliminación' :
             tournament.phase === 'league' ? '⚽ Liga' :
             '🏆 Finalizado' }}
        </span>
      </div>
    </div>

    <!-- Champion banner -->
    <div v-if="champion"
      class="fixed top-14 left-0 w-full z-40 text-center py-2 text-sm font-black uppercase tracking-widest"
      :style="{ background: `${gameNeon}20`, color: gameNeon }">
      🏆 {{ tournament.format === 'league' ? 'Campeón de Liga' : 'Campeón' }}: {{ champion.name }}
    </div>

    <main class="max-w-7xl mx-auto px-5 pt-24 grid grid-cols-1 lg:grid-cols-12 gap-6" :class="{ 'pt-32': !!champion }">

      <!-- ═══ LEFT: TEAMS PANEL ═══ -->
      <aside class="lg:col-span-4 space-y-4">

        <div class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-white/5">
            <h2 class="text-xs font-black uppercase tracking-widest" style="font-family:'Chakra Petch',sans-serif">
              Equipos <span class="text-gray-500">({{ teams.length }})</span></h2>
            <div class="flex gap-1">
              <button @click="shuffle" title="Aleatorio"
                class="p-1.5 text-gray-500 hover:text-white border border-white/10 rounded text-xs transition">🔀</button>
              <button @click="sortByName" title="A-Z"
                class="p-1.5 text-gray-500 hover:text-white border border-white/10 rounded text-xs transition">🔤</button>
            </div>
          </div>

          <!-- Add team -->
          <div class="px-4 py-3 border-b border-white/5 space-y-2">
            <div class="flex gap-2">
              <input v-model="newTeamName" type="text" placeholder="Nombre del equipo..."
                class="flex-1 bg-white/5 border border-white/10 rounded px-2 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-purple-500 transition"
                @keyup.enter="addTeam" />
              <button @click="addTeam" class="px-3 py-1.5 text-xs font-bold rounded text-black"
                :style="{ background: gameNeon }">+</button>
            </div>
            <input v-model="newTeamLogo" type="text" placeholder="URL logo (opcional)..."
              class="w-full bg-white/5 border border-white/10 rounded px-2 py-1.5 text-xs text-white placeholder-gray-600 focus:outline-none focus:border-yellow-500 transition" />
          </div>

          <!-- Team list with status badges -->
          <div class="divide-y divide-white/5 max-h-80 overflow-y-auto">
            <div v-if="teams.length === 0" class="px-4 py-6 text-center text-gray-600 text-xs">Agrega equipos para
              comenzar</div>
            <div v-for="team in teams" :key="team.id"
              class="flex items-center justify-between px-4 py-2 hover:bg-white/5 transition group">
              <div class="flex items-center gap-2">
                <span class="text-[10px] font-mono text-gray-600 w-4">{{ team.seed ?? '—' }}</span>
                <div
                  class="w-6 h-6 rounded-full overflow-hidden bg-white/5 border border-white/10 flex items-center justify-center text-[9px] font-bold flex-shrink-0"
                  :style="{ color: gameNeon }">
                  <img v-if="team.logo" :src="team.logo" :alt="team.name" class="w-full h-full object-cover" />
                  <span v-else>{{ team.name[0] }}</span>
                </div>
                <span class="text-sm font-bold text-white">{{ team.name }}</span>
                <!-- Swiss status badge -->
                <span v-if="tournament.format === 'swiss_elimination' && team.swiss_status === 'advanced'"
                  class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded"
                  style="background:#15803d20;color:#4ade80;">✅ Clasificado</span>
                <span v-else-if="tournament.format === 'swiss_elimination' && team.swiss_status === 'eliminated'"
                  class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded"
                  style="background:#dc262620;color:#f87171;">❌ Eliminado</span>
                <!-- DE bracket badge -->
                <span v-if="tournament.format === 'double_elimination' && team.de_bracket === 'lb'"
                  class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded"
                  style="background:#2563eb20;color:#60a5fa;">🔵 LB</span>
                <span v-else-if="tournament.format === 'double_elimination' && team.de_bracket === 'out'"
                  class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded"
                  style="background:#dc262620;color:#f87171;">❌ Eliminado</span>
              </div>
              <div class="flex items-center gap-2">
                <!-- League points -->
                <span v-if="tournament.format === 'league'" class="text-[10px] font-mono text-yellow-400 font-black">{{ team.points }}pts</span>
                <span v-else class="text-[10px] font-mono" :style="{ color: gameNeon }">{{ team.wins }}W / {{ team.losses }}L</span>
                <button @click="openEdit(team)"
                  class="text-gray-700 hover:text-white transition text-xs opacity-0 group-hover:opacity-100">✎</button>
                <button @click="removeTeam(team.id)"
                  class="text-gray-700 hover:text-red-400 transition text-xs opacity-0 group-hover:opacity-100">✕</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="space-y-2">
          <!-- Round 1 Manual button -->
          <button v-if="needsManualRound1" @click="initManualPairs"
            class="w-full py-2.5 text-xs font-black uppercase rounded border transition-all border-yellow-500/60 text-yellow-400 hover:bg-yellow-500/10">
            ✏️ Configurar Round 1 Manualmente
          </button>

          <!-- Generate next swiss round -->
          <button v-if="canGenerateSwiss" @click="generate"
            class="w-full py-2.5 text-xs font-black uppercase rounded border transition-all"
            :style="{ borderColor: gameNeon, color: gameNeon }"
            @mouseenter="(e: any) => e.currentTarget.style.background = `${gameNeon}15`"
            @mouseleave="(e: any) => e.currentTarget.style.background = 'transparent'">
            ⚔️ Generar Ronda Swiss {{ currentSwissRound + 1 }}
          </button>

          <!-- Elimination solo format -->
          <button
            v-else-if="tournament.format === 'elimination' && tournament.phase === 'elimination' && elimMatches.length === 0"
            @click="generate" class="w-full py-2.5 text-xs font-black uppercase rounded border transition-all"
            :style="{ borderColor: gameNeon, color: gameNeon }"
            @mouseenter="(e: any) => e.currentTarget.style.background = `${gameNeon}15`"
            @mouseleave="(e: any) => e.currentTarget.style.background = 'transparent'">
            🔵 Generar Bracket Eliminación
          </button>

          <!-- Double Elimination generate -->
          <button v-if="canGenerateDE" @click="generate"
            class="w-full py-2.5 text-xs font-black uppercase rounded border transition-all border-red-500/60 text-red-400 hover:bg-red-500/10">
            🔴🔵 Generar Doble Eliminación
          </button>

          <!-- League generate -->
          <button v-if="canGenerateLeague" @click="generate"
            class="w-full py-2.5 text-xs font-black uppercase rounded border transition-all border-green-500/60 text-green-400 hover:bg-green-500/10">
            🏆 Generar Jornadas de Liga
          </button>

          <!-- Manual advance (swiss) -->
          <button v-if="canAdvanceManually" @click="advancePhase"
            class="w-full py-2.5 text-xs font-black uppercase rounded border border-blue-500/60 text-blue-400 transition-all hover:bg-blue-500/10">
            🔵 Avanzar a Eliminación →
          </button>
        </div>

        <!-- Swiss Config Summary -->
        <div v-if="tournament.format === 'swiss_elimination'"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Configuración Swiss</h3>
          <div class="grid grid-cols-2 gap-2 text-[10px]">
            <div class="bg-green-900/20 border border-green-500/20 rounded p-2">
              <div class="text-gray-500 uppercase">Clasifica con</div>
              <div class="text-green-400 font-black text-base">{{ tournament.swiss_wins_to_advance }}W</div>
            </div>
            <div class="bg-red-900/20 border border-red-500/20 rounded p-2">
              <div class="text-gray-500 uppercase">Eliminado con</div>
              <div class="text-red-400 font-black text-base">{{ tournament.swiss_losses_to_eliminate }}L</div>
            </div>
          </div>
          <div class="flex gap-2 text-[9px] font-mono text-gray-600">
            <span>✅ {{ advancedTeams.length }} clasificados</span>
            <span>·</span>
            <span>⚔️ {{ activeTeams.length }} activos</span>
            <span>·</span>
            <span>❌ {{ eliminatedTeams.length }} eliminados</span>
          </div>
        </div>

        <!-- League Config Summary -->
        <div v-if="tournament.format === 'league'"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Puntos de Liga</h3>
          <div class="grid grid-cols-2 gap-2 text-[10px]">
            <div class="bg-green-900/20 border border-green-500/20 rounded p-2">
              <div class="text-gray-500 uppercase">Victoria</div>
              <div class="text-green-400 font-black text-base">+{{ tournament.league_points_win ?? 3 }} pts</div>
            </div>
            <div class="bg-red-900/20 border border-red-500/20 rounded p-2">
              <div class="text-gray-500 uppercase">Derrota</div>
              <div class="text-red-400 font-black text-base">+{{ tournament.league_points_loss ?? 0 }} pts</div>
            </div>
          </div>
        </div>

        <!-- Double Elimination info panel -->
        <div v-if="tournament.format === 'double_elimination' && tournament.phase !== 'pending'"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl p-4 space-y-2">
          <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Estado DE</h3>
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
        </div>

        <!-- League Standings -->
        <div v-if="tournament.format === 'league' && leagueMatches.length > 0"
          class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="px-4 py-3 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-xs font-black uppercase tracking-widest text-green-400" style="font-family:'Chakra Petch',sans-serif">
              🏆 Tabla de Posiciones</h2>
          </div>
          <div class="divide-y divide-white/5">
            <!-- Header -->
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
              :class="idx === 0 && tournament.phase === 'done' ? 'bg-green-500/5' : 'hover:bg-white/5'">
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

        <!-- Standings swiss -->
        <div v-if="swissMatches.length > 0" class="bg-[#0c0c0c] border border-white/5 rounded-xl overflow-hidden">
          <div class="px-4 py-3 border-b border-white/5">
            <h2 class="text-xs font-black uppercase tracking-widest" style="font-family:'Chakra Petch',sans-serif">
              Clasificación Swiss</h2>
          </div>
          <div class="divide-y divide-white/5">
            <div v-for="(team, idx) in [...teams].sort((a, b) => b.wins - a.wins || a.losses - b.losses)" :key="team.id"
              class="flex items-center gap-3 px-4 py-2">
              <span class="text-[10px] font-mono text-gray-600 w-4">{{ idx + 1 }}</span>
              <div
                class="w-5 h-5 rounded-full overflow-hidden bg-white/5 border border-white/10 flex items-center justify-center text-[8px] font-bold flex-shrink-0"
                :style="{ color: gameNeon }">
                <img v-if="team.logo" :src="team.logo" :alt="team.name[0]" class="w-full h-full object-cover" />
                <span v-else>{{ team.name[0] }}</span>
              </div>
              <span class="flex-1 text-sm font-bold"
                :class="team.swiss_status === 'advanced' ? 'text-green-400' : team.swiss_status === 'eliminated' ? 'text-red-400 line-through opacity-50' : 'text-white'">{{
                  team.name }}</span>
              <span class="text-[10px] font-mono" :style="{ color: gameNeon }">{{ team.wins }}W {{ team.losses
              }}L</span>
            </div>
          </div>
        </div>
      </aside>

      <!-- ═══ CENTER: BRACKET ═══ -->
      <div class="lg:col-span-8 space-y-6">

        <!-- Manual Round 1 Setup Panel -->
        <div v-if="showManualSetup" class="bg-[#0c0c0c] border border-yellow-500/30 rounded-xl overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 border-b border-yellow-500/20">
            <h3 class="text-xs font-black uppercase tracking-widest text-yellow-400">✏️ Configurar Round 1</h3>
            <button @click="showManualSetup = false" class="text-gray-500 hover:text-white text-sm">✕</button>
          </div>
          <div class="p-4 space-y-3">
            <div v-for="(pair, idx) in manualPairs" :key="idx" class="flex items-center gap-3">
              <span class="text-[10px] font-bold text-gray-500 w-12 text-right">Par {{ idx + 1 }}</span>
              <!-- Team 1 -->
              <select v-model="pair.t1_id"
                class="flex-1 bg-white/5 border border-white/10 rounded px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                <option v-for="t in props.teams" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <span class="text-gray-500 text-xs font-bold">VS</span>
              <!-- Team 2 (nullable for BYE) -->
              <select v-model="pair.t2_id"
                class="flex-1 bg-white/5 border border-white/10 rounded px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500">
                <option :value="null">— BYE —</option>
                <option v-for="t in availableOpponents(idx, pair.t2_id)" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>
            <button @click="submitManualRound1" class="w-full py-2.5 text-xs font-black uppercase rounded text-black"
              :style="{ background: gameNeon }">
              Confirmar Round 1
            </button>
          </div>
        </div>

        <!-- Swiss rounds -->
        <template v-if="tournament.format === 'swiss_elimination' && swissMatches.length > 0">
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
                class="bg-[#0c0c0c] border rounded-xl p-4 relative overflow-hidden transition-all"
                :class="match.status === 'done' ? 'border-white/5' : 'border-white/10'">
                <div v-if="match.status === 'done'" class="absolute top-0 right-0 w-1 h-full"
                  :style="{ background: gameNeon, opacity: 0.5 }"></div>

                <div v-if="!match.team2" class="text-center text-xs text-gray-500">
                  <span class="font-bold text-white">{{ match.team1?.name }}</span> · BYE ✅
                </div>
                <div v-else>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-1 min-w-0">
                      <div
                        class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px]"
                        :style="{ color: gameNeon }">
                        <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" /><span
                          v-else>{{ match.team1?.name[0] }}</span>
                      </div>
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id !== match.team1_id ? 'text-gray-500' : ''"
                        :style="match.winner_id === match.team1_id ? { color: gameNeon } : {}">{{ match.team1?.name
                        }}</span>
                    </div>
                    <span class="text-xs text-gray-500 font-bold">VS</span>
                    <div class="flex items-center gap-1.5 flex-1 min-w-0 justify-end">
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id !== match.team2_id ? 'text-gray-500' : ''"
                        :style="match.winner_id === match.team2_id ? { color: gameNeon } : {}">{{ match.team2?.name
                        }}</span>
                      <div
                        class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px]"
                        :style="{ color: gameNeon }">
                        <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" /><span
                          v-else>{{ match.team2?.name[0] }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-if="match.status === 'done'"
                    class="text-center mt-2 text-lg font-black font-mono tracking-widest" :style="{ color: gameNeon }">
                    {{ match.score1 }} – {{ match.score2 }}
                  </div>
                  <button v-if="match.status === 'pending'" @click="openResult(match)"
                    class="mt-3 w-full text-xs font-bold uppercase py-1.5 rounded border transition-all"
                    :style="{ borderColor: gameNeon, color: gameNeon }"
                    @mouseenter="(e: any) => e.currentTarget.style.background = `${gameNeon}15`"
                    @mouseleave="(e: any) => e.currentTarget.style.background = 'transparent'">
                    Registrar resultado →
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- Empty state -->
        <div v-if="matches.length === 0 && teams.length >= 2"
          class="bg-[#0c0c0c] border border-dashed border-white/10 rounded-xl p-12 text-center space-y-3">
          <p class="text-gray-500 text-sm">
            {{ tournament.format === 'swiss_elimination'
              ? (tournament.swiss_first_round_manual ? 'Configura los emparejamientos del Round 1.' : 'Genera la primera ronda Swiss para comenzar.')
              : tournament.format === 'double_elimination'
                ? 'Genera el bracket de Doble Eliminación para comenzar.'
                : tournament.format === 'league'
                  ? 'Genera las jornadas de Liga para comenzar.'
                  : 'Genera el bracket de eliminación para comenzar.' }}
          </p>
          <button v-if="tournament.format === 'elimination' || tournament.format === 'double_elimination' || tournament.format === 'league'" @click="generate"
            class="px-6 py-2 text-xs font-bold uppercase rounded border transition-all"
            :style="{ borderColor: gameNeon, color: gameNeon }">Generar →</button>
          <button v-if="tournament.format === 'swiss_elimination' && !tournament.swiss_first_round_manual" @click="generate"
            class="px-6 py-2 text-xs font-bold uppercase rounded border transition-all"
            :style="{ borderColor: gameNeon, color: gameNeon }">Generar bracket →</button>
          <button v-if="tournament.swiss_first_round_manual && swissMatches.length === 0" @click="initManualPairs"
            class="px-6 py-2 text-xs font-bold uppercase rounded border border-yellow-500/50 text-yellow-400 hover:bg-yellow-500/10 transition-all">✏️
            Configurar Round 1 →</button>
        </div>
        <div v-if="matches.length === 0 && teams.length < 2"
          class="bg-[#0c0c0c] border border-dashed border-white/10 rounded-xl p-12 text-center">
          <p class="text-gray-500 text-sm">Agrega al menos 2 equipos para generar el bracket.</p>
        </div>

        <!-- Elimination -->
        <template v-if="elimMatches.length > 0">
          <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-white/5"></div>
            <h3
              class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-blue-500/30 text-blue-400">
              🔵 Eliminación Directa</h3>
            <div class="h-px flex-1 bg-white/5"></div>
          </div>
          <div v-for="(roundMatches, round) in elimRounds" :key="round" class="space-y-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 text-center">{{
              labelRound('elimination', Number(round)) }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div v-for="match in roundMatches" :key="match.id"
                class="bg-[#0c0c0c] rounded-xl p-4 border transition-all relative overflow-hidden"
                :class="match.status === 'done' ? 'border-white/5' : 'border-white/10'">
                <div v-if="match.status === 'done'" class="absolute top-0 right-0 w-1 h-full bg-blue-500 opacity-50">
                </div>
                <div v-if="!match.team2" class="text-xs text-gray-500 text-center"><span class="font-bold text-white">{{
                  match.team1?.name }}</span> · BYE ✅</div>
                <div v-else>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-1 min-w-0">
                      <div
                        class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px] text-blue-400">
                        <img v-if="match.team1?.logo" :src="match.team1.logo" class="w-full h-full object-cover" /><span
                          v-else>{{ match.team1?.name?.[0] }}</span>
                      </div>
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id != match.team1_id ? 'text-gray-500' : ''"
                        :style="match.status === 'done' && match.winner_id == match.team1_id ? { color: gameNeon } : {}">{{
                          match.team1?.name ?? 'TBD' }}</span>
                    </div>
                    <span class="text-xs text-gray-500 font-bold">VS</span>
                    <div class="flex items-center gap-1.5 flex-1 min-w-0 justify-end">
                      <span class="font-bold text-sm truncate"
                        :class="match.status === 'done' && match.winner_id != match.team2_id ? 'text-gray-500' : ''"
                        :style="match.status === 'done' && match.winner_id == match.team2_id ? { color: gameNeon } : {}">{{
                          match.team2?.name ?? 'TBD' }}</span>
                      <div
                        class="w-5 h-5 rounded-full overflow-hidden bg-white/5 flex-shrink-0 flex items-center justify-center text-[8px] text-blue-400">
                        <img v-if="match.team2?.logo" :src="match.team2.logo" class="w-full h-full object-cover" /><span
                          v-else>{{ match.team2?.name?.[0] }}</span>
                      </div>
                    </div>
                  </div>
                  <div v-if="match.status === 'done'"
                    class="text-center mt-2 text-lg font-black font-mono tracking-widest text-blue-400">{{ match.score1
                    }} – {{ match.score2 }}</div>
                  <button v-if="match.status === 'pending'" @click="openResult(match)"
                    class="mt-3 w-full text-xs font-bold uppercase py-1.5 rounded border border-blue-500/40 text-blue-400 transition-all hover:bg-blue-500/10">
                    Registrar resultado →
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>

        <!-- ═══ DOUBLE ELIMINATION BRACKETS ═══ -->
        <template v-if="tournament.format === 'double_elimination' && (wbMatches.length > 0 || lbMatches.length > 0 || gfMatch)">

          <!-- Winner Bracket -->
          <template v-if="wbMatches.length > 0">
            <div class="flex items-center gap-3">
              <div class="h-px flex-1 bg-white/5"></div>
              <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-purple-500/30 text-purple-400">
                🔴 Winner Bracket (WB)</h3>
              <div class="h-px flex-1 bg-white/5"></div>
            </div>
            <div class="w-full pb-2">
              <div class="flex w-full">
                <template v-for="(roundMatches, round, roundIdx) in wbRounds" :key="`wb-${round}`">
                  <div class="flex flex-col flex-1 min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-purple-400/60 text-center mb-3 px-2">
                      {{ labelRound('winner', Number(round)) }}</p>
                    <div class="flex flex-col flex-1 px-2">
                      <template v-for="(match, matchIdx) in roundMatches" :key="match.id">
                        <div v-if="matchIdx > 0 && matchIdx % 2 === 0" class="h-6"></div>
                        <div v-else-if="matchIdx > 0" class="h-2"></div>
                        <div class="relative"
                          :class="{
                            'wb-match-top': matchIdx % 2 === 0 && roundMatches[matchIdx + 1] && roundIdx < Object.keys(wbRounds).length - 1,
                            'wb-match-bottom': matchIdx % 2 === 1 && roundIdx < Object.keys(wbRounds).length - 1,
                            'wb-match-right': roundIdx < Object.keys(wbRounds).length - 1
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
                              <div v-if="match.status === 'pending'" class="border-t border-white/5 px-3 py-1.5">
                                <div v-if="match.scheduled_at" class="text-[9px] text-purple-400/70 text-center mb-1">
                                  🕐 {{ formatScheduledAt(match.scheduled_at) }}
                                </div>
                                <div class="flex gap-1.5">
                                  <button @click="openSchedule(match)"
                                    class="flex-1 text-[10px] font-bold uppercase py-1 rounded border border-purple-500/20 text-purple-400/60 transition-all hover:bg-purple-500/10">
                                    📅 Horario
                                  </button>
                                  <button @click="openResult(match)"
                                    class="flex-1 text-[10px] font-bold uppercase py-1 rounded border border-purple-500/40 text-purple-400 transition-all hover:bg-purple-500/10">
                                    Registrar →
                                  </button>
                                </div>
                              </div>
                              <div v-if="match.status === 'done'" class="border-t border-white/5 px-3 py-1 text-[9px] text-gray-600">
                                Perdedor → 🔵 LB
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

          <!-- Loser Bracket -->
          <template v-if="lbMatches.length > 0">
            <div class="flex items-center gap-3">
              <div class="h-px flex-1 bg-white/5"></div>
              <h3 class="text-xs font-black uppercase tracking-widest px-3 py-1 rounded border border-blue-500/30 text-blue-400">
                🔵 Loser Bracket (LB)</h3>
              <div class="h-px flex-1 bg-white/5"></div>
            </div>
            <div class="w-full pb-2">
              <div class="flex w-full">
                <template v-for="(roundMatches, round, roundIdx) in lbRounds" :key="`lb-${round}`">
                  <div class="flex flex-col flex-1 min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-blue-400/60 text-center mb-3 px-2">
                      {{ labelRound('loser', Number(round)) }}</p>
                    <div class="flex flex-col flex-1 px-2">
                      <template v-for="(match, matchIdx) in roundMatches" :key="match.id">
                        <div v-if="matchIdx > 0 && matchIdx % 2 === 0" class="h-6"></div>
                        <div v-else-if="matchIdx > 0" class="h-2"></div>
                        <div class="relative"
                          :class="{
                            'lb-match-top': matchIdx % 2 === 0 && roundMatches[matchIdx + 1] && roundIdx < Object.keys(lbRounds).length - 1,
                            'lb-match-bottom': matchIdx % 2 === 1 && roundIdx < Object.keys(lbRounds).length - 1,
                            'lb-match-right': roundIdx < Object.keys(lbRounds).length - 1
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
                              <div v-if="match.status === 'pending'" class="border-t border-white/5 px-3 py-1.5">
                                <div v-if="match.scheduled_at" class="text-[9px] text-blue-400/70 text-center mb-1">
                                  🕐 {{ formatScheduledAt(match.scheduled_at) }}
                                </div>
                                <div class="flex gap-1.5">
                                  <button @click="openSchedule(match)"
                                    class="flex-1 text-[10px] font-bold uppercase py-1 rounded border border-blue-500/20 text-blue-400/60 transition-all hover:bg-blue-500/10">
                                    📅 Horario
                                  </button>
                                  <button @click="openResult(match)"
                                    class="flex-1 text-[10px] font-bold uppercase py-1 rounded border border-blue-500/40 text-blue-400 transition-all hover:bg-blue-500/10">
                                    Registrar →
                                  </button>
                                </div>
                              </div>
                              <div v-if="match.status === 'done'" class="border-t border-white/5 px-3 py-1 text-[9px] text-gray-600">
                                Perdedor ❌ eliminado
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

          <!-- Grand Final -->
          <template v-if="gfMatch">
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
                <div v-if="gfMatch.status === 'pending'" class="mt-4 space-y-2">
                  <div v-if="gfMatch.scheduled_at" class="text-[10px] text-yellow-400/70 text-center">
                    🕐 {{ formatScheduledAt(gfMatch.scheduled_at) }}
                  </div>
                  <div class="flex gap-2">
                    <button @click="openSchedule(gfMatch)"
                      class="flex-1 text-xs font-bold uppercase py-2 rounded border border-yellow-500/20 text-yellow-400/60 transition-all hover:bg-yellow-500/10">
                      📅 Horario
                    </button>
                    <button @click="openResult(gfMatch)"
                      class="flex-1 text-xs font-bold uppercase py-2 rounded border border-yellow-500/40 text-yellow-400 transition-all hover:bg-yellow-500/10">
                      🏆 Registrar resultado →
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </template>

        <!-- ═══ LIGA: JORNADAS ═══ -->
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
                class="bg-[#0c0c0c] rounded-xl p-4 border transition-all relative overflow-hidden"
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
                <div v-if="match.status === 'pending'" class="mt-3 space-y-1.5">
                  <div v-if="match.scheduled_at" class="text-[9px] text-green-400/70 text-center">
                    🕐 {{ formatScheduledAt(match.scheduled_at) }}
                  </div>
                  <div class="flex gap-1.5">
                    <button @click="openSchedule(match)"
                      class="flex-1 text-xs font-bold uppercase py-1.5 rounded border border-green-500/20 text-green-400/60 transition-all hover:bg-green-500/10">
                      📅 Horario
                    </button>
                    <button @click="openResult(match)"
                      class="flex-1 text-xs font-bold uppercase py-1.5 rounded border border-green-500/40 text-green-400 transition-all hover:bg-green-500/10">
                      Registrar →
                    </button>
                  </div>
                </div>
                <div v-if="match.status === 'done'" class="mt-3">
                  <button @click="openEditResult(match)"
                    class="w-full text-xs font-bold uppercase py-1.5 rounded border border-yellow-500/20 text-yellow-400/60 transition-all hover:bg-yellow-500/10 hover:text-yellow-400">
                    ✏️ Corregir resultado
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>

      </div>
    </main>

    <!-- ═══ RESULT MODAL ═══ -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="resultModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4"
          @click.self="resultModal = null">
          <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
          <div
            class="relative z-10 w-full max-w-sm bg-[#0e0e0e] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
              <h2 class="font-black uppercase text-base tracking-tight" style="font-family:'Chakra Petch',sans-serif">
                {{ isEditingResult ? 'Corregir Resultado' : 'Resultado' }}</h2>
              <button @click="resultModal = null" class="text-gray-500 hover:text-white transition text-xl">×</button>
            </div>
            <form @submit.prevent="submitResult" class="p-5 space-y-4">
              <div class="text-center text-sm font-bold">{{ resultModal.team1?.name }} <span
                  class="text-gray-500">vs</span>
                {{ resultModal.team2?.name }}</div>
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Ganador</label>
                <div class="grid grid-cols-2 gap-2">
                  <button type="button" @click="resultForm.winner_id = resultModal!.team1_id"
                    class="py-3 rounded-lg border text-xs font-bold uppercase transition-all"
                    :class="resultForm.winner_id === resultModal.team1_id ? 'border-purple-500 bg-purple-500/10 text-purple-400' : 'border-white/10 text-gray-400 hover:border-white/30'">
                    {{ resultModal.team1?.name }}
                  </button>
                  <button type="button" @click="resultForm.winner_id = resultModal!.team2_id!"
                    class="py-3 rounded-lg border text-xs font-bold uppercase transition-all"
                    :class="resultForm.winner_id === resultModal.team2_id ? 'border-purple-500 bg-purple-500/10 text-purple-400' : 'border-white/10 text-gray-400 hover:border-white/30'">
                    {{ resultModal.team2?.name }}
                  </button>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Score {{
                    resultModal.team1?.name }}</label>
                  <input v-model.number="resultForm.score1" type="number" min="0"
                    class="w-full bg-white/5 border border-white/10 rounded px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 transition" />
                </div>
                <div>
                  <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Score {{
                    resultModal.team2?.name }}</label>
                  <input v-model.number="resultForm.score2" type="number" min="0"
                    class="w-full bg-white/5 border border-white/10 rounded px-2 py-1.5 text-xs text-white focus:outline-none focus:border-purple-500 transition" />
                </div>
              </div>
              <div class="flex gap-2 pt-1">
                <button type="button" @click="resultModal = null"
                  class="flex-1 py-2 text-xs font-bold uppercase border border-white/10 text-gray-400 rounded-lg hover:border-white/30 transition">Cancelar</button>
                <button type="submit" :disabled="resultForm.processing || !resultForm.winner_id"
                  class="flex-1 py-2 text-xs font-bold uppercase rounded-lg text-black disabled:opacity-40"
                  :style="{ background: isEditingResult ? '#facc15' : gameNeon }">
                  {{ resultForm.processing ? 'Guardando...' : (isEditingResult ? 'Corregir' : 'Confirmar') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ SCHEDULE MODAL ═══ -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="scheduleModal" class="fixed inset-0 z-[200] flex items-center justify-center p-4"
          @click.self="scheduleModal = null">
          <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
          <div
            class="relative z-10 w-full max-w-sm bg-[#0e0e0e] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
              <h2 class="font-black uppercase text-base tracking-tight" style="font-family:'Chakra Petch',sans-serif">
                📅 Registrar Horario</h2>
              <button @click="scheduleModal = null" class="text-gray-500 hover:text-white transition text-xl">×</button>
            </div>
            <form @submit.prevent="submitSchedule" class="p-5 space-y-4">
              <div class="text-center text-sm font-bold text-gray-400">
                {{ scheduleModal.team1?.name }} <span class="text-gray-600">vs</span> {{ scheduleModal.team2?.name }}
              </div>
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">Fecha y Hora del Partido</label>
                <input v-model="scheduleForm.scheduled_at" type="datetime-local"
                  class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-purple-500 transition" />
              </div>
              <div class="flex gap-2 pt-1">
                <button type="button" @click="scheduleModal = null"
                  class="flex-1 py-2 text-xs font-bold uppercase border border-white/10 text-gray-400 rounded-lg hover:border-white/30 transition">Cancelar</button>
                <button type="submit" :disabled="scheduleForm.processing"
                  class="flex-1 py-2 text-xs font-bold uppercase rounded-lg text-black disabled:opacity-40"
                  :style="{ background: gameNeon }">
                  {{ scheduleForm.processing ? 'Guardando...' : 'Guardar Horario' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ EDIT TEAM MODAL ═══ -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="editTeam" class="fixed inset-0 z-[200] flex items-center justify-center p-4"
          @click.self="editTeam = null">
          <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
          <div
            class="relative z-10 w-full max-w-sm bg-[#0e0e0e] border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
              <h2 class="font-black uppercase text-base tracking-tight" style="font-family:'Chakra Petch',sans-serif">
                Editar Equipo</h2>
              <button @click="editTeam = null" class="text-gray-500 hover:text-white transition text-xl">×</button>
            </div>
            <form @submit.prevent="saveEdit" class="p-5 space-y-4">
              <div class="flex justify-center">
                <div
                  class="w-16 h-16 rounded-full overflow-hidden bg-white/5 border-2 border-white/10 flex items-center justify-center text-2xl font-black"
                  :style="{ color: gameNeon }">
                  <img v-if="editForm.logo" :src="editForm.logo" class="w-full h-full object-cover"
                    onerror="this.style.display='none'" />
                  <span>{{ editTeam.name[0] }}</span>
                </div>
              </div>
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">Nombre</label>
                <input v-model="editForm.name" type="text" required
                  class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-purple-500 transition" />
              </div>
              <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">URL de
                  logo</label>
                <input v-model="editForm.logo" type="text" placeholder="https://..."
                  class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-purple-500 transition" />
              </div>
              <div class="flex gap-2 pt-1">
                <button type="button" @click="editTeam = null"
                  class="flex-1 py-2 text-xs font-bold uppercase border border-white/10 text-gray-400 rounded-lg hover:border-white/30 transition">Cancelar</button>
                <button type="submit" :disabled="editForm.processing"
                  class="flex-1 py-2 text-xs font-bold uppercase rounded-lg text-black"
                  :style="{ background: gameNeon, opacity: editForm.processing ? 0.7 : 1 }">
                  {{ editForm.processing ? 'Guardando...' : 'Guardar' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity .2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* ── Winner Bracket connector lines ── */
.wb-match-right::before {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  width: 12px;
  height: 1px;
  background: rgba(192, 132, 252, 0.3);
  transform: translateX(100%) translateY(-50%);
}

.wb-match-top::after {
  content: '';
  position: absolute;
  right: -12px;
  top: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(192, 132, 252, 0.25);
}

.wb-match-bottom::after {
  content: '';
  position: absolute;
  right: -12px;
  bottom: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(192, 132, 252, 0.25);
}

/* ── Loser Bracket connector lines ── */
.lb-match-right::before {
  content: '';
  position: absolute;
  right: 0;
  top: 50%;
  width: 12px;
  height: 1px;
  background: rgba(96, 165, 250, 0.3);
  transform: translateX(100%) translateY(-50%);
}

.lb-match-top::after {
  content: '';
  position: absolute;
  right: -12px;
  top: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(96, 165, 250, 0.25);
}

.lb-match-bottom::after {
  content: '';
  position: absolute;
  right: -12px;
  bottom: 50%;
  width: 1px;
  height: calc(100% + 8px);
  background: rgba(96, 165, 250, 0.25);
}
</style>
