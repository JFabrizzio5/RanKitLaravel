<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $tournament->name }} — Widget OBS</title>
  <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:transparent; font-family:'Chakra Petch',sans-serif; color:#fff; overflow:hidden; }
    :root { --neon: #00e5ff; --bg: #07090f; --card: rgba(0,229,255,0.07); }
    body.lol     { --neon: #f0b429; }
    body.valorant{ --neon: #ff4655; }

    .widget { width:960px; min-height:400px; background:var(--bg); padding:18px; position:relative; }

    .header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; padding-bottom:10px; border-bottom:2px solid var(--neon); }
    .header-title { font-size:22px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--neon); text-shadow:0 0 12px var(--neon); }
    .header-phase { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.18em; color:rgba(255,255,255,.5); background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); padding:4px 10px; border-radius:4px; }

    /* Swiss */
    .swiss-grid { display:flex; gap:10px; flex-wrap:nowrap; overflow-x:auto; margin-bottom:14px; }
    .round-col  { flex:1; min-width:160px; }
    .round-label{ font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.2em; color:var(--neon); text-align:center; padding:4px 0 8px; border-bottom:1px solid rgba(255,255,255,.06); margin-bottom:8px; }
    .match-card { background:var(--card); border:1px solid rgba(255,255,255,.06); border-radius:6px; padding:8px 10px; margin-bottom:7px; position:relative; overflow:hidden; }
    .match-card::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%; background:var(--neon); opacity:.4; }
    .match-card.done::before { opacity:1; }
    .team-row   { display:flex; align-items:center; gap:7px; padding:3px 0; }
    .team-logo  { width:22px; height:22px; border-radius:50%; background:rgba(255,255,255,.1); flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; color:var(--neon); border:1px solid rgba(255,255,255,.1); overflow:hidden; }
    .team-logo img { width:100%; height:100%; object-fit:cover; }
    .team-name  { flex:1; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:rgba(255,255,255,.85); }
    .team-name.winner  { color:var(--neon); text-shadow:0 0 8px var(--neon); }
    .team-score { font-size:14px; font-weight:800; color:rgba(255,255,255,.6); min-width:16px; text-align:right; }
    .team-score.winner { color:var(--neon); }
    .match-vs   { text-align:center; font-size:8px; font-weight:700; color:rgba(255,255,255,.3); letter-spacing:.15em; margin:1px 0; }
    .match-bye  { font-size:10px; text-align:center; color:rgba(255,255,255,.3); padding:4px 0; }

    /* Elimination */
    .elim-sec { margin-bottom:14px; }
    .elim-sec-title { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.3); margin-bottom:10px; }
    .elim-container { display:flex; gap:10px; align-items:flex-start; }
    .elim-round { flex:1; display:flex; flex-direction:column; gap:8px; }
    .elim-round-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.2em; text-align:center; color:var(--neon); margin-bottom:6px; }

    /* Standings */
    .standings { margin-top:6px; }
    .standings-title { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.4); margin-bottom:8px; }
    .standings-row { display:flex; align-items:center; gap:10px; padding:5px 0; border-bottom:1px solid rgba(255,255,255,.04); }
    .standings-pos  { font-size:10px; font-weight:700; color:rgba(255,255,255,.3); min-width:18px; text-align:center; }
    .standings-pos.top { color:var(--neon); }
    .standings-name  { flex:1; font-size:11px; font-weight:700; text-transform:uppercase; }
    .standings-record{ font-size:10px; color:rgba(255,255,255,.5); }
    .standings-record span { color:var(--neon); }

    /* Champion */
    .champion-banner { text-align:center; padding:24px 0; }
    .champion-title  { font-size:13px; letter-spacing:.3em; text-transform:uppercase; color:rgba(255,255,255,.5); margin-bottom:8px; }
    .champion-name   { font-size:40px; font-weight:800; text-transform:uppercase; color:var(--neon); letter-spacing:.05em; animation:glow 2s ease-in-out infinite alternate; }
    @keyframes glow { from{text-shadow:0 0 20px var(--neon),0 0 40px var(--neon)} to{text-shadow:0 0 40px var(--neon),0 0 80px var(--neon),0 0 120px var(--neon)} }

    /* Rankit Branding */
    .rankit-footer { display:flex; align-items:center; justify-content:flex-end; gap:6px; margin-top:10px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.06); }
    .rankit-logo   { display:flex; align-items:center; gap:5px; }
    .rankit-logo-svg { width:16px; height:16px; }
    .rankit-wordmark { font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.7); }
    .rankit-wordmark span { color:var(--neon); }
    .powered-text  { font-size:8px; letter-spacing:.18em; text-transform:uppercase; color:rgba(255,255,255,.25); }
  </style>
</head>
<body class="{{ $tournament->game }}">

<div class="widget" id="widget-root"></div>

<script>
const TOURNAMENT_ID = {{ $tournament->id }};
const PHASE_FILTER  = '{{ $phase }}';

function teamLogo(team) {
  if (!team) return '';
  if (team.logo) return `<div class="team-logo"><img src="${team.logo}" alt="${team.name[0]}"/></div>`;
  return `<div class="team-logo">${team.name[0]}</div>`;
}

