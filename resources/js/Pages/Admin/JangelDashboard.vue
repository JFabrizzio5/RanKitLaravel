<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import axios from 'axios'

// --- TIPOS Y INTERFACES ---

interface Match {
    id: number;
    game_mode: string; 
    custom_code: string;
    status: 'pending' | 'processed';
    created_at: string;
    game_session_id?: string;
}

interface Tournament {
    id: number;
    name: string;
    slug?: string;
    twitch_channel?: string;
    is_private?: boolean;
    is_serialized?: boolean;
    parent_tournament_id?: number | null;
    access_code?: string;
    banner_image?: string;
    // Nuevos campos
    rules?: string;
    prizes?: string;
    registration_status?: 'open' | 'closed' | 'disabled';
    ticket_price?: string;
    scoring_format?: {
        kill_points: number;
        placement: { from: number; to: number; points: number }[];
    };
    matches: Match[];
}

interface LeaderboardItem {
    player_name: string;
    member_names?: string[];
    games_played: number;
    total_kills: number;
    total_points: number;
    avg_points: number;
    avg_kills: number;
    avg_placement: number;
    best_placement: number;
    match_id?: number; // Para ajustes manuales
}

interface SlotInput {
    game_mode: number | null; 
    custom_code: string;
}

// --- PROPS & SETUP ---

const props = defineProps<{
    tournaments: Tournament[];
    laravelVersion?: string;
    phpVersion?: string;
}>();

const user = usePage().props.auth.user;

// --- SEGURIDAD JANGEL ---
const isJangel = computed(() => {
    return user.role === 'admin' || user.role === 'superadmin' || ['jangel@ejemplo.com', 'admin@jangel.pro', '18jangel18@gmail.com'].includes(user.email);
});

// --- THEME & UTILS ---
const isDark = ref(true);

function applyTheme(nextDark: boolean) {
    isDark.value = nextDark;
    const html = document.documentElement;
    if (nextDark) {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
}

function toggleTheme() {
    applyTheme(!isDark.value);
}

// --- LÓGICA DE DATOS ---
const selectedTournamentId = ref<number | null>(null);

const selectedTournament = computed((): Tournament | undefined => 
    props.tournaments?.find(t => t.id === selectedTournamentId.value) || props.tournaments?.[0]
);

const leaderboard = ref<LeaderboardItem[]>([]);
const selectedMatchId = ref<number | null>(null);

// --- DROPDOWN BUSCADOR DE TORNEO ---
const tournamentSearch = ref('');
const showTournamentDropdown = ref(false);
const tournamentDropdownRef = ref<HTMLElement | null>(null);
const tournamentTriggerRef = ref<HTMLElement | null>(null);
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

const filteredTournaments = computed(() => {
    const all = Array.isArray(props.tournaments) ? props.tournaments : [];
    if (!tournamentSearch.value.trim()) return all;
    const q = tournamentSearch.value.trim().toLowerCase();
    return all.filter(t => t.name.toLowerCase().includes(q));
});

const openTournamentDropdown = () => {
    if (tournamentTriggerRef.value) {
        const rect = tournamentTriggerRef.value.getBoundingClientRect();
        dropdownPosition.value = {
            top: rect.bottom + window.scrollY + 4,
            left: rect.left + window.scrollX,
            width: rect.width,
        };
    }
    showTournamentDropdown.value = !showTournamentDropdown.value;
};

const selectTournamentFromDropdown = (id: number) => {
    selectedTournamentId.value = id;
    showTournamentDropdown.value = false;
    tournamentSearch.value = '';
};
const handleMouseOutsideTournament = (e: MouseEvent) => {
    const target = e.target as Node;
    const insideTrigger = tournamentTriggerRef.value?.contains(target);
    const insideDropdown = tournamentDropdownRef.value?.contains(target);
    if (!insideTrigger && !insideDropdown) {
        showTournamentDropdown.value = false;
    }
};
const slotInputs = ref<Record<number, SlotInput>>({});
const processingSlot = ref<Record<number, boolean>>({});

// Filtros
const leaderboardType = ref<'players' | 'teams'>('players');
const filterMode = ref<string>('all'); 
const sortBy = ref<'points' | 'kills'>('points');
const searchQuery = ref('');
const expandedRowIndex = ref<number | null>(null);
const loadingLeaderboard = ref(false);

const filteredLeaderboard = computed(() => {
    if (!searchQuery.value) return leaderboard.value;
    const q = searchQuery.value.toLowerCase();
    return leaderboard.value.filter(item => {
        if (leaderboardType.value === 'teams' && item.member_names) {
            return item.member_names.some(m => m.toLowerCase().includes(q));
        }
        return item.player_name.toLowerCase().includes(q);
    });
});

// --- FORMULARIOS ---

const formReplay = useForm({ 
    replay: null as File | null, 
    mode: null as number | null, 
    target_match_id: null as number | null 
});

// Apelación Automática (Solo archivo)
const formAppeal = useForm({
    replay: null as File | null,
});

// Ajuste Manual de Puntos (Logs)
const formManualAdjust = useForm({
    match_id: null as number | null,
    player_name: '',
    points_change: 0,
    reason: ''
});

// Configuración Avanzada (Tabs)
const settingsTab = ref<'general' | 'scoring' | 'rules' | 'prizes'>('general');
const formSettings = useForm({
    id: null as number | null,
    name: '',
    twitch_channel: '',
    is_private: false,
    access_code: '',
    rules: '',
    prizes: '',
    is_serialized: false,
    parent_tournament_id: null as number | null,
    registration_status: 'disabled' as 'open' | 'closed' | 'disabled',
    ticket_price: '',
    scoring_format: {
        kill_points: 1,
        placement: [] as { from: number; to: number; points: number }[]
    }
});

const formEditMatch = useForm({
    match_id: null as number | null,
    custom_code: ''
});

// Edición directa de resultado de jugador
const formEditResult = useForm({
    player_name: '',
    kills: 0,
    placement: 1,
    points_override: '' as string | number,
});
const editResultMatchId = ref<number | null>(null);

const formCreateTournament = useForm({
    name: '',
    twitch_channel: '',
    is_private: false,
    access_code: '',
    is_serialized: false,
    parent_tournament_id: null as number | null
});

const formBanner = useForm({
    banner: null as File | null,
});

// --- CANCHAS (PITCHES) STATE ---
interface Pitch {
    id: number;
    name: string;
    type: string;
    price_per_hour: number;
    description: string;
}

interface PitchReservation {
    id: number;
    pitch_id: number;
    customer_name: string;
    customer_phone: string;
    start_time: string;
    end_time: string;
    status: string;
}

const pitches = ref<Pitch[]>([]);
const pitchReservations = ref<PitchReservation[]>([]);
const selectedPitch = ref<Pitch | null>(null);

const formCreatePitch = useForm({
    name: '',
    type: 'Futbol 7',
    price_per_hour: 0,
    description: ''
});

const formCreateReservation = useForm({
    customer_name: '',
    customer_phone: '',
    start_time: '',
    end_time: ''
});

// Estados UI
const activeTab = ref<'codes' | 'widget' | 'matches' | 'registrations' | 'qualifiers'>('codes');
const showMatchModal = ref(false);
const showAppealModal = ref(false); 
const showSettingsModal = ref(false);
const showEditMatchModal = ref(false);
const showCreateModal = ref(false); 
const showManualAdjustModal = ref(false);
const showEditResultModal = ref(false);
const showRegisteredUsersModal = ref(false);
const showPendingRequestsModal = ref(false);
const uploadProgress = ref(0);

// --- INICIALIZACIÓN ---
const initSlotInputs = () => {
    if (props.tournaments) {
        props.tournaments.forEach((t) => {
            if (!slotInputs.value[t.id]) {
                slotInputs.value[t.id] = { game_mode: null, custom_code: '' };
            }
        });
        if (!selectedTournamentId.value && props.tournaments.length > 0) {
            selectedTournamentId.value = props.tournaments[0].id;
        }
    }
};

onMounted(() => {
    initSlotInputs();
    const savedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ?? true;
    if (savedTheme === 'light') applyTheme(false);
    else if (savedTheme === 'dark') applyTheme(true);
    else applyTheme(systemPrefersDark);

    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/@phosphor-icons/web';
        script.async = true;
        document.head.appendChild(script);
    }

    if (selectedTournament.value) {
        fetchLeaderboard(selectedTournament.value);
    }
    fetchPitches(); 

    // Listener para cerrar el dropdown de torneo al hacer click fuera (mousedown evita conflicto con el toggle)
    document.addEventListener('mousedown', handleMouseOutsideTournament);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleMouseOutsideTournament);
});

// --- FUNCIONES DE CANCHAS ---
const fetchPitches = async () => {
    try {
        const res = await axios.get(route('jangel.pitches'));
        pitches.value = res.data;
    } catch (e) {
        console.error("Error cargando canchas", e);
    }
};

const createPitch = () => {
    formCreatePitch.post(route('jangel.pitches.store'), {
        onSuccess: () => {
            fetchPitches();
            formCreatePitch.reset();
            alert('Cancha creada con éxito');
        }
    });
};

const deletePitch = (id: number) => {
    if (confirm('¿Seguro que deseas eliminar esta cancha?')) {
        router.delete(route('jangel.pitches.delete', id), {
            onSuccess: () => {
                if (selectedPitch.value?.id === id) selectedPitch.value = null;
                fetchPitches();
            }
        });
    }
};

const selectPitch = async (pitch: Pitch) => {
    selectedPitch.value = pitch;
    await fetchPitchReservations(pitch.id);
};

const fetchPitchReservations = async (pitchId: number) => {
    try {
        const res = await axios.get(route('jangel.pitches.reservations', pitchId));
        pitchReservations.value = res.data;
    } catch (e) {
        console.error("Error cargando reservas", e);
    }
};

const createReservation = () => {
    if (!selectedPitch.value) return;
    formCreateReservation.post(route('jangel.pitches.reservations.store', selectedPitch.value.id), {
        onSuccess: () => {
            fetchPitchReservations(selectedPitch.value!.id);
            formCreateReservation.reset();
            alert('Reserva creada exitosamente');
        }
    });
};

const updateReservationStatus = (id: number, status: string) => {
    router.put(route('jangel.reservations.status', id), { status }, {
        onSuccess: () => {
            if (selectedPitch.value) fetchPitchReservations(selectedPitch.value.id);
        }
    });
};

watch(() => props.tournaments, initSlotInputs, { deep: true });
watch(selectedTournamentId, (newId) => {
    selectedMatchId.value = null;
    searchQuery.value = ''; 
    filterMode.value = 'all'; 
    if (selectedTournament.value) {
        fetchLeaderboard(selectedTournament.value);
    }
});

// --- ACCIONES DE DATOS ---

// CORRECCIÓN: Permitir que matchId sea null explícitamente para resetear el filtro
const fetchLeaderboard = async (tn: Tournament, matchId: number | null = null) => {
    if(!tn) return;
    
    // Si se llama con null, reseteamos a vista global. Si se llama con ID, filtramos.
    selectedMatchId.value = matchId; 
    
    loadingLeaderboard.value = true;
    leaderboard.value = []; 
    expandedRowIndex.value = null;

    try {
        const res = await axios.get(route('jangel.api.leaderboard', { 
            tournamentId: tn.id, 
            match_id: selectedMatchId.value, // Esto enviará null o el ID
            type: leaderboardType.value,
            mode: filterMode.value, 
            sort: sortBy.value 
        }));
        leaderboard.value = res.data;
    } catch (e) {
        console.error(e);
    } finally {
        loadingLeaderboard.value = false;
    }
};

