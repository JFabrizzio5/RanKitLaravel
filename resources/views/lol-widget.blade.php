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

        function buildSwiss(matches, teams) {
            const swissMatches = matches.filter(m => m.phase === 'swiss');
            if(swissMatches.length === 0) return '';

            const roundsData = {}; 
            swissMatches.forEach(m => {
                if (!roundsData[m.round]) roundsData[m.round] = {};
                let record = "0-0";
                if(m.team1_id) record = getTeamRecordBeforeRound(m.team1_id, m.round, swissMatches);
                if (!roundsData[m.round][record]) roundsData[m.round][record] = [];
                roundsData[m.round][record].push(m);
            });

            const sortedRounds = Object.keys(roundsData).sort((a, b) => a - b);
            let html = `<div class="w-full h-full flex flex-row items-center justify-center gap-6 overflow-x-auto">`;

            sortedRounds.forEach((roundKey, index) => {
                html += `<div class="flex flex-col justify-center gap-6 min-w-[150px] z-10">`;
                const recordsMap = roundsData[roundKey];
                
                const sortedRecords = Object.keys(recordsMap).sort((a, b) => {
                    const [wA, lA] = a.split('-').map(Number);
                    const [wB, lB] = b.split('-').map(Number);
                    if (wA !== wB) return wB - wA; 
                    return lA - lB; 
                });

                sortedRecords.forEach(recordStr => {
                    const matchGroup = recordsMap[recordStr];
                    
                    let badgeHTML = '';
                    if (recordStr === '2-0' && roundKey == 3) badgeHTML = '<div class="absolute -right-1 top-0 translate-x-full bg-white text-black text-[9px] font-black px-2 py-[2px] z-20 whitespace-nowrap tracking-wider">1ST, 2ND</div>';
                    if (recordStr === '2-1' && roundKey == 4) badgeHTML = '<div class="absolute -right-1 top-0 translate-x-full bg-white text-black text-[9px] font-black px-2 py-[2px] z-20 whitespace-nowrap tracking-wider">3RD, 4TH, 5TH</div>';
                    if (recordStr === '2-2' && roundKey == 5) badgeHTML = '<div class="absolute -right-1 top-0 translate-x-full bg-white text-black text-[9px] font-black px-2 py-[2px] z-20 whitespace-nowrap tracking-wider">6TH, 7TH, 8TH</div>';

                    const isBo3 = recordStr.includes('2');

                    html += `
                    <div class="flex flex-col w-full relative shadow-lg shadow-black/50">
                        ${badgeHTML}
                        <div class="bg-neon text-black font-black uppercase text-center py-1 text-sm tracking-widest">${recordStr}</div>
                        <div class="border border-neon py-2 px-3 flex flex-col gap-2 bg-black/85">`;

                    matchGroup.forEach(m => {
                        const t1 = teams.find(te => te.id === m.team1_id);
                        const t2 = teams.find(te => te.id === m.team2_id);
                        const isDone = m.status === 'done';

                        if (!t2) {
                            html += `<div class="flex items-center justify-between gap-3 w-40 opacity-70">
                                ${teamHex(t1)} <span class="text-neon font-bold text-xs mx-2">BYE</span> <div class="hex-logo opacity-0"></div>
                            </div>`;
                        } else {
                            html += `<div class="flex items-center justify-between gap-3 w-40 ${isDone ? 'opacity-50' : ''}">
                                ${teamHex(t1)}
                                <span class="text-neon font-black text-[10px] italic">VS</span>
                                ${teamHex(t2)}
                            </div>`;
                        }
                    });

                    html += `
                        </div>
                        <div class="text-neon font-black uppercase text-center text-[10px] tracking-widest mt-1">${isBo3 ? 'BEST OF 3' : 'BEST OF 1'}</div>
                    </div>`;
                });

                html += `</div>`;
                if (index < sortedRounds.length - 1) {
                    html += `<div class="w-4 lg:w-8 flex items-center justify-center"></div>`;
                }
            });

            html += `</div>`;
            return html;
        }

        function buildElimination(matches, teams) {
            const elimMatches = matches.filter(m => m.phase === 'elimination');
            if(elimMatches.length === 0) return '';

            const rounds = {};
            elimMatches.forEach(m => {
                if (!rounds[m.round]) rounds[m.round] = [];
                rounds[m.round].push(m);
            });

            const sortedRounds = Object.keys(rounds).sort((a,b) => b - a);
            const totalRounds = sortedRounds.length;
            
            let html = `<div class="w-full h-full flex flex-col items-center justify-end gap-0 pb-10">`;

            sortedRounds.forEach((r, index) => {
                const isFinal = index === 0;
                const matchCount = rounds[r].length;
                
                let roundName = 'CUARTOS DE FINAL';
                if (isFinal) roundName = 'LA FINAL';
                else if (index === 1 && totalRounds >= 3) roundName = 'SEMIFINALES';

                let rowGap = 'gap-12';
                if(matchCount === 2) rowGap = 'gap-64';
                if(matchCount === 4) rowGap = 'gap-8';

                html += `<div class="flex w-full justify-center ${rowGap} z-10 mb-0">`;

                rounds[r].forEach(m => {
                    const t1 = teams.find(te => te.id === m.team1_id);
                    const t2 = teams.find(te => te.id === m.team2_id);
                    const isDone = m.status === 'done';
                    const score1HTML = isDone ? `<div class="${m.winner_id == m.team1_id ? 'text-neon text-lg' : 'text-gray-500'} font-bold w-4 text-center">${m.score1}</div>` : '';
                    const score2HTML = isDone ? `<div class="${m.winner_id == m.team2_id ? 'text-neon text-lg' : 'text-gray-500'} font-bold w-4 text-center">${m.score2}</div>` : '';

                    html += `
                    <div class="flex flex-col items-center">
                        <div class="match-node ${isDone ? 'winner-node' : ''} w-52 flex flex-col items-center">
                            <div class="node-header w-full">AL MEJOR DE 5</div>
                            <div class="flex items-center justify-between gap-2 px-3 py-3 w-full backdrop-blur-md">
                                <div class="flex items-center gap-2">${teamHex(t1)} ${score1HTML}</div>
                                <div class="text-neon font-black text-lg italic drop-shadow-md">VS</div>
                                <div class="flex items-center gap-2">${score2HTML} ${teamHex(t2)}</div>
                            </div>
                        </div>
                        <div class="text-neon font-black uppercase text-center text-[10px] tracking-widest mt-1">${roundName}</div>
                    </div>`;
                });

                html += `</div>`;

                // Conectores (Líneas)
                if (index < totalRounds - 1) {
                    html += `<div class="flex w-full justify-center h-12 relative">`;
                    if (matchCount === 1) { 
                        html += `
                            <div class="w-2/3 max-w-[350px] flex flex-col items-center">
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
                                <div class="w-56 flex flex-col items-center">
                                    <div class="w-[2px] h-6 bg-neon"></div>
                                    <div class="w-full h-[2px] bg-neon"></div>
                                    <div class="w-full flex justify-between">
                                        <div class="w-[2px] h-6 bg-neon"></div>
                                        <div class="w-[2px] h-6 bg-neon"></div>
                                    </div>
                                </div>
                                <div class="w-56 flex flex-col items-center">
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
                contentHTML += buildSwiss(matches, teams);
            } 
            else if (p === 'elimination' || (p === 'all' && (tournament.phase === 'elimination' || tournament.phase === 'done'))) {
                contentHTML += buildElimination(matches, teams);
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