function renderMatch(m) {
  if (!m.team2) return `<div class="match-card done"><div class="team-row">${teamLogo(m.team1)}<div class="team-name winner">${m.team1?m.team1.name:'TBD'}</div></div><div class="match-bye">BYE ✓</div></div>`;
  const w = m.winner_id;
  const isDone = m.status === 'done';
  return `<div class="match-card ${isDone?'done':''}">
    <div class="team-row">${teamLogo(m.team1)}<div class="team-name${isDone && w==m.team1_id?' winner':''}">${m.team1?m.team1.name:'TBD'}</div><div class="team-score${isDone && w==m.team1_id?' winner':''}">${m.score1}</div></div>
    <div class="match-vs">VS</div>
    <div class="team-row">${teamLogo(m.team2)}<div class="team-name${isDone && w==m.team2_id?' winner':''}">${m.team2?m.team2.name:'TBD'}</div><div class="team-score${isDone && w==m.team2_id?' winner':''}">${m.score2}</div></div>
  </div>`;
}

function renderSwiss(matches) {
  const rounds = {};
  matches.filter(m=>m.phase==='swiss').forEach(m=>{if(!rounds[m.round])rounds[m.round]=[];rounds[m.round].push(m);});
  const keys = Object.keys(rounds).sort((a,b)=>+a-+b);
  if(!keys.length) return '';
  return `<div class="swiss-grid">${keys.map(r=>`<div class="round-col"><div class="round-label">Swiss R${r}</div>${rounds[r].map(renderMatch).join('')}</div>`).join('')}</div>`;
}

function roundName(r, keys) {
  const diff = keys.length - keys.indexOf(String(r));
  if(diff===1) return 'Gran Final';
  if(diff===2) return 'Semifinal';
  if(diff===3) return 'Cuartos';
  return `Ronda ${r}`;
}

function renderElim(matches) {
  const rounds = {};
  matches.filter(m=>m.phase==='elimination').forEach(m=>{if(!rounds[m.round])rounds[m.round]=[];rounds[m.round].push(m);});
  const keys = Object.keys(rounds).sort((a,b)=>+a-+b);
  if(!keys.length) return '';
  return `<div class="elim-sec"><div class="elim-sec-title">▶ Eliminación Directa</div><div class="elim-container">${keys.map(r=>`<div class="elim-round"><div class="elim-round-label">${roundName(+r,keys)}</div>${rounds[r].map(renderMatch).join('')}</div>`).join('')}</div></div>`;
}

function renderStandings(teams) {
  const sorted=[...teams].sort((a,b)=>b.wins-a.wins||a.losses-b.losses);
  return `<div class="standings"><div class="standings-title">Clasificación</div>${sorted.map((t,i)=>`<div class="standings-row"><div class="standings-pos${i<3?' top':''}">${i+1}</div>${teamLogo(t)}<div class="standings-name">${t.name}</div><div class="standings-record"><span>${t.wins}W</span> ${t.losses}L</div></div>`).join('')}</div>`;
}

function render(data) {
  const {tournament,teams,matches} = data;
  const phaseLabels = {pending:'Sin Iniciar',swiss:'Fase Suiza',elimination:'Eliminación',done:'🏆 Finalizado'};
  let html = `<div class="header"><div class="header-title">${tournament.name}</div><div class="header-phase">${phaseLabels[tournament.phase]||tournament.phase}</div></div>`;

  if (tournament.phase==='done') {
    const elimM=matches.filter(m=>m.phase==='elimination');
    const maxR=Math.max(...elimM.map(m=>m.round));
    const fin=elimM.find(m=>m.round===maxR);
    if(fin&&fin.winner) html+=`<div class="champion-banner"><div class="champion-title">🏆 Campeón</div><div class="champion-name">${fin.winner.name}</div></div>`;
  }

  const p = PHASE_FILTER||'all';
  if((p==='all'||p==='swiss') && tournament.format==='swiss_elimination') html+=renderSwiss(matches);
  if(p==='all'||p==='elimination') html+=renderElim(matches);
  if(p==='all'||p==='standings') html+=renderStandings(teams);

  document.getElementById('widget-root').innerHTML = html;

  // Branding footer
  let footer = document.getElementById('rankit-footer');
  if (!footer) {
    footer = document.createElement('div');
    footer.id = 'rankit-footer';
    footer.className = 'rankit-footer';
    footer.innerHTML = `
      <span class="powered-text">POWERED BY</span>
      <div class="rankit-logo">
        <svg class="rankit-logo-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M15 10 L40 10 L30 90 L5 90 Z" fill="white"/>
          <path d="M45 10 L95 10 L75 50 L45 50 Z" fill="white"/>
          <path d="M50 55 L80 55 L95 90 L65 90 Z" fill="var(--neon)"/>
        </svg>
        <span class="rankit-wordmark">RANKIT<span>.PRO</span></span>
      </div>
    `;
    document.getElementById('widget-root').after(footer);
  }
}

async function fetchData() {
  try {
    const res = await fetch(`/lol/${TOURNAMENT_ID}/widget-data`);
    const data = await res.json();
    render(data);
  } catch(e){ console.error(e); }
}

fetchData();
setInterval(fetchData, 15000); // auto-refresh 15s
</script>
</body>
</html>