// --- INSCRIPCIONES ---
const registrations = ref<any[]>([]);
const selectedPlayersForClassification = ref<string[]>([]);
const targetTournamentId = ref<number | null>(null);

const fetchRegistrations = async () => {
    if (!selectedTournament.value) return;
    try {
        const res = await axios.get(route('jangel.registrations', selectedTournament.value.id));
        registrations.value = res.data;
    } catch (e) {
        console.error(e);
    }
};

const qualifiers = ref<any[]>([]);
const formManualQualify = useForm({
    player_name: '',
    player_email: '',
    epic_id: ''
});

const fetchQualifiers = async () => {
    if (!selectedTournament.value) return;
    try {
        const res = await axios.get(route('jangel.tournaments.qualifiers', selectedTournament.value.id));
        qualifiers.value = res.data;
    } catch (e) {
        console.error(e);
    }
};

const qualifyManual = () => {
    if (!selectedTournament.value) return;
    formManualQualify.post(route('jangel.tournaments.qualify-manual', selectedTournament.value.id), {
        onSuccess: () => {
            fetchQualifiers();
            formManualQualify.reset();
        }
    });
};

const removeQualifier = (qualifierId: number) => {
    if (!selectedTournament.value) return;
    if (confirm('¿Remover jugador clasificado?')) {
        router.delete(route('jangel.tournaments.unqualify-manual', {
            id: selectedTournament.value.id,
            qualifierId: qualifierId
        }), {
            onSuccess: () => fetchQualifiers()
        });
    }
};

watch(activeTab, (newTab) => {
    if (newTab === 'registrations') {
        fetchRegistrations();
    } else if (newTab === 'qualifiers') {
        fetchQualifiers();
    }
});
watch(selectedTournamentId, () => {
    if (activeTab.value === 'registrations') fetchRegistrations();
    if (activeTab.value === 'qualifiers') fetchQualifiers();
});

// Toast reactivo para feedback de inscripciones
const registrationToast = ref<{ type: 'success' | 'error'; message: string } | null>(null);
const showRegistrationToast = (type: 'success' | 'error', message: string) => {
    registrationToast.value = { type, message };
    setTimeout(() => { registrationToast.value = null; }, 3000);
};

const updatePayment = async (id: number, status: string) => {
    try {
        await axios.put(route('jangel.registrations.payment', id), { status });
        await fetchRegistrations();
    } catch (e) {
        alert("Error al actualizar pago.");
    }
};

// Aprobar desde modal de Pendientes
const approveRegistration = async (id: number) => {
    try {
        await axios.put(route('jangel.registrations.payment', id), { status: 'paid' });
        await fetchRegistrations();
        showRegistrationToast('success', '✅ Inscripción aprobada y movida a Inscritos.');
    } catch (e) {
        showRegistrationToast('error', 'Error al aprobar inscripción.');
    }
};

// Rechazar desde modal de Pendientes
const rejectRegistration = async (id: number) => {
    try {
        await axios.put(route('jangel.registrations.payment', id), { status: 'rejected' });
        await fetchRegistrations();
        showRegistrationToast('success', '🚫 Inscripción rechazada y removida de Pendientes.');
    } catch (e) {
        showRegistrationToast('error', 'Error al rechazar inscripción.');
    }
};

// Rechazar y mover desde modal de Inscritos (paid -> rejected)
const rejectAndMove = async (id: number) => {
    try {
        await axios.put(route('jangel.registrations.payment', id), { status: 'rejected' });
        await fetchRegistrations();
        showRegistrationToast('success', '🚫 Inscripción rechazada y movida fuera de Inscritos.');
    } catch (e) {
        showRegistrationToast('error', 'Error al rechazar inscripción.');
    }
};

const acceptAllRegistrations = async () => {
    if (!selectedTournament.value) return;
    if (!confirm('\u00bfAceptar TODAS las inscripciones pendientes?')) return;
    try {
        const res = await axios.post(`/admin/tournaments/${selectedTournament.value.id}/accept-all`);
        alert(`\u2705 ${res.data.accepted} inscripci\u00f3n(es) aceptadas.`);
        fetchRegistrations();
    } catch (e) {
        alert('Error al aceptar inscripciones.');
    }
};

const initializeLeague = async () => {
    if (!confirm('\u00bfDeseas autogenerar los 4 torneos oficiales de la Rankit Pro League? Solo se crear\u00e1n los que no existan.')) return;
    try {
        const res = await axios.post('/admin/tournaments/initialize-league');
        alert(`\u2705 Torneos de liga inicializados correctamente. Creados: ${res.data.created}`);
        window.location.reload();
    } catch (e) {
        alert('Error al inicializar los torneos de la liga.');
    }
};

const classifyPlayers = () => {
    if (selectedPlayersForClassification.value.length === 0) {
        alert("Selecciona al menos un jugador.");
        return;
    }
    if (!selectedTournament.value) return;
    
    router.post(route('jangel.tournaments.classify', selectedTournament.value.id), {
        players: selectedPlayersForClassification.value,
        target_tournament_id: targetTournamentId.value
    }, {
        onSuccess: () => {
            alert("Jugadores clasificados correctamente.");
            selectedPlayersForClassification.value = [];
        }
    });
};

// --- GESTIÓN DE TORNEO (CONFIGURACIÓN AVANZADA) ---

const createTournament = () => {
    formCreateTournament.post(route('jangel.tournament.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            formCreateTournament.reset();
        }
    });
};

const openSettingsModal = () => {
    if (!selectedTournament.value) return;
    const t = selectedTournament.value;
    
    formSettings.id = t.id;
    formSettings.name = t.name;
    formSettings.twitch_channel = t.twitch_channel || '';
    formSettings.is_private = Boolean(t.is_private);
    formSettings.access_code = t.access_code || '';
    formSettings.rules = t.rules || '';
    formSettings.prizes = t.prizes || '';
    formSettings.is_serialized = !!t.is_serialized;
    formSettings.parent_tournament_id = t.parent_tournament_id || null;
    formSettings.registration_status = (t.registration_status as any) || 'disabled';
    formSettings.ticket_price = t.ticket_price || '';
    
    // Deep copy del JSON de puntuación o inicializar default
    if (t.scoring_format) {
        // Asegurarse de que scoring_format es un objeto, si viene del prop como objeto Laravel
        // Si viene como string JSON parsearlo
        let format = t.scoring_format;
        if (typeof format === 'string') format = JSON.parse(format);
        
        formSettings.scoring_format = JSON.parse(JSON.stringify(format));
        if (!formSettings.scoring_format.placement) formSettings.scoring_format.placement = [];
    } else {
        formSettings.scoring_format = { kill_points: 1, placement: [] };
    }
    
    showSettingsModal.value = true;
};

// Helpers para array de rangos
const addPlacementRule = () => {
    formSettings.scoring_format.placement.push({ from: 1, to: 1, points: 0 });
};
const removePlacementRule = (idx: number) => {
    formSettings.scoring_format.placement.splice(idx, 1);
};

const updateTournament = () => {
    if (!formSettings.id) return;
    formSettings.put(route('jangel.tournament.update', formSettings.id), {
        onSuccess: () => {
            showSettingsModal.value = false;
        },
        preserveScroll: true
    });
};

const deleteTournament = () => {
    if (!formSettings.id) return;
    if (!confirm('¿Estás seguro de ELIMINAR este torneo? Esta acción no se puede deshacer.')) return;
    router.delete(route('jangel.tournament.delete', formSettings.id), {
        onSuccess: () => {
            showSettingsModal.value = false;
            selectedTournamentId.value = null; 
        }
    });
};

// --- GESTIÓN DE PARTIDAS ---

const createSlot = () => {
    const tn = selectedTournament.value;
    if (!tn) return;
    const input = slotInputs.value[tn.id];

    if (!input || !input.custom_code || !input.game_mode) {
        alert("¡Debes seleccionar un MODO y escribir un CÓDIGO!");
        return;
    }
    processingSlot.value[tn.id] = true;
    router.post(route('jangel.match.schedule', tn.id), input as any, {
        onSuccess: () => {
            if(slotInputs.value[tn.id]) slotInputs.value[tn.id].custom_code = ''; 
            processingSlot.value[tn.id] = false;
        },
        onError: (err) => {
            processingSlot.value[tn.id] = false;
            alert("Error: " + JSON.stringify(err));
        },
        preserveScroll: true
    });
};

const openEditMatchModal = (match: Match) => {
    formEditMatch.match_id = match.id;
    formEditMatch.custom_code = match.custom_code;
    showEditMatchModal.value = true;
};

const updateMatchCode = () => {
    if (!formEditMatch.match_id) return;
    formEditMatch.put(route('jangel.match.update', formEditMatch.match_id), {
        onSuccess: () => {
            showEditMatchModal.value = false;
        }
    });
};

const deleteMatch = (id: number) => {
    if(confirm("¿Seguro que quieres borrar esta partida y sus datos?")) {
        router.delete(route('jangel.match.delete', id), {
            onSuccess: () => {
                if (selectedMatchId.value === id && selectedTournament.value) {
                    fetchLeaderboard(selectedTournament.value, null);
                }
            },
            preserveScroll: true
        });
    }
};

// --- AJUSTE MANUAL DE PUNTOS ---
const availablePlayers = ref<string[]>([]); // Para el modal de ajuste

const openManualAdjust = (item: LeaderboardItem) => {
    if (!selectedMatchId.value) {
        alert("Por favor selecciona una partida específica del historial para ajustar puntos.");
        return;
    }
    
    formManualAdjust.reset();
    formManualAdjust.match_id = selectedMatchId.value;

    // Detectar si es equipo o jugador individual
    if (item.member_names && item.member_names.length > 0) {
        // Es un equipo: llenamos la lista de jugadores disponibles
        availablePlayers.value = item.member_names;
        // Pre-seleccionamos el primero para evitar errores
        formManualAdjust.player_name = item.member_names[0];
    } else {
        // Es individual (o fallback)
        availablePlayers.value = [item.player_name];
        formManualAdjust.player_name = item.player_name;
    }

    showManualAdjustModal.value = true;
};

const submitManualAdjust = () => {
    if (!selectedTournament.value) return;
    formManualAdjust.post(route('jangel.score.adjust', selectedTournament.value.id), {
        onSuccess: () => {
            showManualAdjustModal.value = false;
            alert("✅ Ajuste realizado y log guardado.");
            fetchLeaderboard(selectedTournament.value!, selectedMatchId.value);
        },
        onError: (err) => alert("Error: " + JSON.stringify(err))
    });
};


// --- EDICIÓN DIRECTA DE RESULTADO ---
const openEditResult = (item: LeaderboardItem) => {
    formEditResult.reset();
    formEditResult.player_name = item.player_name;
    formEditResult.kills = item.total_kills;
    formEditResult.placement = item.best_placement;
    formEditResult.points_override = formatDec(item.total_points);

    // Usar la partida ya seleccionada, si la hay
    editResultMatchId.value = selectedMatchId.value;

    showEditResultModal.value = true;
};

