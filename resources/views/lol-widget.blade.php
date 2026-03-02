<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tournament->name }} — Widget OBS</title>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:ital,wght@0,400;0,600;0,700;1,700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { 
            --neon: #00e5ff; /* Default fallback */
            --bg-dark: #050505; 
            --box-bg: rgba(0, 0, 0, 0.85); 
        }
        body.lol      { --neon: #f0b429; }
        body.valorant { --neon: #ff4655; }

        body { 
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(255, 255, 255, 0.03), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(255, 255, 255, 0.02), transparent 25%);
            color: white; 
            font-family: 'Chakra Petch', sans-serif;
            overflow: hidden; 
        }

        /* Utilidades dinámicas de color para Tailwind */
        .text-neon { color: var(--neon); }
        .bg-neon { background-color: var(--neon); }
        .border-neon { border-color: var(--neon); }
        .shadow-neon { box-shadow: 0 0 15px var(--neon); }

        /* Marquesina superior */
        .marquee-container {
            background-color: var(--neon);
            color: black;
            font-weight: 800;
            font-size: 1.2rem;
            white-space: nowrap;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            display: flex;
            align-items: center;
        }

        /* Nodos (Partidos) */
        .match-node {
            background: var(--box-bg);
            border: 2px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 10;
        }
        .match-node.winner-node {
            border-color: var(--neon);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
        }

        .node-header {
            background-color: var(--neon);
            color: black;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            padding: 2px 0;
        }

        /* Hexágonos para logos */
        .hex-logo {
            width: 32px;
            height: 32px;
            clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255,255,255,0.2);
            flex-shrink: 0;
        }
        .hex-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1.1);
        }

        /* Branding RanKit */
        .rankit-footer { position: fixed; bottom: 15px; right: 20px; display:flex; align-items:center; gap:6px; z-index: 50; }
        .rankit-logo   { display:flex; align-items:center; gap:5px; }
        .rankit-logo-svg { width:16px; height:16px; }
        .rankit-wordmark { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.7); }
        .rankit-wordmark span { color:var(--neon); }
        .powered-text  { font-size:8px; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.25); }

        /* Scrollbar oculta */
        ::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="{{ $tournament->game }} w-screen h-screen flex flex-col">

    <div class="marquee-container py-1 shadow-lg" id="top-marquee"></div>

    <div class="flex-1 flex flex-col items-center justify-center p-8 relative w-full h-full overflow-hidden" id="widget-root">
        </div>

    <div class="rankit-footer" id="rankit-footer">
        <span class="powered-text">POWERED BY</span>
        <div class="rankit-logo">
            <svg class="rankit-logo-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white"/>
                <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white"/>
                <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--neon)"/>
            </svg>
            <span class="rankit-wordmark">RANKIT<span>.PRO</span></span>
        </div>
    </div>

    <script>
        const TOURNAMENT_ID = {{ $tournament->id }};
        const PHASE_FILTER  = '{{ $phase }}'; 

        async function fetchData() {
            try {
                const res = await fetch(`/lol/${TOURNAMENT_ID}/widget-data`);
                const data = await res.json();
                render(data);
            } catch(e) { console.error("Error cargando datos:", e); }
        }

        function teamHex(team) {
            if (!team) return `<div class="hex-logo text-xs font-bold text-gray-500">?</div>`;
            if (team.logo) return `<div class="hex-logo"><img src="${team.logo}" alt="logo"></div>`;
            return `<div class="hex-logo text-xs font-bold text-white">${team.name.substring(0, 2).toUpperCase()}</div>`;
        }

        // Lógica para historial (W-L) de Suizo
        function getTeamRecordBeforeRound(teamId, targetRound, allMatches) {
            let wins = 0; let losses = 0;
            allMatches.forEach(m => {
                if (m.round < targetRound && m.status === 'done' && m.phase === 'swiss') {
                    if (m.team1_id === teamId || m.team2_id === teamId) {
                        if (m.winner_id === teamId) wins++;
                        else if (m.winner_id !== null) losses++;
                    }
                }
            });
            return `${wins}-${losses}`;
        }

        // ====== BUILDERS DE COMPONENTES ======

        function buildSwiss(matches, teams, tournament) {
            const swissMatches = matches.filter(m => m.phase === 'swiss');
            
            // Configuración
            const tCount = tournament.teams_count || teams.length || 16;
            const w = tournament.swiss_wins_to_advance || 3;
            const l = tournament.swiss_losses_to_eliminate || 3;
            let totalSwissRounds = tournament.swiss_rounds_total || (w + l - 1);

            // 1. Generar Estructura Teórica
            // teamsPerRecord[round][recordStr] = numero de equipos que entran a ese pool
            const teamsPerRecord = {};
            for (let r = 1; r <= totalSwissRounds; r++) teamsPerRecord[r] = {};
            
            teamsPerRecord[1]["0-0"] = tCount;

            for (let r = 1; r < totalSwissRounds; r++) {
                for (const record in teamsPerRecord[r]) {
                    const count = teamsPerRecord[r][record];
                    const [win, loss] = record.split('-').map(Number);
                    
                    // Ganadores -> win + 1
                    if (win + 1 < w) {
                        const nextRecord = `${win + 1}-${loss}`;
                        teamsPerRecord[r+1][nextRecord] = (teamsPerRecord[r+1][nextRecord] || 0) + (count / 2);
                    }
                    // Perdedores -> loss + 1
                    if (loss + 1 < l) {
                        const nextRecord = `${win}-${loss + 1}`;
                        teamsPerRecord[r+1][nextRecord] = (teamsPerRecord[r+1][nextRecord] || 0) + (count / 2);
                    }
                }
            }

            // 2. Mapear Partidas Reales
            const realData = {}; // realData[round][recordStr] = [matches]
            swissMatches.forEach(m => {
                if (!realData[m.round]) realData[m.round] = {};
                let rec = "0-0";
                if (m.team1_id) rec = getTeamRecordBeforeRound(m.team1_id, m.round, swissMatches);
                if (!realData[m.round][rec]) realData[m.round][rec] = [];
                realData[m.round][rec].push(m);
            });

            // 3. Renderizar
            let html = `<div class="w-full h-full flex flex-row items-center justify-center gap-10 overflow-x-auto px-10">`;

            for (let r = 1; r <= totalSwissRounds; r++) {
                html += `<div class="flex flex-col justify-center gap-10 min-w-[200px] z-10 py-10">`;
                
                // Obtener records posibles para esta ronda, ordenados (2-0 > 1-1 > 0-2)
                const records = Object.keys(teamsPerRecord[r]).sort((a,b) => {
                    const [wA] = a.split('-').map(Number);
                    const [wB] = b.split('-').map(Number);
                    return wB - wA;
                });

                records.forEach(recordStr => {
                    const expectedMatches = Math.ceil(teamsPerRecord[r][recordStr] / 2);
                    const actualMatches = (realData[r] && realData[r][recordStr]) || [];
                    
                    const isBo3 = recordStr.includes(`${w-1}`); // Bo3 si están a 1 de ganar o perder
                    const isElimDecider = recordStr.includes(`${l-1}`);
                    const isDecider = isBo3 || isElimDecider;

                    html += `
                    <div class="flex flex-col w-full relative shadow-2xl shadow-black/80">
                        <div class="${isDecider ? 'bg-white text-black' : 'bg-neon text-black'} font-black uppercase text-center py-1 text-xs tracking-widest">${recordStr}</div>
                        <div class="border ${isDecider ? 'border-white' : 'border-neon'} py-3 px-4 flex flex-col gap-3 bg-black/95">`;

                    for (let i = 0; i < expectedMatches; i++) {
                        const m = actualMatches[i] || null;
                        const t1 = m ? teams.find(te => te.id === m.team1_id) : null;
                        const t2 = m ? teams.find(te => te.id === m.team2_id) : null;
                        const isDone = m ? m.status === 'done' : false;

                        if (m && !m.team2_id) {
                            html += `<div class="flex items-center justify-between gap-3 w-48 opacity-70">
                                ${teamHex(t1)} <span class="text-neon font-black text-[10px] mx-2 tracking-tighter">BYE</span> <div class="hex-logo opacity-0"></div>
                            </div>`;
                        } else {
                            const s1 = isDone ? m.score1 : (m ? '0' : '-');
                            const s2 = isDone ? m.score2 : (m ? '0' : '-');

                            html += `<div class="flex items-center justify-between gap-3 w-48 ${!m ? 'opacity-20' : ''} ${isDone ? 'opacity-60' : ''}">
                                <div class="flex items-center gap-2 flex-1 overflow-hidden">
                                    ${teamHex(t1)}
                                    <span class="truncate text-[10px] font-black uppercase ${isDone && m.winner_id == m.team1_id ? 'text-neon' : 'text-white'}">${t1 ? t1.name : (r === 1 ? 'POR DEFINIR' : 'TBD')}</span>
                                </div>
                                <div class="flex items-center gap-1 font-mono text-[10px] font-black min-w-[30px] justify-center">
                                    <span class="${isDone && m.winner_id == m.team1_id ? 'text-neon text-[12px]' : ''}">${s1}</span>
                                    <span class="text-neon/30">:</span>
                                    <span class="${isDone && m.winner_id == m.team2_id ? 'text-neon text-[12px]' : ''}">${s2}</span>
                                </div>
                                <div class="flex items-center gap-2 flex-1 justify-end overflow-hidden">
                                    <span class="truncate text-[10px] font-black uppercase text-right ${isDone && m.winner_id == m.team2_id ? 'text-neon' : 'text-white'}">${t2 ? t2.name : (r === 1 ? 'POR DEFINIR' : 'TBD')}</span>
                                    ${teamHex(t2)}
                                </div>
                            </div>`;
                        }
                    }

                    html += `
                        </div>
                        <div class="${isDecider ? 'text-white' : 'text-neon'} font-black uppercase text-center text-[9px] tracking-widest mt-1 opacity-60">${isDecider ? 'BEST OF 3' : 'BEST OF 1'}</div>
                    </div>`;
                });

                html += `</div>`;
            }

            // Ganadores / Eliminados Finales
            const advanced = teams.filter(t => t.swiss_status === 'advanced').sort((a,b) => (a.swiss_wins || 0) - (b.swiss_wins || 0));
            const eliminated = teams.filter(t => t.swiss_status === 'eliminated');

            if (advanced.length > 0 || eliminated.length > 0) {
                 html += `<div class="flex flex-col justify-center gap-6 min-w-[200px] z-10 py-10">`;

                 if (advanced.length > 0) {
                     html += `
                    <div class="flex flex-col w-full relative shadow-2xl shadow-black/80">
                        <div class="bg-green-500 text-black font-black uppercase text-center py-1 text-xs tracking-widest">CLASIFICADOS</div>
                        <div class="border border-green-500 py-3 px-4 flex flex-col gap-2 bg-black/95">`;
                    advanced.forEach(t => {
                        html += `<div class="flex items-center gap-3 w-48 py-1">
                            ${teamHex(t)} <span class="truncate text-[11px] font-black text-white uppercase tracking-tight">${t.name}</span>
                            <span class="text-[9px] font-black text-green-500 ml-auto">${t.swiss_wins || w}-${t.swiss_losses || 0}</span>
                        </div>`;
                    });
                    html += `</div></div>`;
                 }

                 if (eliminated.length > 0) {
                      html += `
                    <div class="flex flex-col w-full relative shadow-2xl shadow-black/80 opacity-40">
                        <div class="bg-red-600 text-black font-black uppercase text-center py-1 text-xs tracking-widest">ELIMINADOS</div>
                        <div class="border border-red-600 py-3 px-4 flex flex-col gap-2 bg-black/95">`;
                    eliminated.forEach(t => {
                         html += `<div class="flex items-center gap-3 w-48 py-1">
                            ${teamHex(t)} <span class="truncate text-[10px] font-bold text-gray-500 uppercase">${t.name}</span>
                        </div>`;
                    });
                    html += `</div></div>`;
                 }

                 html += `</div>`;
            }

            html += `</div>`;
            return html;
        }

        function buildElimination(matches, teams, tournament) {
            const elimMatches = matches.filter(m => m.phase === 'elimination');
            
            // Determinar tamaño del bracket (2, 4, 8, 16...)
            let teamsCount = tournament.elimination_teams || 8;
            let totalRounds = Math.ceil(Math.log2(teamsCount));
            
            const rounds = {};
            // Inicializar todas las rondas posibles según el tamaño del torneo
            for (let r = 1; r <= totalRounds; r++) {
                rounds[r] = [];
            }
            
            // Llenar con partidas existentes
            elimMatches.forEach(m => {
                if (rounds[m.round]) rounds[m.round].push(m);
            });

            // Ordenar rondas de mayor a menor (Final arriba)
            const sortedRounds = Object.keys(rounds).sort((a,b) => b - a);
            
            let html = `<div class="w-full h-full flex flex-col items-center justify-end gap-0 pb-10">`;

            sortedRounds.forEach((r, index) => {
                const isFinal = index === 0;
                const matchCount = Math.pow(2, index); // Partidas teóricas por ronda (1, 2, 4, 8...)
                
                let roundName = 'CUARTOS DE FINAL';
                if (isFinal) roundName = 'LA FINAL';
                else if (index === 1 && sortedRounds.length >= 2) roundName = 'SEMIFINALES';
                else if (index === 2 && sortedRounds.length >= 3) roundName = 'CUARTOS DE FINAL';
                else roundName = `RONDA ${r}`;

                let rowGap = 'gap-12';
                if(matchCount === 2) rowGap = 'gap-48';
                if(matchCount === 4) rowGap = 'gap-8';

                html += `<div class="flex w-full justify-center ${rowGap} z-10 mb-0">`;

                // Renderizar partidas de esta ronda
                for (let i = 0; i < matchCount; i++) {
                    const m = rounds[r][i] || null;
                    const t1 = m ? teams.find(te => te.id === m.team1_id) : null;
                    const t2 = m ? teams.find(te => te.id === m.team2_id) : null;
                    const isDone = m ? m.status === 'done' : false;
                    
                    // Mostrar scores si existen (o 0 si la partida está en curso/pendiente)
                    const score1 = m ? m.score1 : 0;
                    const score2 = m ? m.score2 : 0;
                    
                    const score1HTML = `<div class="${m && isDone && m.winner_id == m.team1_id ? 'text-neon text-lg' : 'text-gray-500'} font-bold w-4 text-center">${m ? score1 : '-'}</div>`;
                    const score2HTML = `<div class="${m && isDone && m.winner_id == m.team2_id ? 'text-neon text-lg' : 'text-gray-500'} font-bold w-4 text-center">${m ? score2 : '-'}</div>`;

                    const t1Name = t1 ? t1.name : (r == 1 ? 'Por definir' : 'TBD');
                    const t2Name = t2 ? t2.name : (r == 1 ? 'Por definir' : 'TBD');

                    html += `
                    <div class="flex flex-col items-center">
                        <div class="match-node ${isDone ? 'winner-node' : ''} w-52 flex flex-col items-center">
                            <div class="node-header w-full">AL MEJOR DE 5</div>
                            <div class="flex items-center justify-between gap-2 px-3 py-3 w-full backdrop-blur-md">
                                <div class="flex items-center gap-2 flex-1 overflow-hidden">
                                    ${teamHex(t1)} 
                                    <span class="truncate text-xs font-bold ${m && isDone && m.winner_id == m.team1_id ? 'text-neon' : 'text-white'}">${t1Name}</span>
                                    ${score1HTML}
                                </div>
                                <div class="text-neon font-black text-sm italic drop-shadow-md px-1">VS</div>
                                <div class="flex items-center gap-2 flex-1 justify-end overflow-hidden">
                                    ${score2HTML} 
                                    <span class="truncate text-xs font-bold ${m && isDone && m.winner_id == m.team2_id ? 'text-neon' : 'text-white'}">${t2Name}</span>
                                    ${teamHex(t2)}
                                </div>
                            </div>
                        </div>
                        <div class="text-neon font-black uppercase text-center text-[10px] tracking-widest mt-1">${roundName}</div>
                    </div>`;
                }

                html += `</div>`;

                // Conectores (Líneas) - Ajustados para ser dinámicos
                if (index < sortedRounds.length - 1) {
                    html += `<div class="flex w-full justify-center h-12 relative">`;
                    if (matchCount === 1) { 
                        html += `
                            <div class="w-1/2 max-w-[300px] flex flex-col items-center">
                                <div class="w-[2px] h-6 bg-neon"></div>
                                <div class="w-full h-[2px] bg-neon"></div>
                                <div class="w-full flex justify-between">
                                    <div class="w-[2px] h-6 bg-neon"></div>
                                    <div class="w-[2px] h-6 bg-neon"></div>
                                </div>
                            </div>`;
                    } else if (matchCount === 2) { 
                        html += `
                            <div class="flex w-full justify-center gap-[12rem] h-12 relative">
                                <div class="w-48 flex flex-col items-center">
                                    <div class="w-[2px] h-6 bg-neon"></div>
                                    <div class="w-full h-[2px] bg-neon"></div>
                                    <div class="w-full flex justify-between">
                                        <div class="w-[2px] h-6 bg-neon"></div>
                                        <div class="w-[2px] h-6 bg-neon"></div>
                                    </div>
                                </div>
                                <div class="w-48 flex flex-col items-center">
                                    <div class="w-[2px] h-6 bg-neon"></div>
                                    <div class="w-full h-[2px] bg-neon"></div>
                                    <div class="w-full flex justify-between">
                                        <div class="w-[2px] h-6 bg-neon"></div>
                                        <div class="w-[2px] h-6 bg-neon"></div>
                                    </div>
                                </div>
                            </div>`;
                    }
                    html += `</div>`;
                }
            });

            html += `</div>`;
            return html;
        }

        function buildStandings(teams) {
            const sorted = [...teams].sort((a,b) => b.wins - a.wins || a.losses - b.losses);
            let html = `
            <div class="w-full max-w-xl mx-auto flex flex-col h-full justify-center">
                <div class="bg-black/80 border border-neon p-6 shadow-neon">
                    <h2 class="text-neon text-xl font-black uppercase tracking-widest text-center mb-6">Clasificación Global</h2>
                    <div class="flex flex-col gap-3">`;
            
            sorted.forEach((t, i) => {
                const isTop = i < 3;
                html += `
                <div class="flex items-center gap-4 py-2 border-b border-white/10 last:border-0">
                    <div class="${isTop ? 'text-neon text-xl' : 'text-white/50 text-md'} font-black w-6 text-center">${i+1}</div>
                    ${teamHex(t)}
                    <div class="flex-1 font-bold text-sm uppercase tracking-wider ${isTop ? 'text-white' : 'text-white/80'}">${t.name}</div>
                    <div class="font-bold text-xs bg-white/5 px-3 py-1 rounded">
                        <span class="text-neon">${t.wins}W</span> - <span class="text-white/50">${t.losses}L</span>
                    </div>
                </div>`;
            });

            html += `</div></div></div>`;
            return html;
        }

        // ====== FUNCIÓN DE RENDER PRINCIPAL ======
        function render(data) {
            const { tournament, teams, matches } = data;
            const root = document.getElementById('widget-root');
            const p = PHASE_FILTER || 'all';

            // 1. Configurar Marquesina Superior
            const phaseLabels = { pending:'Sin Iniciar', swiss:'Fase Suiza', elimination:'Eliminatoria', done:'Torneo Finalizado' };
            const label = phaseLabels[tournament.phase] || tournament.phase;
            document.getElementById('top-marquee').innerHTML = (`${tournament.name} • ${label} • `).repeat(10);

            let contentHTML = '';

            // 2. Banner de Campeón (Si ya terminó)
            if (tournament.phase === 'done') {
                const elimM = matches.filter(m => m.phase === 'elimination');
                const maxR = Math.max(...elimM.map(m => m.round));
                const fin = elimM.find(m => m.round === maxR);
                if (fin && fin.winner_id) {
                    const champ = teams.find(t => t.id === fin.winner_id);
                    contentHTML += `
                    <div class="absolute top-10 left-1/2 -translate-x-1/2 z-50 text-center bg-black/90 border-2 border-neon px-12 py-4 shadow-neon">
                        <div class="text-xs tracking-[0.3em] uppercase text-white/70 mb-2">🏆 Campeón del Torneo</div>
                        <div class="text-4xl font-black uppercase text-neon tracking-widest drop-shadow-md">${champ ? champ.name : 'TBD'}</div>
                    </div>`;
                }
            }

            // 3. Renderizar Fase Correspondiente
            if ((p === 'all' || p === 'swiss') && tournament.format === 'swiss_elimination' && tournament.phase !== 'done') {
                contentHTML += buildSwiss(matches, teams, tournament);
            } 
            else if (p === 'elimination' || (p === 'all' && (tournament.phase === 'elimination' || tournament.phase === 'done'))) {
                contentHTML += buildElimination(matches, teams, tournament);
            }
            else if (p === 'standings') {
                contentHTML += buildStandings(teams);
            }

            root.innerHTML = contentHTML;
        }

        fetchData();
        setInterval(fetchData, 15000); // auto-refresh 15s
    </script>
</body>
</html>