<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bracket Widget — RanKit</title>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@700&family=Archivo:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --neon: #bf00ff; }
        body { 
            background: black; 
            color: white; 
            font-family: 'Archivo', sans-serif;
            overflow: hidden;
        }
        .font-display { font-family: 'Chakra Petch', sans-serif; }
        
        .header-glow {
            background: linear-gradient(90deg, var(--neon), #fb00ff);
            box-shadow: 0 0 20px rgba(191, 0, 255, 0.4);
        }

        .bracket-node {
            background: #0a0a0a;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }
        .bracket-node.winner {
            border-color: var(--neon);
            box-shadow: 0 0 10px rgba(191, 0, 255, 0.2);
        }

        /* Connecting lines for elimination bracket */
        .line-v { position: absolute; width: 2px; background: rgba(255,255,255,0.1); }
        .line-h { position: absolute; height: 2px; background: rgba(255,255,255,0.1); }
        .active-line { background: var(--neon) !important; opacity: 0.6; }

        @keyframes pulse-neon {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 1; transform: scale(1.02); }
        }
        .champion-banner {
            animation: pulse-neon 2s infinite ease-in-out;
        }
    </style>
</head>
<body>
    <div id="app" class="p-8 min-h-screen flex flex-col">
        <!-- Header -->
        <div class="flex items-center justify-between mb-10">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 header-glow flex items-center justify-center rounded-lg italic font-black text-2xl font-display">R</div>
                <div>
                    <h1 class="text-3xl font-black uppercase tracking-tighter font-display" id="tournament-name">CARGANDO...</h1>
                    <div id="phase-label" class="text-[10px] font-bold tracking-[0.3em] text-gray-400 uppercase">FASE DE TORNEO</div>
                </div>
            </div>
            <div id="status-badge" class="px-4 py-1 rounded border border-white/20 text-xs font-black uppercase tracking-widest text-white">LIVE</div>
        </div>

        <!-- Dynamic Content -->
        <div id="content" class="flex-1 flex flex-col justify-center">
            <!-- Phase 1: Swiss View -->
            <div id="swiss-view" class="hidden">
                <div id="swiss-rounds" class="flex flex-wrap gap-8 justify-center">
                    <!-- Rounds will be injected here -->
                </div>
            </div>

            <!-- Phase 2: Elimination View -->
            <div id="elimination-view" class="hidden">
                <div id="elim-bracket" class="flex justify-around items-center h-full gap-12">
                    <!-- Bracket rounds will be injected here -->
                </div>
            </div>
        </div>

        <!-- Footer / Champion -->
        <div id="champion-container" class="mt-auto hidden">
            <div class="champion-banner header-glow py-4 text-center rounded-xl border-2 border-white/20">
                <div class="text-[10px] font-black uppercase tracking-[0.5em] mb-1">¡CAMPEÓN DEL TORNEO!</div>
                <div id="champion-name" class="text-4xl font-black uppercase font-display italic">NOMBREEQUIPO</div>
            </div>
        </div>

        <!-- Rankit.pro Branding Footer -->
        <div class="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-white/5">
            <span class="text-[8px] font-bold tracking-[0.25em] uppercase text-white/20">POWERED BY</span>
            <div class="flex items-center gap-1.5">
                <svg width="16" height="16" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white"/>
                    <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white"/>
                    <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--neon)"/>
                </svg>
                <span class="text-[11px] font-black uppercase tracking-wider text-white/70">RANKIT<span style="color:var(--neon)">.PRO</span></span>
            </div>
        </div>
    </div>

    <script>
        const tournamentId = {{ $tournament->id }};
        const selectedPhase = "{{ $phase }}"; // 'swiss', 'elimination', or 'all'
        
        async function update() {
            try {
                const res = await fetch(`/lol/${tournamentId}/widget-data`);
                const data = await res.json();
                render(data);
            } catch (e) { console.error(e); }
        }

        function render(data) {
            const t = data.tournament;
            const teams = data.teams;
            const matches = data.matches;

            document.getElementById('tournament-name').innerText = t.name;

            // Determine what to show
            const showSwiss = selectedPhase === 'swiss' || (selectedPhase === 'all' && t.phase === 'swiss');
            const showElim  = selectedPhase === 'elimination' || (selectedPhase === 'all' && t.phase === 'elimination') || t.phase === 'done';

            document.getElementById('swiss-view').classList.toggle('hidden', !showSwiss);
            document.getElementById('elimination-view').classList.toggle('hidden', !showElim);
            document.getElementById('champion-container').classList.toggle('hidden', t.phase !== 'done');
            
            if (t.phase === 'done' && showElim) {
                const finalRound = matches.filter(m => m.phase === 'elimination').reduce((max, m) => Math.max(max, m.round), 0);
                const finalMatch = matches.find(m => m.phase === 'elimination' && m.round === finalRound);
                if (finalMatch && finalMatch.winner_id) {
                    const champ = teams.find(te => te.id === finalMatch.winner_id);
                    document.getElementById('champion-name').innerText = champ ? champ.name : 'TBD';
                }
            }

            if (showSwiss) renderSwiss(matches, teams);
            if (showElim) renderElimination(matches, teams, t);
        }

        function renderSwiss(matches, teams) {
            const swissMatches = matches.filter(m => m.phase === 'swiss');
            const rounds = {};
            swissMatches.forEach(m => {
                if (!rounds[m.round]) rounds[m.round] = [];
                rounds[m.round].push(m);
            });

            const container = document.getElementById('swiss-rounds');
            container.innerHTML = '';

            Object.keys(rounds).sort().forEach(r => {
                const roundDiv = document.createElement('div');
                roundDiv.className = 'w-72 space-y-4';
                roundDiv.innerHTML = `<div class="bg-white/10 py-2 text-center font-black uppercase text-xs tracking-widest border-b-2 border-fuchsia-600">RONDA ${r}</div>`;
                
                rounds[r].forEach(m => {
                    const mDiv = document.createElement('div');
                    mDiv.className = `bracket-node p-3 rounded-lg ${m.status === 'done' ? 'opacity-80' : ''}`;
                    
                    const t1 = teams.find(te => te.id === m.team1_id);
                    const t2 = teams.find(te => te.id === m.team2_id);
                    
                    if (!t2) {
                         mDiv.innerHTML = `<div class="flex justify-between items-center text-xs font-bold">
                            <span>${t1.name}</span> <span class="text-fuchsia-500">BYE ✅</span>
                         </div>`;
                    } else {
                        mDiv.innerHTML = `
                            <div class="flex flex-col gap-2">
                                <div class="flex justify-between items-center text-sm font-bold ${m.status == 'done' && m.winner_id == m.team1_id ? 'text-fuchsia-400' : ''}">
                                    <div class="flex items-center gap-2">
                                        ${t1.logo ? `<img src="${t1.logo}" class="w-4 h-4 rounded-full"/>` : `<div class="w-4 h-4 bg-white/10 rounded-full"></div>`}
                                        <span class="truncate w-32">${t1.name}</span>
                                    </div>
                                    <span>${m.status === 'done' ? m.score1 : '-'}</span>
                                </div>
                                <div class="flex justify-between items-center text-sm font-bold ${m.status == 'done' && m.winner_id == m.team2_id ? 'text-fuchsia-400' : ''}">
                                    <div class="flex items-center gap-2">
                                        ${t2.logo ? `<img src="${t2.logo}" class="w-4 h-4 rounded-full"/>` : `<div class="w-4 h-4 bg-white/10 rounded-full"></div>`}
                                        <span class="truncate w-32">${t2.name}</span>
                                    </div>
                                    <span>${m.status === 'done' ? m.score2 : '-'}</span>
                                </div>
                            </div>
                        `;
                    }
                    roundDiv.appendChild(mDiv);
                });
                container.appendChild(roundDiv);
            });
        }

        function renderElimination(matches, teams, tournament) {
            const elimMatches = matches.filter(m => m.phase === 'elimination');
            
            // Determinar tamaño del bracket (2, 4, 8, 16...)
            let teamsCount = tournament.elimination_teams || 8;
            let totalRounds = Math.ceil(Math.log2(teamsCount));
            
            const rounds = {};
            for (let r = 1; r <= totalRounds; r++) {
                rounds[r] = [];
            }
            elimMatches.forEach(m => {
                if (rounds[m.round]) rounds[m.round].push(m);
            });

            const container = document.getElementById('elim-bracket');
            container.innerHTML = '';

            const sortedRounds = Object.keys(rounds).sort((a,b) => a-b);
            sortedRounds.forEach((r, idx) => {
                const roundDiv = document.createElement('div');
                roundDiv.className = 'flex flex-col justify-around gap-8';
                
                // Determinar etiqueta de ronda
                const roundsFromFinal = sortedRounds.length - idx;
                let label = roundsFromFinal === 1 ? 'GRAN FINAL' : (roundsFromFinal === 2 ? 'SEMIFINALES' : 'CUARTOS');
                if (roundsFromFinal > 3) label = `RONDA ${r}`;
                
                roundDiv.innerHTML = `<div class="text-[9px] font-black tracking-widest text-center text-gray-500 mb-2">${label}</div>`;

                const matchCount = Math.pow(2, sortedRounds.length - idx - 1);

                for (let i = 0; i < matchCount; i++) {
                    const m = rounds[r][i] || null;
                    const t1 = m ? teams.find(te => te.id === m.team1_id) : null;
                    const t2 = m ? teams.find(te => te.id === m.team2_id) : null;
                    const isDone = m ? m.status === 'done' : false;

                    const score1 = m ? m.score1 : 0;
                    const score2 = m ? m.score2 : 0;

                    const name1 = t1 ? t1.name : (r == 1 ? 'Por definir' : 'TBD');
                    const name2 = t2 ? t2.name : (r == 1 ? 'Por definir' : 'TBD');
                    
                    const mDiv = document.createElement('div');
                    mDiv.className = `bracket-node p-4 rounded-lg w-64 ${isDone ? 'winner' : ''}`;
                    
                    mDiv.innerHTML = `
                         <div class="flex flex-col gap-3">
                            <div class="flex justify-between items-center ${m && isDone && m.winner_id == m.team1_id ? 'text-fuchsia-400' : ''}">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-full overflow-hidden bg-white/5 flex items-center justify-center text-[10px] font-bold border border-white/10">
                                        ${t1?.logo ? `<img src="${t1.logo}" class="w-full h-full object-cover"/>` : `<span>${name1[0] || '?'}</span>`}
                                    </div>
                                    <span class="text-sm font-black uppercase tracking-tight truncate w-32">${name1}</span>
                                </div>
                                <span class="font-mono font-bold">${m ? score1 : '-'}</span>
                            </div>
                            <div class="flex justify-between items-center ${m && isDone && m.winner_id == m.team2_id ? 'text-fuchsia-400' : ''}">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-full overflow-hidden bg-white/5 flex items-center justify-center text-[10px] font-bold border border-white/10">
                                        ${t2?.logo ? `<img src="${t2.logo}" class="w-full h-full object-cover"/>` : `<span>${name2[0] || '?'}</span>`}
                                    </div>
                                    <span class="text-sm font-black uppercase tracking-tight truncate w-32">${name2}</span>
                                </div>
                                <span class="font-mono font-bold">${m ? score2 : '-'}</span>
                            </div>
                         </div>
                    `;
                    roundDiv.appendChild(mDiv);
                }
                container.appendChild(roundDiv);
            });
        }

        update();
        setInterval(update, 15000);
    </script>
</body>
</html>