const submitEditResult = () => {
    const matchId = editResultMatchId.value;
    if (!matchId) { alert("Selecciona una partida específica del historial (panel MATCHES)."); return; }
    if (!selectedTournament.value) return;

    formEditResult.put(route('jangel.player.result.update', matchId), {
        onSuccess: () => {
            showEditResultModal.value = false;
            alert("✅ Resultado actualizado correctamente.");
            fetchLeaderboard(selectedTournament.value!, selectedMatchId.value);
        },
        onError: (err) => alert("Error: " + JSON.stringify(err))
    });
};
const openAppealModal = (matchId: number) => {
    formAppeal.reset();
    showAppealModal.value = true;
    uploadProgress.value = 0;
}

const handleAppealFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        formAppeal.replay = target.files[0];
    }
}

const submitAppeal = () => {
    if (!formAppeal.replay || !selectedTournament.value) return;
    // Usamos ID del torneo en ruta
    formAppeal.post(route('tournament.appeal', selectedTournament.value.id), {
        onProgress: (progress) => { uploadProgress.value = progress?.percentage || 0; },
        onSuccess: () => {
            showAppealModal.value = false;
            uploadProgress.value = 0;
            alert("✅ Apelación procesada automáticamente con las reglas del torneo.");
            fetchLeaderboard(selectedTournament.value!, selectedMatchId.value);
        },
        onError: (err) => {
            showAppealModal.value = false;
            alert("Error al apelar: " + JSON.stringify(err));
        }
    });
}

// --- SUBIDA REPLAY (GENERAL) ---
const openUploadModal = (matchId: number | null = null) => {
    formReplay.reset(); 
    formReplay.mode = null; 
    formReplay.target_match_id = matchId;
    showMatchModal.value = true;
    uploadProgress.value = 0;
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        formReplay.replay = target.files[0];
    }
}

const submitReplay = () => {
    if (!selectedTournament.value || !formReplay.replay) return;
    if (!formReplay.mode) { alert("¡Debes seleccionar un Modo de Juego!"); return; }

    formReplay.post(route('jangel.match.process', selectedTournament.value.id), {
        onProgress: (progress) => { uploadProgress.value = progress?.percentage || 0; },
        onSuccess: () => {
            showMatchModal.value = false;
            uploadProgress.value = 0;
            if (selectedMatchId.value === formReplay.target_match_id) {
                fetchLeaderboard(selectedTournament.value!, selectedMatchId.value);
            }
        },
        onError: (err) => {
            showMatchModal.value = false;
            alert("Error al subir: " + JSON.stringify(err));
        }
    });
};

// --- HELPERS ---
const formatDec = (num: number | string) => {
    const n = typeof num === 'string' ? parseFloat(num) : num;
    return isNaN(n) ? '0' : n.toFixed(1).replace(/\.0$/, '');
};

const handleBannerFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        formBanner.banner = target.files[0];
    }
};

const uploadBannerImage = () => {
    if (!formBanner.banner || !selectedTournament.value) return;
    formBanner.post(route('jangel.tournament.banner', selectedTournament.value.id), {
        onSuccess: () => {
            formBanner.reset();
            alert('✅ Imagen del torneo actualizada.');
        },
        onError: (err) => alert('Error al subir la imagen: ' + JSON.stringify(err))
    });
};

const copyGlobalObsLink = () => {
    if (!selectedTournament.value) return;
    const baseUrl = `${window.location.origin}/widget/obs/global/${selectedTournament.value.id}`;
    let query = `?type=${leaderboardType.value}&mode=${filterMode.value}&sort=${sortBy.value}&limit=10`;
    if (selectedMatchId.value) query += `&match_id=${selectedMatchId.value}`;
    if (searchQuery.value) query += `&search=${encodeURIComponent(searchQuery.value)}`;
    navigator.clipboard.writeText(baseUrl + query);
    alert(`✅ Link OBS Copiado!`);
};

const copyTrackingLink = (targetName: string) => {
    if (!selectedTournament.value) return;
    const baseUrl = `${window.location.origin}/widget/obs/global/${selectedTournament.value.id}`;
    const query = `?type=${leaderboardType.value}&mode=all&sort=${sortBy.value}&limit=1&search=${encodeURIComponent(targetName)}`;
    navigator.clipboard.writeText(baseUrl + query);
    alert(`✅ Tracking OBS copiado para: ${targetName}`);
};

const copyInviteLink = () => {
    if (!selectedTournament.value) return;
    const url = route('public.tournament.show', {
        slug: selectedTournament.value.slug || selectedTournament.value.id,
        code: selectedTournament.value.access_code
    });
    navigator.clipboard.writeText(url).then(() => {
        alert('✅ Link de invitación copiado al portapapeles:\n' + url);
    });
};

const isSendingCodes = ref(false);
const sendAccessCodes = async () => {
    if (!selectedTournament.value || isSendingCodes.value) return;
    
    if (!confirm('¿Estás seguro de que quieres enviar el código de acceso por correo a TODOS los jugadores inscritos (aceptados) y clasificados?')) return;
    
    isSendingCodes.value = true;
    alert('Enviando correos, por favor espera...');
    
    try {
        const res = await axios.post(`/admin/tournaments/${selectedTournament.value.id}/send-access-codes`, {
            _token: (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
        });
        
        if (res.data.errors && res.data.errors.length > 0) {
            console.error(res.data.errors);
            alert(res.data.message + "\n\nRevisa la consola para ver los errores detallados.");
        } else {
            alert(res.data.success || res.data.message || 'Correos enviados exitosamente');
        }
    } catch (err: any) {
        alert(err.response?.data?.message || 'Error al enviar correos');
    } finally {
        isSendingCodes.value = false;
    }
};

const sendingMatchCodeId = ref<number | null>(null);
const sendMatchCode = async (matchId: number) => {
    if (!selectedTournament.value || sendingMatchCodeId.value) return;
    
    if (!confirm('¿Estás seguro de que quieres enviar ESTE código de partida por correo a TODOS los jugadores inscritos (aceptados) y clasificados?')) return;
    
    sendingMatchCodeId.value = matchId;
    alert('Enviando código de partida, por favor espera...');
    
    try {
        const res = await axios.post(`/admin/tournaments/${selectedTournament.value.id}/match/${matchId}/send-code`, {
            _token: (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content
        });
        
        if (res.data.errors && res.data.errors.length > 0) {
            console.error("Errores de envío:", res.data.errors);
            alert(res.data.message + "\n\nHubo fallos al enviar algunos correos. Revisa la consola (F12) para ver a quién no se le pudo enviar.");
        } else {
            alert(res.data.success || res.data.message || 'Códigos de partida enviados exitosamente');
        }
    } catch (err: any) {
        alert(err.response?.data?.message || err.response?.data?.error || 'Error al enviar código de partida');
    } finally {
        sendingMatchCodeId.value = null;
    }
};

</script>

<template>
  <Head title="Admin Dashboard - Rankit">
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Archivo:wght@300;400;600;800&display=swap" rel="stylesheet" />
  </Head>

  <div class="min-h-screen pb-12 font-sans transition-colors duration-300 overflow-x-hidden selection:bg-[var(--rankit-neon)] selection:text-white bg-gray-50 text-gray-900 dark:bg-[#050505] dark:text-white">
    
    <!-- Top Bar Sticky -->
    <div class="fixed top-0 left-0 w-full h-14 flex items-center justify-between px-6 lg:px-12 z-[60] border-b transition-colors bg-white border-gray-200 dark:bg-[#0a0a0a] dark:border-white/10">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
          <span class="relative flex w-2 h-2">
            <span class="absolute inline-flex w-full h-full bg-red-400 rounded-full opacity-75 animate-ping"></span>
            <span class="relative inline-flex w-2 h-2 bg-red-500 rounded-full"></span>
          </span>
          <span class="text-xs font-bold tracking-wider uppercase text-gray-600 dark:text-gray-300 truncate max-w-[150px]">
             {{ selectedTournament?.name || 'Selecciona Torneo' }}
          </span>
          <button @click="openSettingsModal" class="p-1 hover:text-[var(--rankit-neon)] transition" title="Configurar Torneo">
             <i class="ph-bold ph-gear"></i>
          </button>
        </div>
        <div class="w-px h-4 bg-gray-300 dark:bg-gray-700"></div>
        <span class="text-sm font-bold text-[var(--rankit-neon)] hidden sm:inline">Admin Mode</span>
      </div>

      <a :href="selectedTournament ? `/t/${selectedTournament.id}${selectedTournament.access_code ? '?code=' + selectedTournament.access_code : ''}` : '#'" 
         target="_blank" 
         class="px-4 py-1 text-[10px] font-bold tracking-wider uppercase btn-skew flex items-center gap-2 group decoration-0">
        <span class="flex items-center gap-2 btn-content">
            Ver Público <i class="transition-transform ph-bold ph-arrow-square-out group-hover:scale-110"></i>
        </span>
      </a>
    </div>

    <!-- Navbar -->
    <nav class="fixed z-50 flex items-center justify-between w-full h-20 px-6 transition-all duration-300 border-b lg:px-12 backdrop-blur-md top-14 bg-white/90 border-gray-200 dark:bg-[#050505]/95 dark:border-white/10">
      <Link href="/" class="flex items-center gap-3 cursor-pointer group">
        <svg class="w-8 h-8 text-black dark:text-white group-hover:text-[var(--rankit-neon)] transition-colors" viewBox="0 0 100 100" fill="none">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="currentColor" />
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="currentColor" />
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--rankit-neon)" />
        </svg>
        <span class="text-2xl italic font-bold tracking-tighter text-black uppercase font-display dark:text-white">Rankit</span>
      </Link>

      <div class="flex items-center gap-4">
        <Link :href="route('admin.users.index')" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-yellow-500 uppercase transition-all border rounded-lg border-yellow-500/20 hover:bg-yellow-500 hover:text-black hover:border-yellow-500">
            <i class="text-lg ph-bold ph-users"></i>
            <span class="hidden sm:inline">Usuarios</span>
        </Link>

        <button @click="toggleTheme" class="p-2 text-gray-500 transition-colors border border-transparent rounded-lg hover:text-neon dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700">
          <i v-if="isDark" class="text-xl ph-fill ph-sun"></i>
          <i v-else class="text-xl ph-fill ph-moon"></i>
        </button>

        <Link :href="route('game.selector')" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-purple-500 uppercase transition-all border rounded-lg border-purple-500/20 hover:bg-purple-500 hover:text-white hover:border-purple-500">
            <i class="text-lg ph-bold ph-game-controller"></i>
            <span class="hidden sm:inline">Juegos</span>
        </Link>

        <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-2 px-4 py-2 text-xs font-bold text-red-500 uppercase transition-all border rounded-lg border-red-500/20 hover:bg-red-500 hover:text-white hover:border-red-500 group">
            <i class="text-lg ph-bold ph-sign-out"></i>
            <span class="hidden sm:inline">Salir</span>
        </Link>
      </div>
    </nav>

    <main class="grid grid-cols-1 gap-8 px-6 py-8 mx-auto max-w-7xl lg:px-8 lg:grid-cols-12 pt-44">
      <!-- LEFT COLUMN -->
      <aside class="space-y-6 lg:col-span-4">
        
        <!-- Tournament Selector & CREATE BUTTON -->
        <div class="p-4 brutal-card bg-white dark:bg-[#0a0a0a]">
             <label class="text-[10px] font-bold uppercase text-gray-500 mb-2 block">Seleccionar Torneo</label>
             <div class="flex gap-2">
                 <!-- Custom Searchable Dropdown -->
                 <div class="relative flex-1">
                     <!-- Trigger Button -->
                     <button
                         ref="tournamentTriggerRef"
                         @mousedown.stop="openTournamentDropdown"
                         class="w-full flex items-center justify-between gap-2 text-xs font-bold text-black dark:text-white bg-gray-100 dark:bg-white/5 border rounded p-2 outline-none hover:border-[var(--rankit-neon)] transition"
                         :class="showTournamentDropdown ? 'border-[var(--rankit-neon)]' : 'border-gray-200 dark:border-white/10'"
                     >
                         <span class="truncate">
                             {{ selectedTournament?.name || 'Selecciona un torneo...' }}
                             {{ selectedTournament?.is_private ? '🔒' : '' }}
                         </span>
                         <i :class="showTournamentDropdown ? 'ph-bold ph-caret-up' : 'ph-bold ph-caret-down'" class="shrink-0 text-[var(--rankit-neon)]"></i>
                     </button>

                     <!-- Dropdown via Teleport (renderizado en body para evitar stacking context) -->
                     <Teleport to="body">
                         <div
                             v-if="showTournamentDropdown"
                             ref="tournamentDropdownRef"
                             :style="{
                                 position: 'fixed',
                                 top: dropdownPosition.top + 'px',
                                 left: dropdownPosition.left + 'px',
                                 width: dropdownPosition.width + 'px',
                                 zIndex: 9999
                             }"
                             class="bg-white dark:bg-[#111] border border-gray-200 dark:border-white/10 rounded shadow-2xl overflow-hidden"
                         >
                             <!-- Search Input -->
                             <div class="p-2 border-b border-gray-100 dark:border-white/10">
                                 <div class="relative">
                                     <i class="absolute left-2 top-1/2 -translate-y-1/2 ph ph-magnifying-glass text-gray-400 text-xs"></i>
                                     <input
                                         v-model="tournamentSearch"
                                         type="text"
                                         placeholder="Buscar torneo..."
                                         class="w-full pl-6 pr-2 py-1.5 text-xs bg-gray-100 dark:bg-white/5 border border-transparent focus:border-[var(--rankit-neon)] rounded outline-none text-black dark:text-white"
                                         @mousedown.stop
                                     />
                                 </div>
                                 <div class="text-[9px] text-gray-400 mt-1 pl-1">
                                     {{ filteredTournaments.length }} torneo(s)
                                 </div>
                             </div>

                             <!-- Options List -->
                             <div class="max-h-[220px] overflow-y-auto custom-scrollbar">
                                 <div
                                     v-for="t in filteredTournaments"
                                     :key="t.id"
                                     @mousedown.stop="selectTournamentFromDropdown(t.id)"
                                     class="flex items-center justify-between px-3 py-2 text-xs font-bold cursor-pointer transition select-none"
                                     :class="selectedTournamentId === t.id
                                         ? 'bg-[var(--rankit-neon)] text-white'
                                         : 'text-black dark:text-white hover:bg-gray-100 dark:hover:bg-white/5'"
                                 >
                                     <span class="truncate">{{ t.name }}</span>
                                     <span class="ml-2 shrink-0 flex items-center gap-1">
                                         <span v-if="t.is_private" class="text-[10px]">🔒</span>
                                         <i v-if="selectedTournamentId === t.id" class="ph-bold ph-check"></i>
                                     </span>
                                 </div>
                                 <div v-if="filteredTournaments.length === 0" class="px-3 py-6 text-center text-[10px] text-gray-400 italic">
                                     <i class="ph ph-magnifying-glass block text-lg mb-1"></i>
                                     Sin resultados para "{{ tournamentSearch }}"
                                 </div>
                             </div>
                         </div>
                     </Teleport>
                 </div>

                 <button @click="showCreateModal = true" class="px-3 text-white transition bg-black rounded dark:bg-white dark:text-black hover:opacity-80" title="Crear Nuevo Torneo">
                     <i class="ph-bold ph-plus"></i>
                 </button>
             </div>
        </div>

        <!-- MANAGER PANEL -->
        <div class="overflow-hidden text-left brutal-card bg-white dark:bg-[#0a0a0a]">
          <div class="p-4 border-b border-gray-200 dark:border-white/10">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--rankit-neon)]">Panel de Control</div>
                <div class="flex items-center gap-2">
                    <div class="text-xl italic font-bold uppercase font-display text-black dark:text-white truncate max-w-[200px]">
                        {{ selectedTournament?.name }}
                    </div>
                    <button @click="openSettingsModal" class="text-gray-400 hover:text-[var(--rankit-neon)] transition">
                        <i class="ph-bold ph-pencil-simple"></i>
                    </button>
                </div>
                
                <div v-if="selectedTournament?.is_private" class="mt-2">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="text-[9px] bg-red-100 dark:bg-red-900/30 text-red-500 px-2 py-0.5 rounded border border-red-500/20 whitespace-nowrap">
                            🔒 CÓDIGO: {{ selectedTournament.access_code }}
                        </span>
                        <button @click="copyInviteLink" class="text-[9px] font-bold text-[var(--rankit-neon)] hover:underline uppercase">
                            Copiar Link
                        </button>
                        <button @click="sendAccessCodes" :disabled="isSendingCodes" class="text-[9px] font-bold text-white bg-[var(--rankit-neon)] px-2 py-0.5 rounded hover:opacity-80 transition flex items-center gap-1 uppercase disabled:opacity-50">
                            <i class="ph-bold" :class="isSendingCodes ? 'ph-spinner animate-spin' : 'ph-envelope-simple'"></i> 
                            {{ isSendingCodes ? 'Enviando...' : 'Enviar por Correo' }}
                        </button>
                    </div>
                </div>
              </div>
              <div class="flex items-center gap-1 text-[10px] font-bold text-gray-500 bg-gray-100 dark:bg-white/10 px-2 py-1 rounded self-start">
                   <i class="ph-fill ph-eye text-[var(--rankit-neon)]"></i> 
                   <span>LIVE</span>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="flex border-b border-gray-200 dark:border-white/10 overflow-x-auto custom-scrollbar">
            <button v-for="tab in ['codes', 'widget', 'matches', 'registrations', 'qualifiers']" :key="tab" @click="activeTab = tab as any"
              class="flex-1 min-w-[80px] py-3 text-[10px] font-bold uppercase tracking-wider text-center transition"
              :class="activeTab === tab ? 'bg-[var(--rankit-neon)] text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5'">
              {{ tab === 'registrations' ? 'Inscrip.' : (tab === 'qualifiers' ? 'Clasificados' : tab) }}
            </button>
          </div>

          <!-- TAB: CODES -->
          <div v-if="activeTab === 'codes' && selectedTournament && slotInputs[selectedTournament.id]" class="p-4 space-y-3 animate-fade-in">
            <div class="space-y-4">
              <div>
                 <label class="text-[9px] font-bold uppercase text-gray-500 block mb-1">Modo de Juego</label>
                 <div class="flex gap-2">
                    <button v-for="m in [1,2,3,4]" :key="m" 
                        @click="slotInputs[selectedTournament.id].game_mode = m"
                        class="flex-1 py-2 text-xs font-bold border border-gray-200 dark:border-white/10 hover:border-[var(--rankit-neon)] transition"
                        :class="slotInputs[selectedTournament.id].game_mode === m ? 'bg-[var(--rankit-neon)] text-white' : 'text-gray-500'">
                        {{ ['Solo','Duo','Trio','Squad'][m-1] }}
                    </button>
                 </div>
              </div>
              <div>
                <label class="text-[9px] font-bold uppercase text-gray-500 block mb-1">Código de Partida</label>
                <input 
                    v-model="slotInputs[selectedTournament.id].custom_code" 
                    type="text" placeholder="Ej: A1, FINAL..." 
                    class="py-1 font-mono text-sm text-center uppercase brutal-input" 
                />
              </div>
            </div>
            <button 
                @click="createSlot"
                :disabled="processingSlot[selectedTournament.id]"
                class="w-full py-3 mt-2 text-xs font-bold uppercase btn-skew"
            >
              <span class="btn-content">{{ processingSlot[selectedTournament.id] ? 'Creando...' : 'Crear Slot' }}</span>
            </button>
          </div>

          <!-- TAB: WIDGET -->
          <div v-if="activeTab === 'widget'" class="p-4 space-y-4 text-center animate-fade-in">
            <div class="p-3 border border-gray-300 border-dashed rounded-lg bg-gray-50 dark:border-gray-700 dark:bg-black/20">
              <div class="text-[10px] uppercase font-bold text-gray-500 mb-2">Tabla OBS Global (Top 10)</div>
              
              <div v-if="searchQuery" class="mb-2 bg-yellow-500/20 text-yellow-500 text-[10px] font-bold p-1 rounded">
                  ⚠️ Filtro activo: "{{ searchQuery }}"
              </div>

              <button @click="copyGlobalObsLink" class="px-4 py-2 text-[10px] font-bold uppercase border hover:border-[var(--rankit-neon)] transition flex items-center justify-center gap-2 mx-auto text-black dark:text-white border-gray-300 dark:border-gray-600 bg-white dark:bg-black w-full">
                <i class="ph ph-copy"></i> Copiar Link {{ searchQuery ? '(Filtrado)' : '(General)' }}
              </button>
            </div>
          </div>

          <!-- TAB: MATCHES -->
          <div v-if="activeTab === 'matches' && selectedTournament" class="p-0 animate-fade-in max-h-[400px] overflow-y-auto">
             <div @click="fetchLeaderboard(selectedTournament!, null)" class="flex items-center gap-3 p-3 transition border-b border-gray-200 cursor-pointer dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5">
                 <div class="w-8 h-8 flex items-center justify-center bg-[var(--rankit-neon)] text-white rounded-full"><i class="ph ph-globe"></i></div>
                 <div class="text-xs font-bold text-black dark:text-white">Ranking Global</div>
             </div>

             <div v-for="match in selectedTournament.matches" :key="match.id" 
                @click="match.status === 'processed' ? fetchLeaderboard(selectedTournament!, match.id) : null"
                class="flex items-center justify-between p-3 transition border-b border-gray-200 cursor-pointer group dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5"
                :class="{'bg-[var(--rankit-neon)]/10': selectedMatchId === match.id}"
             >
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 flex items-center justify-center text-[10px] font-bold bg-gray-200 dark:bg-gray-800 text-gray-500 border border-black/10 dark:border-white/10">
                     {{ match.game_mode[0].toUpperCase() }}
                  </div>
                  <div>
                    <div class="font-mono text-xs font-bold text-black uppercase dark:text-white">{{ match.custom_code }}</div>
                    <div v-if="match.status === 'pending'" class="text-[9px] text-yellow-500 font-bold animate-pulse">EN VIVO</div>
                    <div v-else class="text-[9px] text-green-500 font-bold">PROCESADA</div>
                  </div>
                </div>
                
                <div class="flex gap-2">
                    <button @click.stop="sendMatchCode(match.id)" :disabled="sendingMatchCodeId === match.id" class="p-1 text-gray-400 hover:text-[var(--rankit-neon)] disabled:opacity-50" title="Enviar Código por Email">
                        <i class="ph" :class="sendingMatchCodeId === match.id ? 'ph-spinner animate-spin' : 'ph-envelope-simple'"></i>
                    </button>
                    <button @click.stop="openAppealModal(match.id)" class="p-1 text-gray-400 hover:text-yellow-400" title="Apelar (Auto)">
                        <i class="ph ph-gavel"></i>
                    </button>
                    <button @click.stop="openEditMatchModal(match)" class="p-1 hover:text-blue-500" title="Editar Código">
                        <i class="ph ph-pencil-simple"></i>
                    </button>
                    <button @click.stop="openUploadModal(match.id)" class="p-1 hover:text-[var(--rankit-neon)]" title="Subir Replay">
                        <i class="ph ph-upload-simple"></i>
                    </button>
                    <button @click.stop="deleteMatch(match.id)" class="p-1 hover:text-red-500" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
              </div>
          </div>

          <!-- TAB: REGISTRATIONS -->
          <div v-if="activeTab === 'registrations' && selectedTournament" class="p-4 space-y-6 animate-fade-in flex flex-col justify-center items-center h-[300px]">
             <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Gestión de Inscripciones</h3>
             <div class="flex gap-6 w-full max-w-md">
                <button @click="showRegisteredUsersModal = true; fetchRegistrations()" class="flex-1 p-6 bg-emerald-500/10 border border-emerald-500/30 hover:bg-emerald-500 hover:text-white rounded-xl text-emerald-500 transition-all flex flex-col items-center justify-center gap-3 group">
                   <i class="ph-duotone ph-users text-4xl group-hover:scale-110 transition-transform"></i>
                   <span class="font-bold uppercase tracking-wider text-sm text-center">Ver Inscritos</span>
                </button>
                <button @click="showPendingRequestsModal = true; fetchRegistrations()" class="flex-1 p-6 bg-yellow-500/10 border border-yellow-500/30 hover:bg-yellow-500 hover:text-black rounded-xl text-yellow-500 transition-all flex flex-col items-center justify-center gap-3 group">
                   <i class="ph-duotone ph-clock text-4xl group-hover:scale-110 transition-transform"></i>
                   <span class="font-bold uppercase tracking-wider text-sm text-center">Ver Peticiones</span>
                </button>
             </div>
          </div>

          <!-- TAB: CLASIFICADOS -->
          <div v-if="activeTab === 'qualifiers' && selectedTournament" class="p-4 space-y-4 animate-fade-in">
             <div class="flex justify-between items-center mb-2">
                 <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500">Clasificados ({{ qualifiers.length }})</h3>
                 <button @click="fetchQualifiers" class="text-xs text-[var(--rankit-neon)] hover:underline"><i class="ph-bold ph-arrows-clockwise"></i></button>
             </div>

             <!-- Lista de clasificados con botón eliminar -->
             <div class="max-h-[200px] overflow-y-auto space-y-2 custom-scrollbar pr-2">
                 <div v-for="q in qualifiers" :key="q.id"
                      class="p-3 border border-gray-200 dark:border-white/10 rounded bg-gray-50 dark:bg-white/5 flex justify-between items-center text-xs">
                     <div>
                         <div class="font-bold text-sm text-black dark:text-white">{{ q.player_name }}</div>
                         <div class="text-[10px] text-gray-500">{{ q.player_email || 'Manual' }}</div>
                     </div>
                     <button @click="removeQualifier(q.id)"
                             class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-500/10 transition"
                             title="Desclasificar">
                         <i class="ph-bold ph-x-circle"></i>
                     </button>
                 </div>
                 <div v-if="qualifiers.length === 0" class="text-center py-6 text-gray-500 text-xs italic">
                     No hay clasificados.
                 </div>
             </div>

             <!-- Clasificar desde Ranking (select múltiple) -->
             <div class="pt-3 border-t border-gray-200 dark:border-white/10 space-y-3">
                 <h4 class="text-[10px] font-bold uppercase tracking-widest text-blue-500">Clasificar desde Ranking</h4>
                 <div class="text-[9px] text-gray-400">Ctrl+Click para seleccionar varios jugadores</div>
                 <select
                     v-model="selectedPlayersForClassification"
                     multiple
                     class="w-full h-[110px] text-xs bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded p-2 focus:border-[var(--rankit-neon)] outline-none custom-scrollbar"
                 >
                     <option v-for="item in filteredLeaderboard" :key="item.player_name" :value="item.player_name">
                         {{ item.player_name }} ({{ item.total_points }} pts)
                     </option>
                 </select>

                 <div class="space-y-1">
                     <label class="text-[9px] font-bold uppercase text-gray-500">Torneo Destino</label>
                     <select
                         v-model="targetTournamentId"
                         class="w-full text-xs bg-gray-100 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded p-2 focus:border-[var(--rankit-neon)] outline-none"
                     >
                         <option :value="null">-- Seleccionar Torneo --</option>
                         <option v-for="t in props.tournaments" :key="t.id" :value="t.id">
                             {{ t.name }} (ID: {{ t.id }})
                         </option>
                     </select>
                 </div>

                 <button
                     @click="classifyPlayers"
                     :disabled="selectedPlayersForClassification.length === 0 || !targetTournamentId"
                     class="w-full py-2 text-xs font-bold uppercase btn-skew disabled:opacity-50 disabled:cursor-not-allowed"
                 >
                     <span class="btn-content">Clasificar {{ selectedPlayersForClassification.length > 0 ? selectedPlayersForClassification.length + ' Jugador(es)' : '' }}</span>
                 </button>
             </div>

             <!-- Clasificación Manual -->
             <div class="pt-3 border-t border-gray-200 dark:border-white/10 space-y-3">
                 <h4 class="text-[10px] font-bold uppercase tracking-widest text-emerald-500">Añadir Manual</h4>
                 <div class="flex gap-2 mb-2">
                     <input v-model="formManualQualify.player_name" type="text" placeholder="Nombre en juego" class="flex-1 text-[10px] p-2 rounded bg-white dark:bg-black border border-gray-200 dark:border-white/10 outline-none focus:border-[var(--rankit-neon)]" />
                     <input v-model="formManualQualify.player_email" type="email" placeholder="Correo Rankit.Pro" class="flex-1 text-[10px] p-2 rounded bg-white dark:bg-black border border-gray-200 dark:border-white/10 outline-none focus:border-[var(--rankit-neon)]" />
                 </div>
                 <button @click="qualifyManual" :disabled="formManualQualify.processing || !formManualQualify.player_name || !formManualQualify.player_email" class="w-full py-2 bg-[var(--rankit-neon)] text-white text-xs font-bold uppercase rounded hover:opacity-90 transition disabled:opacity-50">
                     Añadir Clasificado
                 </button>
             </div>
          </div>
        </div>
      </aside>

      <!-- CENTER COLUMN -->
      <div class="space-y-6 lg:col-span-8">
        <!-- Header & Filters & Search -->
        <div class="flex flex-col gap-4 pb-4 border-b border-gray-300 dark:border-gray-800">
          <div class="flex flex-col items-start justify-between md:flex-row md:items-center">
              <div>
                  <h2 class="text-2xl font-bold text-black uppercase font-display dark:text-white">
                      {{ selectedMatchId ? `Partida Individual` : 'Ranking Global' }}
                  </h2>
                  <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">
                      {{ filteredLeaderboard.length }} Resultados
                  </p>
                  <p v-if="!selectedMatchId" class="text-[10px] text-blue-500 mt-1">
                      <i class="ph ph-info"></i> Selecciona una partida en el panel MATCHES para editar resultados individuales.
                  </p>
              </div>

              <!-- Filtro de Modalidad Global -->
              <div v-if="!selectedMatchId" class="flex flex-wrap gap-1 mt-2 md:mt-0">
                 <button 
                    v-for="m in ['all', 'solo', 'duo', 'trio', 'squad']" 
                    :key="m"
                    @click="filterMode=m; fetchLeaderboard(selectedTournament!, null)"
                    :class="filterMode===m ? 'bg-[var(--rankit-neon)] text-white shadow' : 'bg-gray-200 dark:bg-white/5 text-gray-500 hover:text-black dark:hover:text-white'"
                    class="px-3 py-1 text-[10px] font-bold uppercase rounded transition whitespace-nowrap"
                 >
                    {{ m }}
                 </button>
              </div>
          </div>

          <div class="flex flex-col items-center w-full gap-2 md:flex-row md:w-auto md:ml-auto">
               <div class="relative w-full md:w-48">
                   <i class="absolute text-gray-500 -translate-y-1/2 ph ph-magnifying-glass left-2 top-1/2"></i>
                   <input v-model="searchQuery" type="text" placeholder="Buscar..." class="w-full pl-8 pr-2 py-1 text-[10px] font-bold uppercase bg-gray-200 dark:bg-white/5 border border-transparent focus:border-[var(--rankit-neon)] rounded outline-none text-black dark:text-white transition" />
               </div>

               <div class="flex p-1 bg-gray-200 rounded-lg dark:bg-white/5">
                   <button @click="leaderboardType='players'; fetchLeaderboard(selectedTournament!, selectedMatchId)" :class="leaderboardType==='players'?'bg-white dark:bg-gray-700 shadow text-black dark:text-white':'text-gray-500'" class="px-3 py-1 text-[10px] font-bold uppercase rounded transition">Players</button>
                   <button @click="leaderboardType='teams'; fetchLeaderboard(selectedTournament!, selectedMatchId)" :class="leaderboardType==='teams'?'bg-white dark:bg-gray-700 shadow text-black dark:text-white':'text-gray-500'" class="px-3 py-1 text-[10px] font-bold uppercase rounded transition">Teams</button>
               </div>
               
               <button @click="sortBy = sortBy==='points'?'kills':'points'; fetchLeaderboard(selectedTournament!, selectedMatchId)" class="px-3 py-1 text-[10px] font-bold uppercase border border-gray-300 dark:border-gray-700 rounded hover:border-[var(--rankit-neon)] transition text-black dark:text-white">
                   {{ sortBy === 'points' ? 'Pts' : 'Kills' }}
               </button>
          </div>
        </div>

        <!-- LEADERBOARD TABLE -->
        <div class="brutal-card bg-white dark:bg-[#0a0a0a] min-h-[400px] relative">
          
          <div v-if="loadingLeaderboard" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-black/80 backdrop-blur-sm">
             <div class="flex flex-col items-center gap-2">
                 <div class="w-10 h-10 border-4 border-[var(--rankit-neon)] border-t-transparent rounded-full animate-spin"></div>
                 <span class="text-[10px] uppercase font-bold text-[var(--rankit-neon)] animate-pulse">Cargando datos...</span>
             </div>
          </div>
          
          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="text-gray-500 bg-gray-100 dark:bg-white/5 dark:text-gray-400">
                <tr>
                  <th class="px-4 py-3 font-bold uppercase text-[10px]">#</th>
                  <th class="px-4 py-3 font-bold uppercase text-[10px]">Participante</th>
                  <th class="px-4 py-3 font-bold uppercase text-[10px] text-center">Partidas</th>
                  <th class="px-4 py-3 font-bold uppercase text-[10px] text-right">Kills</th>
                  <th class="px-4 py-3 font-bold uppercase text-[10px] text-right">Pts</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                <template v-for="(item, idx) in filteredLeaderboard" :key="idx">
                    <tr @click="expandedRowIndex = expandedRowIndex === idx ? null : idx" class="transition cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="px-4 py-3 font-mono font-bold text-gray-500">{{ idx + 1 }}</td>
                        <td class="px-4 py-3 font-bold text-black dark:text-white">
                             <div v-if="leaderboardType==='teams'" class="flex flex-wrap gap-1">
                                 <span v-for="m in item.member_names" :key="m" class="text-[9px] bg-gray-200 dark:bg-white/10 px-1 rounded">{{ m }}</span>
                             </div>
                             <div v-else>{{ item.player_name }}</div>
                        </td>
                        <td class="px-4 py-3 font-mono text-center text-gray-500">{{ item.games_played }}</td>
                        <td class="px-4 py-3 font-mono text-right text-red-500">{{ item.total_kills }}</td>
                        <td class="px-4 py-3 text-right font-bold text-[var(--rankit-neon)] font-mono text-lg">{{ formatDec(item.total_points) }}</td>
                    </tr>
                    <tr v-if="expandedRowIndex === idx" class="bg-[var(--rankit-neon)]/5">
                        <td colspan="5" class="p-4">
                            <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                                <div class="grid flex-1 grid-cols-4 gap-4 text-xs">
                                    <div><span class="block text-gray-500">Avg Puntos</span> <span class="font-bold">{{ formatDec(item.avg_points) }}</span></div>
                                    <div><span class="block text-gray-500">Avg Kills</span> <span class="font-bold">{{ formatDec(item.avg_kills) }}</span></div>
                                    <div><span class="block text-gray-500">Avg Top</span> <span class="font-bold">#{{ formatDec(item.avg_placement) }}</span></div>
                                    <div><span class="block text-gray-500">Best Top</span> <span class="font-bold">#{{ item.best_placement }}</span></div>
                                </div>
                                <div class="flex gap-2">
                                     <button @click.stop="copyTrackingLink(item.player_name)" class="px-3 py-2 bg-black dark:bg-white text-white dark:text-black text-[10px] font-bold uppercase rounded hover:opacity-80 transition flex items-center gap-2 whitespace-nowrap">
                                        <i class="ph ph-target"></i> Tracking
                                    </button>
                                    <button v-if="selectedMatchId" @click.stop="openEditResult(item)" class="px-3 py-2 bg-blue-600 text-white text-[10px] font-bold uppercase rounded hover:bg-blue-700 transition flex items-center gap-2 whitespace-nowrap">
                                        <i class="ph ph-pencil-simple-line"></i> Editar Resultado
                                    </button>
                                    <button v-if="selectedMatchId" @click.stop="openManualAdjust(item)" class="px-3 py-2 bg-red-500 text-white text-[10px] font-bold uppercase rounded hover:bg-red-600 transition flex items-center gap-2 whitespace-nowrap">
                                        <i class="ph ph-warning"></i> Ajuste Pts
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr v-if="filteredLeaderboard.length === 0 && !loadingLeaderboard">
                    <td colspan="5" class="py-12 text-center text-gray-500">
                        {{ searchQuery ? 'No hay coincidencias.' : 'No hay datos registrados.' }}
                    </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>

    <!-- MODAL CREAR TORNEO -->
    <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="sm">
        <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
            <h2 class="mb-4 text-xl italic font-bold uppercase font-display">Nuevo Torneo</h2>
            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Nombre</label>
                    <input v-model="formCreateTournament.name" type="text" class="w-full bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-2 text-sm outline-none" placeholder="Ej: Torneo Verano 2025" />
                </div>
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Canal de Twitch (Opcional)</label>
                    <div class="flex items-center gap-2 bg-gray-100 dark:bg-white/5 rounded p-2 border border-transparent focus-within:border-[#9146FF]">
                        <i class="ph-bold ph-twitch-logo text-[#9146FF]"></i>
                        <input v-model="formCreateTournament.twitch_channel" type="text" placeholder="Ej: Bellz_z11" class="w-full p-0 text-sm bg-transparent border-none outline-none focus:ring-0" />
                    </div>
                </div>

                <div v-if="isJangel" class="p-3 border border-[var(--rankit-neon)]/30 rounded bg-[var(--rankit-neon)]/5 space-y-3">
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="formCreateTournament.is_private" class="rounded border-gray-600 text-[var(--rankit-neon)] focus:ring-[var(--rankit-neon)] bg-black/20" />
                            <span class="text-xs font-bold uppercase">Torneo Privado</span>
                        </label>
                        <div v-if="formCreateTournament.is_private" class="mt-2">
                            <input v-model="formCreateTournament.access_code" type="text" placeholder="CÓDIGO DE ACCESO" class="w-full p-2 text-xs font-bold text-center uppercase border rounded outline-none border-red-500/30 bg-red-500/10 focus:border-red-500" />
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="formCreateTournament.is_serialized" class="rounded border-gray-600 text-[var(--rankit-neon)] focus:ring-[var(--rankit-neon)] bg-black/20" />
                            <span class="text-xs font-bold uppercase">Torneo Seriado</span>
                        </label>
                        <div v-if="formCreateTournament.is_serialized" class="mt-2">
                            <select v-model="formCreateTournament.parent_tournament_id" class="w-full p-2 text-xs font-bold uppercase border rounded outline-none border-blue-500/30 bg-blue-500/10 focus:border-blue-500 text-black dark:text-white custom-scrollbar">
                                <option :value="null">Ninguno (Este es Torneo Matriz)</option>
                                <option v-for="t in props.tournaments" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button @click="showCreateModal = false" class="flex-1 py-3 text-xs font-bold uppercase border border-gray-300 dark:border-gray-700">Cancelar</button>
                    <button @click="createTournament" :disabled="formCreateTournament.processing" class="flex-1 py-3 bg-[var(--rankit-neon)] text-white font-bold uppercase text-xs hover:opacity-90 transition">
                        Crear
                    </button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- MODAL SETTINGS (ADVANCED TABS) -->
    <Modal :show="showSettingsModal" @close="showSettingsModal = false" maxWidth="2xl">
        <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white h-[600px] flex flex-col">
            <h2 class="mb-4 text-xl italic font-bold uppercase font-display">Configurar Torneo</h2>
            
            <!-- Tabs Header -->
            <div class="flex mb-4 overflow-x-auto border-b border-gray-200 dark:border-gray-700">
                <button v-for="tab in ['general', 'scoring', 'rules', 'prizes']" :key="tab"
                    @click="settingsTab = tab as any"
                    class="px-4 py-2 text-xs font-bold uppercase transition border-b-2 whitespace-nowrap"
                    :class="settingsTab === tab ? 'border-[var(--rankit-neon)] text-[var(--rankit-neon)]' : 'border-transparent text-gray-500 hover:text-black dark:hover:text-white'">
                    {{ tab === 'scoring' ? 'Puntuación' : tab }}
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 pr-2 space-y-4 overflow-y-auto custom-scrollbar">
                
                <!-- GENERAL TAB -->
                <div v-if="settingsTab === 'general'" class="space-y-4">
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Nombre Torneo</label>
                        <input v-model="formSettings.name" type="text" class="w-full bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-2 text-sm outline-none" />
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Canal de Twitch</label>
                        <input v-model="formSettings.twitch_channel" type="text" class="w-full bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-2 text-sm outline-none" />
                    </div>

                    <!-- BANNER IMAGE -->
                    <div class="p-3 border border-gray-200 dark:border-white/10 rounded bg-gray-50 dark:bg-white/5">
                        <label class="text-[10px] font-bold uppercase text-gray-500 block mb-2">Imagen de Fondo (Banner)</label>
                        <div v-if="selectedTournament?.banner_image" class="mb-2">
                            <img :src="selectedTournament.banner_image" class="w-full h-24 object-cover rounded opacity-80" />
                            <p class="text-[9px] text-gray-400 mt-1">Imagen actual. Sube una nueva para reemplazarla.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="file" accept="image/*" @change="handleBannerFileChange" class="flex-1 text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-bold file:bg-[var(--rankit-neon)] file:text-white hover:file:opacity-80" />
                            <button @click="uploadBannerImage" :disabled="!formBanner.banner || formBanner.processing" class="px-3 py-1.5 text-xs font-bold text-white uppercase bg-[var(--rankit-neon)] rounded hover:opacity-80 transition disabled:opacity-40 whitespace-nowrap">
                                <i class="ph-bold ph-upload-simple"></i> Subir
                            </button>
                        </div>
                        <p class="text-[9px] text-gray-400 mt-1">JPG, PNG, GIF o WebP · Máx. 5 MB</p>
                    </div>

                    <div v-if="isJangel" class="p-3 border border-[var(--rankit-neon)]/30 rounded bg-[var(--rankit-neon)]/5 space-y-3">
                        <div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="formSettings.is_private" class="rounded border-gray-600 text-[var(--rankit-neon)] focus:ring-[var(--rankit-neon)] bg-black/20" />
                                <span class="text-xs font-bold uppercase">Torneo Privado</span>
                            </label>
                            <div v-if="formSettings.is_private" class="mt-2">
                                <input v-model="formSettings.access_code" type="text" placeholder="CÓDIGO DE ACCESO" class="w-full p-2 text-xs font-bold text-center uppercase border rounded outline-none border-red-500/30 bg-red-500/10 focus:border-red-500" />
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" v-model="formSettings.is_serialized" class="rounded border-gray-600 text-[var(--rankit-neon)] focus:ring-[var(--rankit-neon)] bg-black/20" />
                                <span class="text-xs font-bold uppercase">Torneo Seriado</span>
                            </label>
                            <div v-if="formSettings.is_serialized" class="mt-2">
                                <select v-model="formSettings.parent_tournament_id" class="w-full p-2 text-xs font-bold uppercase border rounded outline-none border-blue-500/30 bg-blue-500/10 focus:border-blue-500 text-black dark:text-white custom-scrollbar">
                                    <option :value="null">Ninguno (Este es Torneo Matriz)</option>
                                    <option v-for="t in props.tournaments" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Estado de Inscripciones</label>
                            <select v-model="formSettings.registration_status" class="w-full p-2 text-xs font-bold uppercase border rounded outline-none border-[var(--rankit-neon)]/30 bg-[var(--rankit-neon)]/10 focus:border-[var(--rankit-neon)] text-black dark:text-white custom-scrollbar">
                                <option value="open">Abiertas (Públicas)</option>
                                <option value="closed">Cerradas</option>
                                <option value="disabled">Sin Inscripciones</option>
                            </select>
                            
                            <div v-if="formSettings.registration_status === 'open' || formSettings.registration_status === 'closed'" class="mt-2">
                                <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Precio del Boleto</label>
                                <input v-model="formSettings.ticket_price" type="text" placeholder="Ej. $150 MXN (vía WhatsApp)" class="w-full p-2 text-xs font-bold uppercase border rounded outline-none border-green-500/30 bg-green-500/10 focus:border-green-500" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SCORING TAB -->
                <div v-if="settingsTab === 'scoring'" class="space-y-4">
                    <div class="p-3 bg-blue-500/10 border border-blue-500/20 rounded text-[10px] text-blue-500 dark:text-blue-400">
                        <i class="ph-bold ph-info"></i> El sistema usará estas reglas para calcular los puntos automáticamente al subir replays. Si está vacío, usará el modo por defecto.
                    </div>
                    
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Puntos por Kill</label>
                        <input v-model="formSettings.scoring_format.kill_points" type="number" class="w-24 bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-2 text-sm outline-none" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-[10px] font-bold uppercase text-gray-500">Rangos de Posición</label>
                            <button @click="addPlacementRule" class="text-[10px] bg-[var(--rankit-neon)] text-white px-2 py-1 rounded hover:opacity-80 transition">+ Agregar Regla</button>
                        </div>
                        
                        <div v-for="(rule, idx) in formSettings.scoring_format.placement" :key="idx" class="flex items-center gap-2 p-2 mb-2 border border-gray-200 rounded bg-gray-50 dark:bg-white/5 dark:border-white/10">
                            <span class="text-xs font-bold text-gray-500">Top</span>
                            <input type="number" v-model="rule.from" class="w-16 p-1 text-xs text-center bg-white border border-gray-300 rounded dark:bg-black dark:border-gray-700" placeholder="1" />
                            <span class="text-xs font-bold text-gray-500">a</span>
                            <input type="number" v-model="rule.to" class="w-16 p-1 text-xs text-center bg-white border border-gray-300 rounded dark:bg-black dark:border-gray-700" placeholder="1" />
                            <span class="text-xs font-bold text-gray-500">=</span>
                            <input type="number" v-model="rule.points" class="w-16 bg-white dark:bg-black border border-gray-300 dark:border-gray-700 rounded p-1 text-center text-xs font-bold text-[var(--rankit-neon)]" placeholder="10" />
                            <span class="text-xs font-bold text-gray-500">pts</span>
                            <button @click="removePlacementRule(idx)" class="p-1 ml-auto text-red-500 rounded hover:bg-red-500/10"><i class="ph-bold ph-trash"></i></button>
                        </div>
                        
                        <div v-if="formSettings.scoring_format.placement.length === 0" class="py-4 text-xs italic text-center text-gray-400">
                            No hay reglas definidas.
                        </div>
                    </div>
                </div>

                <!-- RULES TAB -->
                <div v-if="settingsTab === 'rules'" class="h-full">
                    <textarea v-model="formSettings.rules" class="w-full h-64 bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-3 text-sm outline-none resize-none" placeholder="Escribe las reglas aquí (Soporta texto simple)..."></textarea>
                </div>

                <!-- PRIZES TAB -->
                <div v-if="settingsTab === 'prizes'" class="h-full">
                     <textarea v-model="formSettings.prizes" class="w-full h-64 bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-3 text-sm outline-none resize-none" placeholder="Lista de premios..."></textarea>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="flex items-center justify-between pt-4 mt-auto border-t border-gray-200 dark:border-white/10">
                 <button 
                    v-if="selectedTournament?.matches.length === 0" 
                    @click="deleteTournament" 
                    class="px-3 py-2 text-xs font-bold text-red-500 uppercase transition rounded hover:bg-red-500/10"
                >
                    <i class="ph-bold ph-trash"></i> Eliminar Torneo
                </button>
                <div v-else></div>

                <div class="flex gap-2">
                    <button @click="showSettingsModal = false" class="px-4 py-2 text-xs font-bold uppercase border border-gray-300 rounded dark:border-gray-700">Cancelar</button>
                    <button @click="updateTournament" :disabled="formSettings.processing" class="px-4 py-2 bg-[var(--rankit-neon)] text-white font-bold uppercase text-xs rounded hover:opacity-90 transition">
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- MODAL EDIT MATCH CODE -->
    <Modal :show="showEditMatchModal" @close="showEditMatchModal = false" maxWidth="sm">
        <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
            <h2 class="mb-4 text-lg italic font-bold uppercase font-display">Editar Código</h2>
            <input v-model="formEditMatch.custom_code" type="text" class="w-full text-center text-xl font-mono uppercase bg-gray-100 dark:bg-white/5 border-transparent focus:border-[var(--rankit-neon)] rounded p-3 outline-none mb-4" />
            <div class="flex gap-2">
                <button @click="showEditMatchModal = false" class="flex-1 py-2 text-xs font-bold uppercase border border-gray-300 dark:border-gray-700">Cancelar</button>
                <button @click="updateMatchCode" :disabled="formEditMatch.processing" class="flex-1 py-2 text-xs font-bold text-white uppercase bg-black dark:bg-white dark:text-black">Guardar</button>
            </div>
        </div>
    </Modal>

    <!-- MODAL MANUAL ADJUST (NUEVO) -->
    <Modal :show="showManualAdjustModal" @close="showManualAdjustModal=false" maxWidth="sm">
        <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
            <h2 class="mb-2 text-lg italic font-bold text-red-500 uppercase font-display">Ajuste Manual</h2>
            
            <!-- Selector de Jugador si es Equipo -->
            <div class="mb-4">
                 <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Jugador Objetivo</label>
                 <div v-if="availablePlayers.length > 1">
                     <select v-model="formManualAdjust.player_name" class="w-full p-2 bg-gray-100 dark:bg-white/5 border border-gray-300 dark:border-gray-700 rounded text-sm font-bold outline-none focus:border-[var(--rankit-neon)]">
                         <option v-for="p in availablePlayers" :key="p" :value="p">{{ p }}</option>
                     </select>
                     <p class="text-[9px] text-gray-400 mt-1">Selecciona a quién aplicar los puntos (afecta al equipo).</p>
                 </div>
                 <div v-else>
                     <span class="text-lg font-bold">{{ formManualAdjust.player_name }}</span>
                 </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Puntos a sumar/restar</label>
                    <input type="number" v-model="formManualAdjust.points_change" class="w-full p-2 font-bold bg-gray-100 border-none rounded dark:bg-white/5" placeholder="-10 o 10" />
                    <p class="text-[9px] text-gray-400 mt-1">Usa números negativos para penalizar (ej: -10).</p>
                </div>
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Razón (Requerido)</label>
                    <input type="text" v-model="formManualAdjust.reason" class="w-full p-2 text-sm bg-gray-100 border-none rounded dark:bg-white/5" placeholder="Ej: Uso de bug, Toxicidad..." />
                </div>
                <button @click="submitManualAdjust" :disabled="formManualAdjust.processing || !formManualAdjust.reason" class="w-full py-3 text-xs font-bold text-white uppercase transition bg-red-500 rounded hover:bg-red-600">
                    Confirmar Ajuste
                </button>
            </div>
        </div>
    </Modal>

    <!-- MODAL EDITAR RESULTADO DIRECTO -->
    <Modal :show="showEditResultModal" @close="showEditResultModal = false" maxWidth="sm">
        <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
            <h2 class="mb-1 text-lg italic font-bold text-blue-500 uppercase font-display">
                <i class="ph ph-pencil-simple-line"></i> Editar Resultado
            </h2>
            <p class="mb-4 text-[10px] text-gray-500 uppercase font-bold">Modifica directamente kills, posición y puntos del jugador en esta partida.</p>

            <div class="space-y-4">
                <!-- Jugador -->
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Jugador</label>
                    <div class="p-2 font-bold bg-gray-100 rounded dark:bg-white/5 text-sm">{{ formEditResult.player_name }}</div>
                </div>

                <!-- Kills -->
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Kills</label>
                    <input type="number" v-model="formEditResult.kills" min="0" class="w-full p-2 font-bold bg-gray-100 border-none rounded dark:bg-white/5 text-center text-lg" />
                </div>

                <!-- Posición -->
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">Posición (Placement)</label>
                    <input type="number" v-model="formEditResult.placement" min="1" class="w-full p-2 font-bold bg-gray-100 border-none rounded dark:bg-white/5 text-center text-lg" />
                </div>

                <!-- Puntos Override (Opcional) -->
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 block mb-1">
                        Puntos Totales <span class="text-gray-400 normal-case">(opcional · si se deja vacío, se recalculan automáticamente)</span>
                    </label>
                    <input type="number" v-model="formEditResult.points_override" class="w-full p-2 font-bold bg-gray-100 border-none rounded dark:bg-white/5 text-center text-lg" placeholder="Auto" />
                </div>

                <div class="flex gap-2 pt-2">
                    <button @click="showEditResultModal = false" class="flex-1 py-2 text-xs font-bold uppercase border border-gray-300 dark:border-gray-700 rounded">Cancelar</button>
                    <button @click="submitEditResult" :disabled="formEditResult.processing" class="flex-1 py-2 bg-blue-600 text-white font-bold uppercase text-xs rounded hover:bg-blue-700 transition disabled:opacity-50">
                        <i class="ph ph-check"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- MODAL APELAR (AUTOMÁTICA) -->
    <Modal :show="showAppealModal" @close="showAppealModal = false" maxWidth="lg">
      <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
        <div class="flex items-start justify-between mb-6">
          <div>
            <h2 class="flex items-center gap-2 text-xl italic font-bold uppercase font-display">
              <i class="text-yellow-500 ph-fill ph-gavel"></i> Apelar Resultado
            </h2>
            <p class="text-[10px] text-gray-500 mt-1 uppercase font-bold">Cálculo automático basado en reglas del torneo</p>
          </div>
          <button @click="showAppealModal = false" class="text-gray-500 hover:text-red-500">
            <i class="text-xl ph ph-x"></i>
          </button>
        </div>

        <div class="p-4 border rounded-lg bg-yellow-500/5 border-yellow-500/20">
          <h3 class="flex items-center gap-2 mb-3 text-sm font-bold text-yellow-500 uppercase">
            <i class="ph ph-file-arrow-up"></i> Seleccionar Replay de Jugador
          </h3>
          <div class="space-y-4">
            <input type="file" @change="handleAppealFileUpload" 
                class="block w-full text-xs text-gray-500 bg-gray-100 border border-gray-200 cursor-pointer file:mr-4 file:py-2 file:px-4 file:border-0 file:text-xs file:font-bold file:uppercase file:bg-yellow-500 file:text-black hover:file:bg-black file:cursor-pointer file:transition dark:bg-black/20 dark:border-white/10" />
            
            <div v-if="formAppeal.processing" class="w-full h-2 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-800">
                <div class="h-full transition-all duration-300 bg-yellow-500" :style="{width: uploadProgress + '%'}"></div>
            </div>

            <button @click="submitAppeal" 
                :disabled="!formAppeal.replay || formAppeal.processing" 
                class="w-full py-3 text-xs font-bold text-black uppercase transition bg-yellow-500 hover:bg-yellow-400 disabled:opacity-50 disabled:cursor-not-allowed">
              {{ formAppeal.processing ? 'Analizando...' : 'Procesar Apelación' }}
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- MODAL UPLOAD (NORMAL) -->
    <Modal :show="showMatchModal" @close="showMatchModal = false" maxWidth="lg">
      <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white">
        <div class="flex items-start justify-between mb-6">
          <div>
            <h2 class="text-xl italic font-bold uppercase font-display">
              Subir Replay <span class="text-[var(--rankit-neon)]" v-if="formReplay.target_match_id">Overwrite</span>
            </h2>
          </div>
          <button @click="showMatchModal = false" class="text-gray-500 hover:text-red-500">
            <i class="text-xl ph ph-x"></i>
          </button>
        </div>

        <div class="p-4 mb-4 bg-gray-100 rounded-lg dark:bg-white/5">
             <label class="text-[10px] font-bold uppercase text-gray-500 block mb-2">Modo de Juego</label>
             <select v-model="formReplay.mode" class="w-full p-2 text-sm bg-white border border-gray-300 rounded dark:bg-black dark:border-gray-700 outline-none focus:border-[var(--rankit-neon)]">
                 <option :value="null" disabled selected>-- Selecciona un Modo --</option>
                 <option :value="1">Solo</option>
                 <option :value="2">Duo</option>
                 <option :value="3">Trio</option>
                 <option :value="4">Squad</option>
             </select>
        </div>

        <div class="bg-[var(--rankit-neon)]/5 border border-[var(--rankit-neon)]/20 p-4">
          <h3 class="text-sm font-bold uppercase mb-3 flex items-center gap-2 text-[var(--rankit-neon)]">
            <i class="ph ph-file-arrow-up"></i> Seleccionar Archivo
          </h3>
          <div class="space-y-4">
            <input type="file" @change="handleFileUpload" 
                class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-xs file:font-bold file:uppercase file:bg-[var(--rankit-neon)] file:text-white hover:file:bg-black file:cursor-pointer file:transition cursor-pointer bg-gray-100 dark:bg-black/20 border border-gray-200 dark:border-white/10" />
            
            <div v-if="formReplay.processing" class="w-full h-2 overflow-hidden bg-gray-200 rounded-full dark:bg-gray-800">
                <div class="h-full bg-[var(--rankit-neon)] transition-all duration-300" :style="{width: uploadProgress + '%'}"></div>
            </div>

            <button @click="submitReplay" 
                :disabled="!formReplay.replay || !formReplay.mode || formReplay.processing" 
                class="w-full py-3 text-xs font-bold text-white uppercase transition bg-black dark:bg-white dark:text-black hover:opacity-80 disabled:opacity-50 disabled:cursor-not-allowed">
              {{ formReplay.processing ? 'Procesando...' : 'Procesar Replay' }}
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- MODAL: REGISTERED USERS -->
    <Modal :show="showRegisteredUsersModal" @close="showRegisteredUsersModal = false" maxWidth="2xl">
      <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white max-h-[80vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-6">
          <h2 class="text-xl italic font-bold uppercase font-display">Usuarios Inscritos (Pagados)</h2>
          <button @click="showRegisteredUsersModal = false" class="text-gray-500 hover:text-red-500">
            <i class="text-xl ph ph-x"></i>
          </button>
        </div>
        <div class="space-y-2">
            <div v-for="reg in registrations.filter(r => r.payment_status === 'paid')" :key="reg.id" class="p-3 border border-gray-200 dark:border-white/10 rounded bg-gray-50 dark:bg-white/5 flex flex-col gap-2 text-xs">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-sm text-black dark:text-white">{{ reg.player_name }}</div>
                        <div class="text-[10px] text-gray-500">{{ reg.email }}</div>
                    </div>
                    <div class="text-right">
                        <span class="text-emerald-500 font-bold uppercase text-[10px]">PAGADO</span>
                    </div>
                </div>
                <div class="flex gap-2 mt-2">
                    <a v-if="reg.whatsapp" :href="`https://wa.me/${reg.whatsapp.replace(/[^0-9]/g, '')}`" target="_blank" class="flex-1 py-1 text-center bg-green-500/10 text-green-500 border border-green-500/20 hover:bg-green-500 hover:text-white rounded transition flex justify-center items-center gap-1 font-bold">
                        <i class="ph-bold ph-whatsapp-logo"></i> WhatsApp
                    </a>
                    <button @click="rejectAndMove(reg.id)" class="flex-1 py-1 bg-red-500/10 text-red-500 border border-red-500/20 hover:bg-red-500 hover:text-white rounded transition">Rechazar / Mover</button>
                </div>
            </div>
            <div v-if="registrations.filter(r => r.payment_status === 'paid').length === 0" class="text-center py-6 text-gray-500 text-xs italic">
                No hay usuarios inscritos aún.
            </div>
        </div>
      </div>
    </Modal>

    <!-- MODAL: PENDING REQUESTS -->
    <Modal :show="showPendingRequestsModal" @close="showPendingRequestsModal = false" maxWidth="2xl">
      <div class="p-6 bg-white dark:bg-[#101012] text-black dark:text-white max-h-[80vh] overflow-y-auto">
        <div class="flex items-start justify-between mb-4">
          <h2 class="text-xl italic font-bold uppercase font-display flex items-center gap-3">
              Gestión de Peticiones
              <button @click="acceptAllRegistrations" v-if="registrations.some(r => r.payment_status === 'pending')" class="text-[10px] font-bold text-emerald-500 border border-emerald-500/30 hover:bg-emerald-500 hover:text-white px-2 py-1 rounded transition">
                 <i class="ph-bold ph-check-circle"></i> Aceptar Todas
              </button>
          </h2>
          <button @click="showPendingRequestsModal = false" class="text-gray-500 hover:text-red-500">
            <i class="text-xl ph ph-x"></i>
          </button>
        </div>

        <!-- Toast de acción -->
        <div v-if="registrationToast" class="mb-4 px-4 py-2 rounded text-xs font-bold flex items-center gap-2"
             :class="registrationToast.type === 'success' ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30' : 'bg-red-500/10 text-red-500 border border-red-500/30'">
            <i :class="registrationToast.type === 'success' ? 'ph-bold ph-check-circle' : 'ph-bold ph-x-circle'"></i>
            {{ registrationToast.message }}
        </div>

        <!-- SECCIÓN: PENDIENTES -->
        <div class="mb-5">
            <div class="flex items-center gap-2 mb-3 pb-2 border-b border-yellow-500/30">
                <span class="w-2.5 h-2.5 rounded-full bg-yellow-500 inline-block animate-pulse"></span>
                <span class="text-xs font-bold uppercase tracking-widest text-yellow-500">
                    Pendientes ({{ registrations.filter(r => r.payment_status === 'pending').length }})
                </span>
            </div>
            <div class="space-y-2">
                <div v-for="reg in registrations.filter(r => r.payment_status === 'pending')" :key="reg.id"
                     class="p-3 border border-yellow-500/20 rounded bg-yellow-500/5 flex flex-col gap-2 text-xs transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-black dark:text-white">{{ reg.player_name }}</div>
                            <div class="text-[10px] text-gray-500">{{ reg.email }}</div>
                        </div>
                        <span class="text-yellow-500 font-bold uppercase text-[10px] bg-yellow-500/10 border border-yellow-500/30 px-2 py-0.5 rounded">PENDIENTE</span>
                    </div>
                    <div class="flex gap-2 mt-1">
                        <a v-if="reg.whatsapp" :href="`https://wa.me/${reg.whatsapp.replace(/[^0-9]/g, '')}`" target="_blank"
                           class="py-1 px-2 text-center bg-green-500/10 text-green-500 border border-green-500/20 hover:bg-green-500 hover:text-white rounded transition flex items-center gap-1 font-bold">
                            <i class="ph-bold ph-whatsapp-logo"></i>
                        </a>
                        <button @click="approveRegistration(reg.id)"
                                class="flex-1 py-1 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 hover:bg-emerald-500 hover:text-white rounded transition font-bold text-[10px]">
                            ✓ Aprobar
                        </button>
                        <button @click="rejectRegistration(reg.id)"
                                class="flex-1 py-1 bg-red-500/10 text-red-500 border border-red-500/20 hover:bg-red-500 hover:text-white rounded transition font-bold text-[10px]">
                            ✗ Rechazar
                        </button>
                    </div>
                </div>
                <div v-if="registrations.filter(r => r.payment_status === 'pending').length === 0"
                     class="text-center py-5 text-gray-400 text-xs border border-dashed border-gray-200 dark:border-white/10 rounded">
                    <i class="ph-bold ph-check-circle text-xl text-emerald-500 block mb-1"></i>
                    Sin peticiones pendientes.
                </div>
            </div>
        </div>

        <!-- SECCIÓN: RECHAZADAS -->
        <div>
            <div class="flex items-center gap-2 mb-3 pb-2 border-b border-red-500/30">
                <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>
                <span class="text-xs font-bold uppercase tracking-widest text-red-500">
                    Rechazadas ({{ registrations.filter(r => r.payment_status === 'rejected').length }})
                </span>
            </div>
            <div class="space-y-2">
                <div v-for="reg in registrations.filter(r => r.payment_status === 'rejected')" :key="reg.id"
                     class="p-3 border border-red-500/20 rounded bg-red-500/5 flex flex-col gap-2 text-xs transition-all">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-black dark:text-white">{{ reg.player_name }}</div>
                            <div class="text-[10px] text-gray-500">{{ reg.email }}</div>
                        </div>
                        <span class="text-red-500 font-bold uppercase text-[10px] bg-red-500/10 border border-red-500/30 px-2 py-0.5 rounded">RECHAZADA</span>
                    </div>
                    <div class="flex gap-2 mt-1">
                        <a v-if="reg.whatsapp" :href="`https://wa.me/${reg.whatsapp.replace(/[^0-9]/g, '')}`" target="_blank"
                           class="py-1 px-2 text-center bg-green-500/10 text-green-500 border border-green-500/20 hover:bg-green-500 hover:text-white rounded transition flex items-center gap-1 font-bold">
                            <i class="ph-bold ph-whatsapp-logo"></i>
                        </a>
                        <button @click="approveRegistration(reg.id)"
                                class="flex-1 py-1 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 hover:bg-emerald-500 hover:text-white rounded transition font-bold text-[10px]">
                            ✓ Aprobar
                        </button>
                        <button @click="updatePayment(reg.id, 'pending')"
                                class="flex-1 py-1 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border border-yellow-500/20 hover:bg-yellow-500 hover:text-white rounded transition font-bold text-[10px]">
                            ↩ Regresar a Pendiente
                        </button>
                    </div>
                </div>
                <div v-if="registrations.filter(r => r.payment_status === 'rejected').length === 0"
                     class="text-center py-5 text-gray-400 text-xs border border-dashed border-gray-200 dark:border-white/10 rounded">
                    Sin inscripciones rechazadas.
                </div>
            </div>
        </div>

      </div>
    </Modal>

  </div>
</template>

<style>
:root { --rankit-neon: #bf00ff; }
.font-display { font-family: "Chakra Petch", sans-serif; }
.font-sans { font-family: "Archivo", sans-serif; }
.brutal-card { position: relative; transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94); border: 1px solid; }
.dark .brutal-card { background: #0a0a0a; border-color: #333; }
html:not(.dark) .brutal-card { background: #ffffff; border-color: #e5e5e5; box-shadow: 4px 4px 0px #00000010; }
.brutal-card:hover { border-color: var(--rankit-neon); transform: translate(-4px, -4px); }
.dark .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon); }
html:not(.dark) .brutal-card:hover { box-shadow: 6px 6px 0px var(--rankit-neon), 6px 6px 0px 2px black; }
.brutal-input { width: 100%; background: transparent; border-bottom: 2px solid #333; padding: 0.5rem 0; font-family: "Archivo", sans-serif; font-weight: 600; outline: none; transition: all 0.3s; }
.dark .brutal-input { color: white; border-color: #333; }
html:not(.dark) .brutal-input { color: black; border-color: #e5e5e5; }
.brutal-input:focus { border-color: var(--rankit-neon); padding-left: 0.5rem; }
.btn-skew { background-color: var(--rankit-neon); color: white; transform: skewX(-10deg); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.btn-skew:hover { background-color: white; color: black; box-shadow: 0 0 15px var(--rankit-neon); }
html:not(.dark) .btn-skew:hover { background-color: black; color: white; box-shadow: 4px 4px 0px rgba(0,0,0,0.2); }
.btn-content { transform: skewX(10deg); }
.animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>