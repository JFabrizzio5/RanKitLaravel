<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

/* ==========================================================================
   MUNDIAL 2026 — Resumen del torneo (página pública /mundial)

   TODO se deriva en tiempo de ejecución desde la API pública worldcup26.ir.
   No hay campeón, marcador ni equipo quemado en el código: si la API no
   devuelve una final terminada, la página degrada a "torneo en curso".

   Ojo con la API: casi todos los campos llegan como string y los nulos
   llegan como la cadena "null". Por eso todo pasa por txt() / num().
   ========================================================================== */

const API = 'https://worldcup26.ir/get';

const MESES = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
];

/* Mapa y ORDEN oficial de fases. El bug anterior era agrupar por m.type
   crudo: salían "r32"/"qf" y en desorden. */
const FASES = [
    { clave: 'group', nombre: 'Fase de Grupos', corto: 'Grupos' },
    { clave: 'r32', nombre: 'Dieciseisavos de Final', corto: '16avos' },
    { clave: 'r16', nombre: 'Octavos de Final', corto: 'Octavos' },
    { clave: 'qf', nombre: 'Cuartos de Final', corto: 'Cuartos' },
    { clave: 'sf', nombre: 'Semifinales', corto: 'Semis' },
    { clave: 'third', nombre: 'Tercer Lugar', corto: '3er Lugar' },
    { clave: 'final', nombre: 'Final', corto: 'Final' },
];

/* Rondas que forman el bracket "Camino al Título" (el 3er lugar va aparte). */
const RONDAS_BRACKET = ['r32', 'r16', 'qf', 'sf', 'final'];

const PESTANAS = [
    { clave: 'ruta', nombre: 'Camino al Título', icono: 'ph-tree-structure' },
    { clave: 'grupos', nombre: 'Fase de Grupos', icono: 'ph-list-numbers' },
    { clave: 'partidos', nombre: 'Partidos', icono: 'ph-soccer-ball' },
    { clave: 'stats', nombre: 'Estadísticas', icono: 'ph-chart-bar' },
    { clave: 'equipos', nombre: 'Equipos', icono: 'ph-flag-banner' },
];

/* ------------------------------ estado ---------------------------------- */
const cargando = ref(true);
const error = ref('');
const gruposApi = ref([]);
const juegosApi = ref([]);
const equiposApi = ref([]);
const estadiosApi = ref([]);

const pestana = ref('ruta');
const busqueda = ref('');
const faseFiltro = ref('todas');
const jornadaActiva = ref('1');

/* --------------------------- normalizadores ------------------------------ */
function txt(valor) {
    if (valor === null || valor === undefined) return '';
    const cadena = String(valor).trim();
    if (!cadena) return '';
    const bajo = cadena.toLowerCase();
    if (bajo === 'null' || bajo === 'undefined' || bajo === 'nan') return '';
    return cadena;
}

function num(valor) {
    const cadena = txt(valor);
    if (cadena === '') return null;
    const n = Number(cadena);
    return Number.isFinite(n) ? n : null;
}

function sinAcentos(cadena) {
    return String(cadena).normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

/* Clave de comparación: minúsculas, sin acentos y sin puntuación. */
function clave(cadena) {
    return sinAcentos(txt(cadena))
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/* La API manda "MM/DD/YYYY HH:mm": new Date() no es confiable con eso,
   así que se parsea a mano. */
function parseFecha(crudo) {
    const cadena = txt(crudo);
    const partes = cadena.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:[\sT]+(\d{1,2}):(\d{2}))?/);
    if (!partes) return null;

    const mes = Number(partes[1]);
    const dia = Number(partes[2]);
    const anio = Number(partes[3]);
    if (mes < 1 || mes > 12 || dia < 1 || dia > 31) return null;

    const hora = partes[4] !== undefined ? Number(partes[4]) : null;
    const minuto = partes[5] !== undefined ? Number(partes[5]) : null;
    const dosDigitos = (n) => String(n).padStart(2, '0');

    return {
        dia,
        mes,
        anio,
        texto: `${dia} de ${MESES[mes - 1]} de ${anio}`,
        textoCorto: `${dosDigitos(dia)}/${dosDigitos(mes)}/${anio}`,
        hora: hora === null ? '' : `${dosDigitos(hora)}:${dosDigitos(minuto === null ? 0 : minuto)}`,
        orden: anio * 100000000 + mes * 1000000 + dia * 10000 + (hora === null ? 0 : hora) * 100 + (minuto === null ? 0 : minuto),
    };
}

/* Goleadores: la API manda {"H. Kane 12'(p)","J. Bellingham 47'"} o "null". */
function parseGoleadores(crudo) {
    const cadena = txt(crudo);
    if (!cadena) return [];

    let cuerpo = cadena;
    if (cuerpo.startsWith('{') && cuerpo.endsWith('}')) cuerpo = cuerpo.slice(1, -1);

    const brutos = [];
    const comillas = /"([^"]*)"/g;
    let encontrado = comillas.exec(cuerpo);
    while (encontrado !== null) {
        brutos.push(encontrado[1]);
        encontrado = comillas.exec(cuerpo);
    }
    if (!brutos.length) {
        cuerpo.split(',').forEach((parte) => {
            const limpio = parte.trim();
            if (limpio) brutos.push(limpio);
        });
    }

    return brutos
        .map((entrada) => {
            const original = txt(entrada);
            if (!original) return null;

            const anotaciones = (original.match(/\([^)]*\)/g) || []).join(' ').toLowerCase();
            const autogol = anotaciones.includes('og');
            const penal = !autogol && /\(\s*p\s*\)/.test(anotaciones);

            // "90+4'", "45'+5'" o "12'"
            const minutos = original.match(/(\d{1,3})\s*'?\s*(?:\+\s*(\d{1,2}))?\s*'/);
            const minuto = minutos ? (minutos[2] ? `${minutos[1]}+${minutos[2]}` : minutos[1]) : '';

            const nombre = original
                .replace(/\([^)]*\)/g, ' ')
                .replace(/\d[\s\S]*$/, ' ')
                .replace(/[\s,.'’-]+$/, '')
                .replace(/\s+/g, ' ')
                .trim();

            if (!nombre) return null;
            return {
                nombre,
                minuto,
                minutoTexto: minuto ? `${minuto}'` : '',
                penal,
                autogol,
            };
        })
        .filter(Boolean);
}

/* Distancia de edición acotada (para fusionar variantes de transliteración). */
function distancia(a, b) {
    if (a === b) return 0;
    if (Math.abs(a.length - b.length) > 2) return 99;
    const fila = [];
    for (let j = 0; j <= b.length; j += 1) fila[j] = j;
    for (let i = 1; i <= a.length; i += 1) {
        let diagonal = fila[0];
        fila[0] = i;
        for (let j = 1; j <= b.length; j += 1) {
            const previo = fila[j];
            fila[j] = Math.min(fila[j] + 1, fila[j - 1] + 1, diagonal + (a[i - 1] === b[j - 1] ? 0 : 1));
            diagonal = previo;
        }
    }
    return fila[b.length];
}

/* ------------------------------ carga ----------------------------------- */
async function pedirJson(ruta) {
    const respuesta = await fetch(`${API}/${ruta}`, { headers: { Accept: 'application/json' } });
    if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);
    return await respuesta.json();
}

function listaDe(resultado, llave) {
    if (!resultado || resultado.status !== 'fulfilled') return [];
    const dato = resultado.value;
    if (Array.isArray(dato)) return dato;
    if (dato && Array.isArray(dato[llave])) return dato[llave];
    return [];
}

async function cargarDatos() {
    cargando.value = true;
    error.value = '';
    try {
        const [rGrupos, rJuegos, rEquipos, rEstadios] = await Promise.allSettled([
            pedirJson('groups'),
            pedirJson('games'),
            pedirJson('teams'),
            pedirJson('stadiums'),
        ]);

        gruposApi.value = listaDe(rGrupos, 'groups');
        juegosApi.value = listaDe(rJuegos, 'games');
        equiposApi.value = listaDe(rEquipos, 'teams');
        estadiosApi.value = listaDe(rEstadios, 'stadiums');

        if (!juegosApi.value.length && !equiposApi.value.length && !gruposApi.value.length) {
            throw new Error('respuesta vacía');
        }
    } catch (e) {
        error.value = 'No pudimos obtener los datos del Mundial 2026. Revisa tu conexión e inténtalo de nuevo.';
        gruposApi.value = [];
        juegosApi.value = [];
        equiposApi.value = [];
        estadiosApi.value = [];
    } finally {
        cargando.value = false;
    }
}

/* ------------------------ derivados: catálogos --------------------------- */
const equipos = computed(() =>
    equiposApi.value.map((t) => {
        const nombre = txt(t.name_en) || txt(t.name) || 'Por definir';
        return {
            id: txt(t.id),
            nombre,
            bandera: txt(t.flag),
            codigo: txt(t.fifa_code) || sinAcentos(nombre).slice(0, 3).toUpperCase(),
            grupo: txt(t.groups),
            buscar: `${clave(nombre)} ${clave(t.fifa_code)}`,
        };
    })
);

const equiposPorId = computed(() => {
    const mapa = {};
    equipos.value.forEach((e) => {
        if (e.id) mapa[e.id] = e;
    });
    return mapa;
});

const estadiosPorId = computed(() => {
    const mapa = {};
    estadiosApi.value.forEach((s) => {
        const id = txt(s.id);
        if (!id) return;
        const nombre = txt(s.name_en) || txt(s.fifa_name) || 'Sede por confirmar';
        const ciudad = [txt(s.city_en), txt(s.country_en)].filter(Boolean).join(', ');
        mapa[id] = {
            id,
            nombre,
            alterno: txt(s.fifa_name),
            ciudad,
            capacidad: num(s.capacity),
            texto: ciudad ? `${nombre} · ${ciudad}` : nombre,
        };
    });
    return mapa;
});

function fase(claveFase) {
    return FASES.find((f) => f.clave === claveFase) || null;
}

function nombreFase(claveFase) {
    const encontrada = fase(claveFase);
    return encontrada ? encontrada.nombre : (txt(claveFase).toUpperCase() || 'Otros Partidos');
}

function ordenFase(claveFase) {
    const indice = FASES.findIndex((f) => f.clave === claveFase);
    return indice === -1 ? 99 : indice;
}

function ladoEquipo(idCrudo, nombreCrudo, etiquetaCruda) {
    const id = txt(idCrudo);
    const base = equiposPorId.value[id] || null;
    const etiqueta = txt(etiquetaCruda);
    const nombre = txt(nombreCrudo) || (base ? base.nombre : '') || etiqueta || 'Por definir';
    return {
        id,
        nombre,
        etiqueta,
        bandera: base ? base.bandera : '',
        codigo: base ? base.codigo : sinAcentos(nombre).slice(0, 3).toUpperCase(),
    };
}

/* ------------------------ derivados: partidos ---------------------------- */
const partidos = computed(() => {
    const lista = juegosApi.value.map((g) => {
        const tipo = txt(g.type) || 'otros';
        const fechaObj = parseFecha(g.local_date);
        const local = ladoEquipo(g.home_team_id, g.home_team_name_en, g.home_team_label);
        const visita = ladoEquipo(g.away_team_id, g.away_team_name_en, g.away_team_label);

        const golesLocal = num(g.home_score);
        const golesVisita = num(g.away_score);
        const penalesLocal = num(g.home_penalty_score);
        const penalesVisita = num(g.away_penalty_score);
        const hayPenales = penalesLocal !== null && penalesVisita !== null;

        const terminado = txt(g.finished).toUpperCase() === 'TRUE'
            || txt(g.time_elapsed).toLowerCase() === 'finished';
        const marcadorValido = golesLocal !== null && golesVisita !== null;

        let ganador = null;
        let ganadorPorPenales = false;
        if (terminado && marcadorValido) {
            if (golesLocal > golesVisita) ganador = 'local';
            else if (golesVisita > golesLocal) ganador = 'visita';
            else if (hayPenales && penalesLocal !== penalesVisita) {
                ganador = penalesLocal > penalesVisita ? 'local' : 'visita';
                ganadorPorPenales = true;
            } else ganador = 'empate';
        }

        const grupo = txt(g.group);
        const jornada = txt(g.matchday);
        const estadio = estadiosPorId.value[txt(g.stadium_id)] || null;

        return {
            id: txt(g.id) || txt(g._id),
            ordenId: num(g.id) || 0,
            tipo,
            faseNombre: nombreFase(tipo),
            faseCorto: fase(tipo) ? fase(tipo).corto : nombreFase(tipo),
            ordenFase: ordenFase(tipo),
            grupo,
            jornada,
            contexto: tipo === 'group'
                ? `Grupo ${grupo || '—'}${jornada ? ` · Jornada ${jornada}` : ''}`
                : nombreFase(tipo),
            fecha: fechaObj,
            fechaTexto: fechaObj ? fechaObj.texto : 'Fecha por confirmar',
            horaTexto: fechaObj ? fechaObj.hora : '',
            ordenFecha: fechaObj ? fechaObj.orden : 0,
            estadio,
            local,
            visita,
            golesLocal,
            golesVisita,
            penalesLocal,
            penalesVisita,
            hayPenales,
            terminado,
            marcadorValido,
            ganador,
            ganadorPorPenales,
            golesLocalLista: parseGoleadores(g.home_scorers),
            golesVisitaLista: parseGoleadores(g.away_scorers),
            buscar: `${clave(local.nombre)} ${clave(visita.nombre)} ${clave(local.codigo)} ${clave(visita.codigo)}`,
        };
    });

    return lista.sort(
        (a, b) => a.ordenFase - b.ordenFase || a.ordenFecha - b.ordenFecha || a.ordenId - b.ordenId
    );
});

const partidosJugados = computed(() => partidos.value.filter((p) => p.terminado && p.marcadorValido));

function equipoDelLado(partido, lado) {
    if (!partido || !lado) return null;
    return lado === 'local' ? partido.local : partido.visita;
}

/* ------------------------ derivados: campeón ----------------------------- */
const partidoFinal = computed(
    () => partidos.value.find((p) => p.tipo === 'final' && p.terminado && p.marcadorValido) || null
);

const partidoTercerPuesto = computed(
    () => partidos.value.find((p) => p.tipo === 'third' && p.terminado && p.marcadorValido) || null
);

const torneoFinalizado = computed(
    () => !!partidoFinal.value && !!partidoFinal.value.ganador && partidoFinal.value.ganador !== 'empate'
);

const campeon = computed(() =>
    torneoFinalizado.value ? equipoDelLado(partidoFinal.value, partidoFinal.value.ganador) : null
);

const subcampeon = computed(() =>
    torneoFinalizado.value
        ? equipoDelLado(partidoFinal.value, partidoFinal.value.ganador === 'local' ? 'visita' : 'local')
        : null
);

const tercerLugar = computed(() => {
    const p = partidoTercerPuesto.value;
    if (!p || !p.ganador || p.ganador === 'empate') return null;
    return equipoDelLado(p, p.ganador);
});

const cuartoLugar = computed(() => {
    const p = partidoTercerPuesto.value;
    if (!p || !p.ganador || p.ganador === 'empate') return null;
    return equipoDelLado(p, p.ganador === 'local' ? 'visita' : 'local');
});

const podio = computed(() => {
    const lista = [];
    if (campeon.value) {
        lista.push({
            puesto: '1º', etiqueta: 'Campeón', equipo: campeon.value,
            clases: 'border-fnGold/60 shadow-glowGold', texto: 'text-fnGold',
        });
    }
    if (subcampeon.value) {
        lista.push({
            puesto: '2º', etiqueta: 'Subcampeón', equipo: subcampeon.value,
            clases: 'border-gray-400/40', texto: 'text-gray-200',
        });
    }
    if (tercerLugar.value) {
        lista.push({
            puesto: '3º', etiqueta: 'Tercer lugar', equipo: tercerLugar.value,
            clases: 'border-amber-700/50', texto: 'text-amber-600',
        });
    }
    if (cuartoLugar.value) {
        lista.push({
            puesto: '4º', etiqueta: 'Cuarto lugar', equipo: cuartoLugar.value,
            clases: 'border-fuchsia-900/40', texto: 'text-gray-400',
        });
    }
    return lista;
});

/* --------------------------- derivados: KPIs ----------------------------- */
const kpis = computed(() => {
    const jugados = partidosJugados.value;
    const goles = jugados.reduce((suma, p) => suma + p.golesLocal + p.golesVisita, 0);
    const promedio = jugados.length ? goles / jugados.length : 0;
    return {
        partidos: jugados.length,
        goles,
        promedio: jugados.length ? promedio.toFixed(2).replace('.', ',') : '—',
        tandas: jugados.filter((p) => p.hayPenales).length,
        equipos: equipos.value.length,
        sedes: estadiosApi.value.length,
    };
});

const recordGoleada = computed(() => {
    let mejor = null;
    partidosJugados.value.forEach((p) => {
        const diferencia = Math.abs(p.golesLocal - p.golesVisita);
        if (!mejor || diferencia > mejor.diferencia) mejor = { partido: p, diferencia };
    });
    return mejor;
});

const recordGoleado = computed(() => {
    let mejor = null;
    partidosJugados.value.forEach((p) => {
        const total = p.golesLocal + p.golesVisita;
        if (!mejor || total > mejor.total) mejor = { partido: p, total };
    });
    return mejor;
});

const partidosPorPenales = computed(() => partidosJugados.value.filter((p) => p.hayPenales));

/* ---------------------- derivados: goles por equipo ---------------------- */
const rendimientoEquipos = computed(() => {
    const mapa = new Map();
    const acumula = (equipo, favor, contra) => {
        if (!equipo || (!equipo.id && !equipo.nombre)) return;
        const llave = equipo.id || equipo.nombre;
        const actual = mapa.get(llave) || { equipo, pj: 0, gf: 0, gc: 0 };
        actual.pj += 1;
        actual.gf += favor;
        actual.gc += contra;
        if (!actual.equipo.bandera && equipo.bandera) actual.equipo = equipo;
        mapa.set(llave, actual);
    };
    partidosJugados.value.forEach((p) => {
        acumula(p.local, p.golesLocal, p.golesVisita);
        acumula(p.visita, p.golesVisita, p.golesLocal);
    });
    return [...mapa.values()];
});

const mejorAtaque = computed(() =>
    [...rendimientoEquipos.value].sort((a, b) => b.gf - a.gf || a.gc - b.gc).slice(0, 8)
);

const mejorDefensa = computed(() =>
    [...rendimientoEquipos.value]
        .filter((e) => e.pj >= 3)
        .sort((a, b) => a.gc / a.pj - b.gc / b.pj || b.pj - a.pj)
        .slice(0, 8)
);

const maxGolesEquipo = computed(() =>
    rendimientoEquipos.value.reduce((max, e) => Math.max(max, e.gf), 0)
);

/* -------------------- derivados: tabla de goleadores --------------------- */
/* Los nombres llegan transliterados desde la API (a veces "H. Kane" y a veces
   "Hri Kin"). Se agrupan por apellido normalizado y se fusionan variantes con
   distancia de edición <= 2, conservando la grafía más frecuente. */
const goleadores = computed(() => {
    const crudos = new Map();

    partidos.value.forEach((p) => {
        const lados = [
            { lista: p.golesLocalLista, equipo: p.local },
            { lista: p.golesVisitaLista, equipo: p.visita },
        ];
        lados.forEach(({ lista, equipo }) => {
            lista.forEach((gol) => {
                if (gol.autogol) return; // los autogoles no cuentan para el goleador
                const registro = crudos.get(gol.nombre) || {
                    nombre: gol.nombre,
                    goles: 0,
                    penales: 0,
                    equipos: new Map(),
                };
                registro.goles += 1;
                if (gol.penal) registro.penales += 1;
                const llaveEquipo = equipo.id || equipo.nombre;
                const acumEquipo = registro.equipos.get(llaveEquipo) || { equipo, veces: 0 };
                acumEquipo.veces += 1;
                registro.equipos.set(llaveEquipo, acumEquipo);
                crudos.set(gol.nombre, registro);
            });
        });
    });

    const entradas = [...crudos.values()]
        .map((registro) => {
            const partes = clave(registro.nombre).split(' ').filter((t) => t.length > 1);
            const equipoTop = [...registro.equipos.values()].sort((a, b) => b.veces - a.veces)[0];
            return {
                nombre: registro.nombre,
                goles: registro.goles,
                penales: registro.penales,
                equipo: equipoTop ? equipoTop.equipo : null,
                apellido: partes.length ? partes[partes.length - 1] : clave(registro.nombre),
                pila: partes.length > 1 ? partes[0] : '',
                variantes: [registro.nombre],
            };
        })
        .sort((a, b) => b.goles - a.goles || a.nombre.localeCompare(b.nombre));

    const compatible = (a, b) => {
        if (a.pila.length >= 3 && b.pila.length >= 3) return distancia(a.pila, b.pila) <= 1;
        if (!a.pila || !b.pila) return true;
        return a.pila[0] === b.pila[0];
    };

    const grupos = [];
    entradas.forEach((entrada) => {
        const mismoEquipo = (g) => {
            const idA = g.equipo ? g.equipo.id || g.equipo.nombre : '';
            const idB = entrada.equipo ? entrada.equipo.id || entrada.equipo.nombre : '';
            return idA === idB;
        };
        const destino = grupos.find((g) => {
            if (!mismoEquipo(g) || !compatible(g, entrada)) return false;
            const d = distancia(g.apellido, entrada.apellido);
            if (d === 0) return true;
            return (
                d <= 2
                && g.apellido.charAt(0) === entrada.apellido.charAt(0)
                && Math.min(g.apellido.length, entrada.apellido.length) >= 3
            );
        });
        if (destino) {
            destino.goles += entrada.goles;
            destino.penales += entrada.penales;
            destino.variantes.push(entrada.nombre);
        } else {
            grupos.push({ ...entrada });
        }
    });

    return grupos
        .map((g) => ({ ...g, nombre: mejorGrafia(g.variantes) }))
        .sort((a, b) => b.goles - a.goles || a.nombre.localeCompare(b.nombre))
        .slice(0, 12);
});

/* De todas las variantes de un mismo jugador, elige la grafía más completa:
   la API mezcla transliteraciones ("Jvd Blingham") con la forma correcta
   ("J. Bellingham"). Gana el apellido más largo; a igualdad, el nombre de pila
   completo (no inicial) y luego la cadena más larga. */
function mejorGrafia(variantes) {
    const lista = (variantes || []).filter(Boolean);
    if (!lista.length) return '';

    const puntuar = (nombre) => {
        const tokens = String(nombre).trim().split(/\s+/).filter(Boolean);
        const apellido = tokens.length ? tokens[tokens.length - 1] : '';
        const pila = tokens.length > 1 ? tokens[0] : '';
        const pilaCompleta = pila && !pila.endsWith('.') && pila.length > 2 ? 1 : 0;
        return [apellido.length, pilaCompleta, String(nombre).length];
    };

    return lista.reduce((mejor, actual) => {
        const a = puntuar(actual);
        const b = puntuar(mejor);
        for (let i = 0; i < a.length; i++) {
            if (a[i] !== b[i]) return a[i] > b[i] ? actual : mejor;
        }
        return mejor;
    }, lista[0]);
}

const maxGolesJugador = computed(() =>
    goleadores.value.reduce((max, g) => Math.max(max, g.goles), 0)
);

/* ---------------------- derivados: fase de grupos ------------------------ */
const tablasGrupos = computed(() =>
    gruposApi.value
        .map((g) => ({
            nombre: txt(g.name) || txt(g.letter) || '?',
            equipos: (g.teams || [])
                .map((t) => {
                    const info = equiposPorId.value[txt(t.team_id)] || null;
                    const gf = num(t.gf) || 0;
                    const ga = num(t.ga) || 0;
                    const gd = t.gd !== undefined && num(t.gd) !== null ? num(t.gd) : gf - ga;
                    return {
                        id: txt(t.team_id) || txt(t._id),
                        nombre: info ? info.nombre : 'Por definir',
                        bandera: info ? info.bandera : '',
                        codigo: info ? info.codigo : '',
                        pj: num(t.mp) || 0,
                        g: num(t.w) || 0,
                        e: num(t.d) || 0,
                        p: num(t.l) || 0,
                        gf,
                        gc: ga,
                        dif: gd,
                        pts: num(t.pts) || 0,
                    };
                })
                .sort((a, b) => b.pts - a.pts || b.dif - a.dif || b.gf - a.gf),
        }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre))
);

const jornadasGrupos = computed(() => {
    const mapa = new Map();
    partidos.value
        .filter((p) => p.tipo === 'group')
        .forEach((p) => {
            const llave = p.jornada || '?';
            if (!mapa.has(llave)) mapa.set(llave, []);
            mapa.get(llave).push(p);
        });
    return [...mapa.entries()]
        .map(([numero, lista]) => ({
            numero,
            nombre: numero === '?' ? 'Jornada por confirmar' : `Jornada ${numero}`,
            partidos: lista.sort((a, b) => a.ordenFecha - b.ordenFecha || a.ordenId - b.ordenId),
        }))
        .sort((a, b) => (Number(a.numero) || 99) - (Number(b.numero) || 99));
});

const jornadaSeleccionada = computed(() => {
    const lista = jornadasGrupos.value;
    if (!lista.length) return null;
    return lista.find((j) => j.numero === jornadaActiva.value) || lista[0];
});

/* --------------------------- bracket (Camino) ---------------------------- */
function feedersDe(partido, mapaPorId) {
    const etiquetas = [partido.local.etiqueta, partido.visita.etiqueta];
    return etiquetas
        .map((etiqueta) => {
            const encontrado = txt(etiqueta).match(/match\s*#?\s*(\d+)/i);
            return encontrado ? mapaPorId.get(encontrado[1]) : null;
        })
        .filter(Boolean);
}

const bracket = computed(() => {
    const porTipo = {};
    RONDAS_BRACKET.forEach((r) => {
        porTipo[r] = partidos.value
            .filter((p) => p.tipo === r)
            .sort((a, b) => a.ordenId - b.ordenId || a.ordenFecha - b.ordenFecha);
    });

    const disponibles = RONDAS_BRACKET.filter((r) => porTipo[r].length);
    if (!disponibles.length) return [];

    const mapaPorId = new Map();
    partidos.value.forEach((p) => {
        if (p.id) mapaPorId.set(String(p.id), p);
    });

    // Intento 1: ordenar por linaje ("Winner Match 101") para que el bracket
    // quede alineado de verdad, no como lista suelta.
    const derivadas = [];
    let actual = porTipo[disponibles[disponibles.length - 1]];
    derivadas.unshift(actual);
    let coherente = true;

    for (let i = disponibles.length - 2; i >= 0; i -= 1) {
        const claveRonda = disponibles[i];
        const previa = [];
        actual.forEach((p) => {
            feedersDe(p, mapaPorId).forEach((origen) => {
                if (origen.tipo === claveRonda && !previa.includes(origen)) previa.push(origen);
            });
        });
        if (previa.length !== porTipo[claveRonda].length) {
            coherente = false;
            break;
        }
        derivadas.unshift(previa);
        actual = previa;
    }

    const rondas = coherente && derivadas.length === disponibles.length
        ? derivadas
        : disponibles.map((r) => porTipo[r]);

    return rondas.map((lista, indice) => ({
        clave: disponibles[indice],
        nombre: nombreFase(disponibles[indice]),
        corto: fase(disponibles[indice]) ? fase(disponibles[indice]).corto : disponibles[indice],
        partidos: lista,
    }));
});

/* ------------------------------- filtros --------------------------------- */
const consulta = computed(() => clave(busqueda.value));

function coincideBusqueda(partido) {
    if (!consulta.value) return true;
    return partido.buscar.includes(consulta.value);
}

const partidosFiltrados = computed(() =>
    partidos.value.filter(
        (p) => (faseFiltro.value === 'todas' || p.tipo === faseFiltro.value) && coincideBusqueda(p)
    )
);

const fasesFiltradas = computed(() => {
    const mapa = new Map();
    partidosFiltrados.value.forEach((p) => {
        if (!mapa.has(p.tipo)) mapa.set(p.tipo, []);
        mapa.get(p.tipo).push(p);
    });
    return [...mapa.entries()]
        .map(([tipo, lista]) => ({
            tipo,
            nombre: nombreFase(tipo),
            orden: ordenFase(tipo),
            partidos: lista,
        }))
        .sort((a, b) => a.orden - b.orden);
});

const fasesDisponibles = computed(() => {
    const presentes = new Set(partidos.value.map((p) => p.tipo));
    return FASES.filter((f) => presentes.has(f.clave));
});

const equiposPorGrupo = computed(() => {
    const mapa = new Map();
    equipos.value
        .filter((e) => !consulta.value || e.buscar.includes(consulta.value))
        .forEach((e) => {
            const llave = e.grupo ? `Grupo ${e.grupo}` : 'Sin grupo asignado';
            if (!mapa.has(llave)) mapa.set(llave, []);
            mapa.get(llave).push(e);
        });
    return [...mapa.entries()]
        .map(([nombre, lista]) => ({
            nombre,
            equipos: lista.sort((a, b) => a.nombre.localeCompare(b.nombre)),
        }))
        .sort((a, b) => a.nombre.localeCompare(b.nombre));
});

function resaltado(partido) {
    return !!consulta.value && partido.buscar.includes(consulta.value);
}

function limpiarFiltros() {
    busqueda.value = '';
    faseFiltro.value = 'todas';
}

function marcadorTexto(partido) {
    if (!partido || !partido.marcadorValido) return 'POR JUGAR';
    return `${partido.golesLocal} - ${partido.golesVisita}`;
}

/* -------------------------------- montaje -------------------------------- */
onMounted(() => {
    cargarDatos();

    // Iconos Phosphor por CDN (mismo patrón que Inicio.vue)
    if (!document.querySelector('script[src="https://unpkg.com/@phosphor-icons/web"]')) {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/@phosphor-icons/web';
        script.async = true;
        document.head.appendChild(script);
    }
});
</script>

<template>
    <Head title="Mundial 2026 | Campeón, Bracket y Estadísticas" />

    <div class="min-h-screen bg-[#10031c] text-gray-300 font-sans selection:bg-fuchsia-500/30">
        <!-- ============================ HEADER ============================ -->
        <header class="sticky top-0 z-40 border-b border-fuchsia-900/50 bg-[#1a082c]/85 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-fnGold/40 bg-[#10031c] text-2xl text-fnGold shadow-glowGold">
                        <i class="ph-fill ph-trophy"></i>
                    </div>
                    <div>
                        <h1 class="font-display text-2xl font-bold uppercase leading-none tracking-tight text-white sm:text-3xl">
                            Mundial <span class="text-neon">2026</span>
                        </h1>
                        <p class="mt-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em] text-fuchsia-400 sm:text-xs">
                            <span v-if="torneoFinalizado" class="rounded border border-fnGold/50 bg-fnGold/10 px-2 py-0.5 text-fnGold">Torneo finalizado</span>
                            <span v-else-if="cargando" class="text-gray-500">Cargando datos…</span>
                            <span v-else class="text-gray-400">Resumen del torneo</span>
                        </p>
                    </div>
                </div>

                <nav class="flex items-center gap-4 text-sm font-bold">
                    <Link href="/" class="text-gray-400 transition-colors hover:text-white">Inicio</Link>
                    <span class="h-4 w-px bg-fuchsia-900/60"></span>
                    <Link href="/semanales" class="text-gray-400 transition-colors hover:text-white">Semanales</Link>
                    <span class="h-4 w-px bg-fuchsia-900/60"></span>
                    <Link href="/dashboard" class="text-gray-400 transition-colors hover:text-white">Dashboard</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
            <!-- =========================== SKELETONS =========================== -->
            <div v-if="cargando" class="space-y-8">
                <div class="h-64 animate-pulse rounded-3xl border border-fuchsia-900/30 bg-[#1a082c] sm:h-72"></div>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <div v-for="n in 6" :key="'kpi-skel-' + n" class="h-24 animate-pulse rounded-2xl border border-fuchsia-900/30 bg-[#1a082c]"></div>
                </div>
                <div class="h-12 animate-pulse rounded-xl border border-fuchsia-900/30 bg-[#1a082c]"></div>
                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div v-for="n in 6" :key="'card-skel-' + n" class="h-36 animate-pulse rounded-2xl border border-fuchsia-900/30 bg-[#1a082c]"></div>
                </div>
                <p class="text-center text-xs font-bold uppercase tracking-[0.3em] text-fuchsia-500/70">
                    Cargando datos del Mundial 2026
                </p>
            </div>

            <!-- ============================= ERROR ============================= -->
            <div v-else-if="error" class="mx-auto max-w-xl rounded-3xl border border-fnCrimson/40 bg-[#1a082c] p-10 text-center shadow-glowCrimson">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full border border-fnCrimson/50 bg-fnCrimson/10 text-3xl text-fnCrimson">
                    <i class="ph-fill ph-warning-octagon"></i>
                </div>
                <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-white">Sin conexión con la API</h2>
                <p class="mx-auto mt-3 max-w-md text-sm text-gray-400">{{ error }}</p>
                <button
                    type="button"
                    class="btn-skew mt-7 px-8 py-3 text-sm font-bold uppercase tracking-widest"
                    @click="cargarDatos"
                >
                    <span class="btn-content flex items-center gap-2">
                        <i class="ph-bold ph-arrow-clockwise"></i> Reintentar
                    </span>
                </button>
            </div>

            <!-- ============================ CONTENIDO ========================== -->
            <div v-else class="space-y-10">
                <!-- ---------------------- HERO CAMPEÓN ---------------------- -->
                <section
                    v-if="torneoFinalizado && campeon && subcampeon && partidoFinal"
                    class="relative overflow-hidden rounded-3xl border border-fnGold/30 bg-gradient-to-br from-[#1f0d36] via-[#1a082c] to-[#10031c] p-6 shadow-glowGold sm:p-10"
                >
                    <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-fnGold/10 blur-3xl"></div>
                    <div class="pointer-events-none absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-fuchsia-600/10 blur-3xl"></div>

                    <div class="relative z-10 grid gap-10 lg:grid-cols-[1.15fr_1fr] lg:items-center">
                        <!-- Campeón -->
                        <div>
                            <p class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.3em] text-fnGold">
                                <i class="ph-fill ph-confetti"></i> Campeón del Mundo
                            </p>

                            <div class="mt-5 flex flex-wrap items-center gap-5">
                                <img
                                    v-if="campeon.bandera"
                                    :src="campeon.bandera"
                                    :alt="'Bandera de ' + campeon.nombre"
                                    class="h-20 w-28 rounded-lg border border-fnGold/40 object-cover shadow-glowGold sm:h-24 sm:w-36"
                                />
                                <div v-else class="flex h-20 w-28 items-center justify-center rounded-lg border border-fnGold/40 bg-[#10031c] text-3xl text-fnGold sm:h-24 sm:w-36">
                                    <i class="ph-fill ph-flag"></i>
                                </div>
                                <div>
                                    <h2 class="font-display text-4xl font-bold uppercase leading-none tracking-tight text-white sm:text-6xl">
                                        {{ campeon.nombre }}
                                    </h2>
                                    <p class="mt-2 text-xs font-bold uppercase tracking-[0.25em] text-fnGold">
                                        {{ campeon.codigo }} · Copa del Mundo 2026
                                    </p>
                                </div>
                            </div>

                            <!-- Marcador de la final -->
                            <div class="mt-7 rounded-2xl border border-fuchsia-900/40 bg-[#10031c]/70 p-4 sm:p-5">
                                <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-fuchsia-400">La Final</p>
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 flex-1 items-center gap-2">
                                        <img v-if="partidoFinal.local.bandera" :src="partidoFinal.local.bandera" :alt="partidoFinal.local.nombre" class="h-6 w-9 rounded object-cover" />
                                        <span
                                            class="truncate font-display text-sm font-bold uppercase sm:text-base"
                                            :class="partidoFinal.ganador === 'local' ? 'text-fnGold' : 'text-gray-400'"
                                        >{{ partidoFinal.local.nombre }}</span>
                                    </div>
                                    <div class="shrink-0 text-center">
                                        <div class="font-display text-2xl font-bold text-white sm:text-3xl">
                                            {{ marcadorTexto(partidoFinal) }}
                                        </div>
                                        <div v-if="partidoFinal.hayPenales" class="text-[10px] font-bold uppercase tracking-widest text-fnGold">
                                            ({{ partidoFinal.penalesLocal }}-{{ partidoFinal.penalesVisita }} pen.)
                                        </div>
                                    </div>
                                    <div class="flex min-w-0 flex-1 items-center justify-end gap-2">
                                        <span
                                            class="truncate text-right font-display text-sm font-bold uppercase sm:text-base"
                                            :class="partidoFinal.ganador === 'visita' ? 'text-fnGold' : 'text-gray-400'"
                                        >{{ partidoFinal.visita.nombre }}</span>
                                        <img v-if="partidoFinal.visita.bandera" :src="partidoFinal.visita.bandera" :alt="partidoFinal.visita.nombre" class="h-6 w-9 rounded object-cover" />
                                    </div>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 border-t border-fuchsia-900/30 pt-3 text-[11px] text-gray-400">
                                    <span class="flex items-center gap-1.5">
                                        <i class="ph ph-calendar-blank text-fuchsia-500"></i>
                                        {{ partidoFinal.fechaTexto }}<template v-if="partidoFinal.horaTexto"> · {{ partidoFinal.horaTexto }}</template>
                                    </span>
                                    <span class="flex items-center gap-1.5">
                                        <i class="ph ph-map-pin text-fuchsia-500"></i>
                                        {{ partidoFinal.estadio ? partidoFinal.estadio.texto : 'Sede por confirmar' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Podio -->
                        <div>
                            <p class="mb-4 flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.3em] text-gray-400">
                                <i class="ph-fill ph-medal"></i> Podio final
                            </p>
                            <div class="space-y-3">
                                <div
                                    v-for="lugar in podio"
                                    :key="'podio-' + lugar.puesto"
                                    class="flex items-center gap-4 rounded-2xl border bg-[#10031c]/70 px-4 py-3"
                                    :class="lugar.clases"
                                >
                                    <span class="font-display text-2xl font-bold" :class="lugar.texto">{{ lugar.puesto }}</span>
                                    <img
                                        v-if="lugar.equipo.bandera"
                                        :src="lugar.equipo.bandera"
                                        :alt="lugar.equipo.nombre"
                                        class="h-8 w-12 rounded object-cover"
                                    />
                                    <div class="min-w-0">
                                        <div class="truncate font-display text-base font-bold uppercase text-white">{{ lugar.equipo.nombre }}</div>
                                        <div class="text-[10px] font-bold uppercase tracking-[0.2em]" :class="lugar.texto">{{ lugar.etiqueta }}</div>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!partidoTercerPuesto" class="mt-3 text-[11px] text-gray-500">
                                El partido por el tercer lugar aún no aparece en la API: 3.º y 4.º quedan POR ANUNCIAR.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Degradación elegante si la API no trae final terminada -->
                <section v-else class="rounded-3xl border border-fuchsia-900/40 bg-[#1a082c] p-8 text-center sm:p-12">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-fuchsia-700/50 bg-[#10031c] text-2xl text-fuchsia-400">
                        <i class="ph-fill ph-hourglass-medium"></i>
                    </div>
                    <h2 class="font-display text-2xl font-bold uppercase tracking-wide text-white sm:text-3xl">
                        {{ partidos.length ? 'Torneo en curso' : 'Sin datos disponibles' }}
                    </h2>
                    <p class="mx-auto mt-3 max-w-lg text-sm text-gray-400">
                        <template v-if="partidos.length">
                            La API todavía no reporta una final terminada. El campeón se mostrará aquí en cuanto exista el
                            resultado: por ahora, <span class="text-fuchsia-400">PRÓXIMAMENTE</span>.
                        </template>
                        <template v-else>
                            No hay partidos publicados por el proveedor de datos en este momento.
                        </template>
                    </p>
                </section>

                <!-- --------------------------- KPIs --------------------------- -->
                <section class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6 lg:gap-4">
                    <div class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-4">
                        <i class="ph-fill ph-soccer-ball text-xl text-fuchsia-500"></i>
                        <div class="mt-2 font-display text-2xl font-bold text-white">{{ kpis.partidos }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Partidos jugados</div>
                    </div>
                    <div class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-4">
                        <i class="ph-fill ph-target text-xl text-fnMagenta"></i>
                        <div class="mt-2 font-display text-2xl font-bold text-white">{{ kpis.goles }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Goles totales</div>
                    </div>
                    <div class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-4">
                        <i class="ph-fill ph-chart-line-up text-xl text-fnNeonCyan"></i>
                        <div class="mt-2 font-display text-2xl font-bold text-white">{{ kpis.promedio }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Goles por partido</div>
                    </div>
                    <div class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-4">
                        <i class="ph-fill ph-crosshair text-xl text-fnGold"></i>
                        <div class="mt-2 font-display text-2xl font-bold text-white">{{ kpis.tandas }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Series por penales</div>
                    </div>
                    <div class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-4">
                        <i class="ph-fill ph-flag-banner text-xl text-fnEmerald"></i>
                        <div class="mt-2 font-display text-2xl font-bold text-white">{{ kpis.equipos }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Selecciones</div>
                    </div>
                    <div class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-4">
                        <i class="ph-fill ph-buildings text-xl text-fnBrightPurple"></i>
                        <div class="mt-2 font-display text-2xl font-bold text-white">{{ kpis.sedes || '—' }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Sedes</div>
                    </div>
                </section>

                <!-- -------------------------- PESTAÑAS ------------------------ -->
                <nav class="flex gap-2 overflow-x-auto border-b border-fuchsia-900/40 pb-px">
                    <button
                        v-for="tab in PESTANAS"
                        :key="tab.clave"
                        type="button"
                        class="flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-xs font-bold uppercase tracking-widest transition-colors sm:text-sm"
                        :class="pestana === tab.clave
                            ? 'border-fuchsia-500 bg-fuchsia-900/10 text-fuchsia-300'
                            : 'border-transparent text-gray-500 hover:text-gray-300'"
                        @click="pestana = tab.clave"
                    >
                        <i class="ph-fill" :class="tab.icono"></i>{{ tab.nombre }}
                    </button>
                </nav>

                <!-- ---------------------- FILTROS (partidos/equipos) --------- -->
                <div
                    v-if="pestana === 'partidos' || pestana === 'equipos' || pestana === 'ruta'"
                    class="flex flex-col gap-4 rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] px-5 py-4 sm:flex-row sm:items-end"
                >
                    <label class="flex-1">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500">Buscar equipo</span>
                        <div class="flex items-center gap-2">
                            <i class="ph ph-magnifying-glass text-fuchsia-500"></i>
                            <input
                                v-model="busqueda"
                                type="search"
                                placeholder="Ej. Argentina, MEX, Marruecos…"
                                class="brutal-input text-sm"
                            />
                        </div>
                    </label>

                    <label v-if="pestana === 'partidos'" class="sm:w-64">
                        <span class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500">Fase</span>
                        <select
                            v-model="faseFiltro"
                            class="w-full rounded-lg border border-fuchsia-900/50 bg-[#10031c] px-3 py-2 text-sm font-bold text-gray-200 focus:border-fuchsia-500 focus:ring-0"
                        >
                            <option value="todas">Todas las fases</option>
                            <option v-for="f in fasesDisponibles" :key="f.clave" :value="f.clave">{{ f.nombre }}</option>
                        </select>
                    </label>

                    <button
                        v-if="busqueda || faseFiltro !== 'todas'"
                        type="button"
                        class="rounded-lg border border-fuchsia-900/50 px-4 py-2 text-xs font-bold uppercase tracking-widest text-gray-400 transition-colors hover:border-fuchsia-500 hover:text-white"
                        @click="limpiarFiltros"
                    >
                        Limpiar
                    </button>
                </div>

                <!-- ==================== PESTAÑA: CAMINO AL TÍTULO ============= -->
                <section v-if="pestana === 'ruta'" class="space-y-6">
                    <div class="flex items-center gap-4">
                        <h3 class="font-display text-xl font-bold uppercase tracking-wide text-white">Camino al Título</h3>
                        <div class="h-px flex-1 bg-fuchsia-900/40"></div>
                        <span class="hidden text-[10px] font-bold uppercase tracking-widest text-gray-500 sm:block">Desliza para ver todas las rondas →</span>
                    </div>

                    <div v-if="bracket.length" class="overflow-x-auto pb-4">
                        <div class="flex min-w-max items-stretch gap-4">
                            <div v-for="ronda in bracket" :key="'ronda-' + ronda.clave" class="flex w-[240px] shrink-0 flex-col">
                                <div
                                    class="mb-3 rounded-lg border px-3 py-2 text-center text-[10px] font-bold uppercase tracking-widest"
                                    :class="ronda.clave === 'final'
                                        ? 'border-fnGold/50 bg-fnGold/10 text-fnGold'
                                        : 'border-fuchsia-900/40 bg-[#1a082c] text-fuchsia-400'"
                                >
                                    {{ ronda.nombre }}
                                </div>
                                <div class="flex flex-1 flex-col justify-around gap-2">
                                    <div
                                        v-for="p in ronda.partidos"
                                        :key="'bracket-' + p.id"
                                        class="rounded-xl border bg-[#1a082c] px-3 py-2 transition-colors"
                                        :class="[
                                            p.tipo === 'final' ? 'border-fnGold/50 shadow-glowGold' : 'border-fuchsia-900/40',
                                            resaltado(p) ? 'ring-1 ring-fnNeonCyan' : '',
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <img v-if="p.local.bandera" :src="p.local.bandera" :alt="p.local.nombre" class="h-4 w-6 shrink-0 rounded-sm object-cover" />
                                                <span
                                                    class="truncate text-xs font-bold"
                                                    :class="p.ganador === 'local' ? 'text-fnGold' : 'text-gray-500'"
                                                >{{ p.local.nombre }}</span>
                                            </div>
                                            <span class="font-display text-sm font-bold" :class="p.ganador === 'local' ? 'text-white' : 'text-gray-500'">
                                                {{ p.marcadorValido ? p.golesLocal : '–' }}
                                            </span>
                                        </div>
                                        <div class="mt-1.5 flex items-center justify-between gap-2">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <img v-if="p.visita.bandera" :src="p.visita.bandera" :alt="p.visita.nombre" class="h-4 w-6 shrink-0 rounded-sm object-cover" />
                                                <span
                                                    class="truncate text-xs font-bold"
                                                    :class="p.ganador === 'visita' ? 'text-fnGold' : 'text-gray-500'"
                                                >{{ p.visita.nombre }}</span>
                                            </div>
                                            <span class="font-display text-sm font-bold" :class="p.ganador === 'visita' ? 'text-white' : 'text-gray-500'">
                                                {{ p.marcadorValido ? p.golesVisita : '–' }}
                                            </span>
                                        </div>
                                        <div v-if="p.hayPenales" class="mt-1 text-right text-[10px] font-bold uppercase tracking-wider text-fnGold">
                                            {{ p.penalesLocal }}-{{ p.penalesVisita }} pen.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] px-6 py-10 text-center text-sm text-gray-500">
                        Las eliminatorias aún no están publicadas: <span class="text-fuchsia-400">PRÓXIMAMENTE</span>.
                    </p>

                    <!-- Tercer lugar -->
                    <div v-if="partidoTercerPuesto" class="rounded-2xl border border-amber-700/40 bg-[#1a082c] p-5">
                        <p class="mb-3 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.25em] text-amber-600">
                            <i class="ph-fill ph-medal"></i> Partido por el Tercer Lugar
                        </p>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <img v-if="partidoTercerPuesto.local.bandera" :src="partidoTercerPuesto.local.bandera" :alt="partidoTercerPuesto.local.nombre" class="h-6 w-9 rounded object-cover" />
                                <span class="truncate font-display font-bold uppercase" :class="partidoTercerPuesto.ganador === 'local' ? 'text-amber-500' : 'text-gray-500'">
                                    {{ partidoTercerPuesto.local.nombre }}
                                </span>
                            </div>
                            <span class="shrink-0 font-display text-xl font-bold text-white">{{ marcadorTexto(partidoTercerPuesto) }}</span>
                            <div class="flex min-w-0 flex-1 items-center justify-end gap-2">
                                <span class="truncate text-right font-display font-bold uppercase" :class="partidoTercerPuesto.ganador === 'visita' ? 'text-amber-500' : 'text-gray-500'">
                                    {{ partidoTercerPuesto.visita.nombre }}
                                </span>
                                <img v-if="partidoTercerPuesto.visita.bandera" :src="partidoTercerPuesto.visita.bandera" :alt="partidoTercerPuesto.visita.nombre" class="h-6 w-9 rounded object-cover" />
                            </div>
                        </div>
                        <p class="mt-3 border-t border-fuchsia-900/30 pt-3 text-[11px] text-gray-500">
                            {{ partidoTercerPuesto.fechaTexto }} ·
                            {{ partidoTercerPuesto.estadio ? partidoTercerPuesto.estadio.texto : 'Sede por confirmar' }}
                        </p>
                    </div>
                </section>

                <!-- ====================== PESTAÑA: GRUPOS ==================== -->
                <section v-else-if="pestana === 'grupos'" class="space-y-10">
                    <div>
                        <div class="mb-5 flex items-center gap-4">
                            <h3 class="font-display text-xl font-bold uppercase tracking-wide text-white">Tablas de posiciones</h3>
                            <div class="h-px flex-1 bg-fuchsia-900/40"></div>
                            <span class="hidden text-[10px] font-bold uppercase tracking-widest text-fuchsia-400 sm:block">Top 2 = clasificados</span>
                        </div>

                        <div v-if="tablasGrupos.length" class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="grupo in tablasGrupos"
                                :key="'grupo-' + grupo.nombre"
                                class="brutal-card !border-fuchsia-900/40 !bg-[#1f0d36] relative overflow-hidden rounded-2xl p-5"
                            >
                                <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-fuchsia-500/10 blur-2xl"></div>
                                <h4 class="relative z-10 mb-4 font-display text-xl font-bold uppercase text-white">
                                    Grupo <span class="text-neon">{{ grupo.nombre }}</span>
                                </h4>
                                <div class="relative z-10 overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="border-b border-fuchsia-900/40 text-[9px] uppercase tracking-wider text-gray-500">
                                            <tr>
                                                <th class="py-2 pr-1">#</th>
                                                <th class="py-2">Equipo</th>
                                                <th class="py-2 text-center">PJ</th>
                                                <th class="py-2 text-center">G</th>
                                                <th class="py-2 text-center">E</th>
                                                <th class="py-2 text-center">P</th>
                                                <th class="py-2 text-center">GF</th>
                                                <th class="py-2 text-center">GC</th>
                                                <th class="py-2 text-center">DIF</th>
                                                <th class="py-2 text-center text-fuchsia-400">PTS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(eq, indice) in grupo.equipos"
                                                :key="'gt-' + grupo.nombre + '-' + (eq.id || eq.nombre)"
                                                class="border-b border-fuchsia-900/20 transition-colors hover:bg-[#2a134a]"
                                                :class="indice < 2 ? 'bg-fuchsia-900/10' : ''"
                                            >
                                                <td class="py-2 pr-1 font-bold" :class="indice < 2 ? 'text-fnGold' : 'text-gray-600'">{{ indice + 1 }}</td>
                                                <td class="py-2">
                                                    <div class="flex items-center gap-2">
                                                        <img v-if="eq.bandera" :src="eq.bandera" :alt="eq.nombre" class="h-4 w-6 shrink-0 rounded-sm object-cover" />
                                                        <span class="truncate font-bold" :class="indice < 2 ? 'text-white' : 'text-gray-400'">{{ eq.nombre }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-2 text-center text-gray-400">{{ eq.pj }}</td>
                                                <td class="py-2 text-center text-gray-400">{{ eq.g }}</td>
                                                <td class="py-2 text-center text-gray-400">{{ eq.e }}</td>
                                                <td class="py-2 text-center text-gray-400">{{ eq.p }}</td>
                                                <td class="py-2 text-center text-gray-400">{{ eq.gf }}</td>
                                                <td class="py-2 text-center text-gray-400">{{ eq.gc }}</td>
                                                <td class="py-2 text-center" :class="eq.dif > 0 ? 'text-fnEmerald' : (eq.dif < 0 ? 'text-fnCrimson' : 'text-gray-400')">
                                                    {{ eq.dif > 0 ? '+' + eq.dif : eq.dif }}
                                                </td>
                                                <td class="py-2 text-center font-bold text-fuchsia-400">{{ eq.pts }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <p v-else class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] px-6 py-10 text-center text-sm text-gray-500">
                            La API no devolvió tablas de grupos.
                        </p>
                    </div>

                    <!-- Partidos por jornada -->
                    <div v-if="jornadasGrupos.length">
                        <div class="mb-5 flex flex-wrap items-center gap-3">
                            <h3 class="font-display text-xl font-bold uppercase tracking-wide text-white">Partidos por jornada</h3>
                            <div class="hidden h-px flex-1 bg-fuchsia-900/40 sm:block"></div>
                            <div class="flex gap-2">
                                <button
                                    v-for="jornada in jornadasGrupos"
                                    :key="'jornada-btn-' + jornada.numero"
                                    type="button"
                                    class="rounded-lg border px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest transition-colors"
                                    :class="(jornadaSeleccionada && jornadaSeleccionada.numero === jornada.numero)
                                        ? 'border-fuchsia-500 bg-fuchsia-900/20 text-fuchsia-300'
                                        : 'border-fuchsia-900/40 text-gray-500 hover:text-gray-300'"
                                    @click="jornadaActiva = jornada.numero"
                                >
                                    {{ jornada.nombre }}
                                </button>
                            </div>
                        </div>

                        <div v-if="jornadaSeleccionada" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="p in jornadaSeleccionada.partidos"
                                :key="'jp-' + p.id"
                                class="rounded-xl border border-fuchsia-900/40 bg-[#1a082c] p-4"
                            >
                                <div class="mb-3 flex items-center justify-between text-[10px] font-bold uppercase tracking-widest">
                                    <span class="text-fuchsia-500">Grupo {{ p.grupo || '—' }}</span>
                                    <span class="text-gray-600">{{ p.fecha ? p.fecha.textoCorto : 'S/F' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 flex-1 items-center gap-2">
                                        <img v-if="p.local.bandera" :src="p.local.bandera" :alt="p.local.nombre" class="h-4 w-6 shrink-0 rounded-sm object-cover" />
                                        <span class="truncate text-sm font-bold" :class="p.ganador === 'local' ? 'text-white' : 'text-gray-500'">{{ p.local.nombre }}</span>
                                    </div>
                                    <span class="shrink-0 font-display text-base font-bold text-fuchsia-300">{{ marcadorTexto(p) }}</span>
                                    <div class="flex min-w-0 flex-1 items-center justify-end gap-2">
                                        <span class="truncate text-right text-sm font-bold" :class="p.ganador === 'visita' ? 'text-white' : 'text-gray-500'">{{ p.visita.nombre }}</span>
                                        <img v-if="p.visita.bandera" :src="p.visita.bandera" :alt="p.visita.nombre" class="h-4 w-6 shrink-0 rounded-sm object-cover" />
                                    </div>
                                </div>
                                <p class="mt-3 truncate border-t border-fuchsia-900/20 pt-2 text-[10px] text-gray-600">
                                    {{ p.estadio ? p.estadio.texto : 'Sede por confirmar' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ====================== PESTAÑA: PARTIDOS ================== -->
                <section v-else-if="pestana === 'partidos'" class="space-y-12">
                    <p class="text-xs font-bold uppercase tracking-widest text-gray-500">
                        {{ partidosFiltrados.length }} partido<span v-if="partidosFiltrados.length !== 1">s</span> encontrado<span v-if="partidosFiltrados.length !== 1">s</span>
                    </p>

                    <div v-for="bloque in fasesFiltradas" :key="'fase-' + bloque.tipo">
                        <div class="mb-5 flex items-center gap-4">
                            <h3 class="font-display text-lg font-bold uppercase tracking-wide text-white sm:text-xl">{{ bloque.nombre }}</h3>
                            <span class="rounded border border-fuchsia-900/50 px-2 py-0.5 text-[10px] font-bold text-fuchsia-400">{{ bloque.partidos.length }}</span>
                            <div class="h-px flex-1 bg-fuchsia-900/40"></div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <article
                                v-for="p in bloque.partidos"
                                :key="'match-' + p.id"
                                class="brutal-card !border-fuchsia-900/40 !bg-[#1a082c] rounded-2xl p-5"
                            >
                                <!-- Encabezado -->
                                <header class="flex flex-wrap items-center justify-between gap-2 border-b border-fuchsia-900/30 pb-3">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-fuchsia-500">{{ p.contexto }}</span>
                                    <span class="text-[10px] text-gray-500">
                                        {{ p.fechaTexto }}<template v-if="p.horaTexto"> · {{ p.horaTexto }}</template>
                                    </span>
                                </header>

                                <!-- Marcador -->
                                <div class="flex items-center justify-between gap-3 py-4">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <img v-if="p.local.bandera" :src="p.local.bandera" :alt="p.local.nombre" class="h-7 w-10 shrink-0 rounded object-cover" />
                                        <div v-else class="flex h-7 w-10 shrink-0 items-center justify-center rounded bg-[#2a134a] text-[9px] font-bold text-gray-400">{{ p.local.codigo }}</div>
                                        <span
                                            class="truncate font-display text-sm font-bold uppercase sm:text-base"
                                            :class="p.ganador === 'local' ? 'text-fnGold' : (p.ganador === 'empate' ? 'text-gray-300' : 'text-gray-500')"
                                        >{{ p.local.nombre }}</span>
                                    </div>

                                    <div class="shrink-0 rounded-lg border border-fuchsia-900/40 bg-[#10031c] px-4 py-2 text-center">
                                        <div class="font-display text-xl font-bold text-white">{{ marcadorTexto(p) }}</div>
                                        <div v-if="p.hayPenales" class="text-[10px] font-bold uppercase tracking-wider text-fnGold">
                                            ({{ p.penalesLocal }}-{{ p.penalesVisita }} pen.)
                                        </div>
                                        <div v-else-if="p.ganador === 'empate'" class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Empate</div>
                                    </div>

                                    <div class="flex min-w-0 flex-1 items-center justify-end gap-3">
                                        <span
                                            class="truncate text-right font-display text-sm font-bold uppercase sm:text-base"
                                            :class="p.ganador === 'visita' ? 'text-fnGold' : (p.ganador === 'empate' ? 'text-gray-300' : 'text-gray-500')"
                                        >{{ p.visita.nombre }}</span>
                                        <img v-if="p.visita.bandera" :src="p.visita.bandera" :alt="p.visita.nombre" class="h-7 w-10 shrink-0 rounded object-cover" />
                                        <div v-else class="flex h-7 w-10 shrink-0 items-center justify-center rounded bg-[#2a134a] text-[9px] font-bold text-gray-400">{{ p.visita.codigo }}</div>
                                    </div>
                                </div>

                                <!-- Ganador por penales -->
                                <p v-if="p.ganadorPorPenales" class="mb-3 rounded-lg border border-fnGold/30 bg-fnGold/5 px-3 py-1.5 text-center text-[10px] font-bold uppercase tracking-widest text-fnGold">
                                    <i class="ph-fill ph-crosshair"></i>
                                    {{ (p.ganador === 'local' ? p.local.nombre : p.visita.nombre) }} avanza en penales
                                </p>

                                <!-- Goleadores -->
                                <div
                                    v-if="p.golesLocalLista.length || p.golesVisitaLista.length"
                                    class="grid grid-cols-2 gap-4 border-t border-fuchsia-900/30 pt-3 text-[11px]"
                                >
                                    <ul class="space-y-1 text-right">
                                        <li v-for="(gol, i) in p.golesLocalLista" :key="'gl-' + p.id + '-' + i" class="truncate">
                                            <span class="text-gray-300">{{ gol.nombre }}</span>
                                            <span v-if="gol.minutoTexto" class="ml-1 text-fuchsia-400">{{ gol.minutoTexto }}</span>
                                            <span v-if="gol.penal" class="ml-1 text-fnGold">(pen.)</span>
                                            <span v-if="gol.autogol" class="ml-1 text-fnCrimson">(a.g.)</span>
                                        </li>
                                    </ul>
                                    <ul class="space-y-1">
                                        <li v-for="(gol, i) in p.golesVisitaLista" :key="'gv-' + p.id + '-' + i" class="truncate">
                                            <span class="text-gray-300">{{ gol.nombre }}</span>
                                            <span v-if="gol.minutoTexto" class="ml-1 text-fuchsia-400">{{ gol.minutoTexto }}</span>
                                            <span v-if="gol.penal" class="ml-1 text-fnGold">(pen.)</span>
                                            <span v-if="gol.autogol" class="ml-1 text-fnCrimson">(a.g.)</span>
                                        </li>
                                    </ul>
                                </div>
                                <p
                                    v-else-if="p.marcadorValido && (p.golesLocal + p.golesVisita) > 0"
                                    class="border-t border-fuchsia-900/30 pt-3 text-[10px] text-gray-600"
                                >
                                    Detalle de goleadores no disponible en la API.
                                </p>

                                <!-- Sede -->
                                <footer class="mt-3 flex items-center gap-1.5 text-[10px] text-gray-600">
                                    <i class="ph ph-map-pin"></i>
                                    <span class="truncate">{{ p.estadio ? p.estadio.texto : 'Sede por confirmar' }}</span>
                                </footer>
                            </article>
                        </div>
                    </div>

                    <p v-if="!fasesFiltradas.length" class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] px-6 py-12 text-center text-sm text-gray-500">
                        Ningún partido coincide con tu búsqueda.
                    </p>
                </section>

                <!-- ==================== PESTAÑA: ESTADÍSTICAS ================ -->
                <section v-else-if="pestana === 'stats'" class="space-y-10">
                    <!-- Goleadores -->
                    <div>
                        <div class="mb-5 flex items-center gap-4">
                            <h3 class="font-display text-xl font-bold uppercase tracking-wide text-white">Tabla de goleadores</h3>
                            <div class="h-px flex-1 bg-fuchsia-900/40"></div>
                        </div>

                        <div v-if="goleadores.length" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div
                                v-for="(jugador, indice) in goleadores"
                                :key="'goleador-' + jugador.nombre"
                                class="flex items-center gap-4 rounded-xl border bg-[#1a082c] px-4 py-3"
                                :class="indice === 0 ? 'border-fnGold/50 shadow-glowGold' : 'border-fuchsia-900/40'"
                            >
                                <span class="w-6 shrink-0 font-display text-lg font-bold" :class="indice === 0 ? 'text-fnGold' : 'text-gray-600'">
                                    {{ indice + 1 }}
                                </span>
                                <img
                                    v-if="jugador.equipo && jugador.equipo.bandera"
                                    :src="jugador.equipo.bandera"
                                    :alt="jugador.equipo.nombre"
                                    class="h-6 w-9 shrink-0 rounded object-cover"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-bold text-white">{{ jugador.nombre }}</div>
                                    <div class="truncate text-[10px] uppercase tracking-widest text-gray-500">
                                        {{ jugador.equipo ? jugador.equipo.nombre : 'Equipo por confirmar' }}
                                        <span v-if="jugador.penales"> · {{ jugador.penales }} de penal</span>
                                    </div>
                                    <div class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-[#2a134a]">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-fnBrightPurple to-fnMagenta"
                                            :style="{ width: (maxGolesJugador ? (jugador.goles / maxGolesJugador) * 100 : 0) + '%' }"
                                        ></div>
                                    </div>
                                </div>
                                <span class="shrink-0 font-display text-2xl font-bold" :class="indice === 0 ? 'text-fnGold' : 'text-fuchsia-300'">
                                    {{ jugador.goles }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] px-6 py-10 text-center text-sm text-gray-500">
                            La API no publicó el detalle de goleadores.
                        </p>

                        <p class="mt-3 text-[10px] leading-relaxed text-gray-600">
                            Nota: los nombres provienen de la API pública worldcup26 y pueden tener variaciones de
                            transliteración; las variantes evidentes de un mismo apellido se agrupan automáticamente.
                            Los autogoles no se contabilizan.
                        </p>
                    </div>

                    <!-- Ataque / Defensa -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] p-5">
                            <h4 class="mb-4 flex items-center gap-2 font-display text-base font-bold uppercase tracking-wide text-white">
                                <i class="ph-fill ph-fire text-fnMagenta"></i> Mejor ataque
                            </h4>
                            <ul v-if="mejorAtaque.length" class="space-y-3">
                                <li v-for="fila in mejorAtaque" :key="'atk-' + (fila.equipo.id || fila.equipo.nombre)" class="flex items-center gap-3">
                                    <img v-if="fila.equipo.bandera" :src="fila.equipo.bandera" :alt="fila.equipo.nombre" class="h-5 w-8 shrink-0 rounded-sm object-cover" />
                                    <span class="w-28 shrink-0 truncate text-xs font-bold text-gray-300 sm:w-36">{{ fila.equipo.nombre }}</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-[#2a134a]">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-fnMagenta to-fnBrightPurple"
                                            :style="{ width: (maxGolesEquipo ? (fila.gf / maxGolesEquipo) * 100 : 0) + '%' }"
                                        ></div>
                                    </div>
                                    <span class="w-8 shrink-0 text-right font-display text-sm font-bold text-white">{{ fila.gf }}</span>
                                </li>
                            </ul>
                            <p v-else class="py-6 text-center text-sm text-gray-500">Sin partidos jugados.</p>
                        </div>

                        <div class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] p-5">
                            <h4 class="mb-4 flex items-center gap-2 font-display text-base font-bold uppercase tracking-wide text-white">
                                <i class="ph-fill ph-shield-check text-fnEmerald"></i> Mejor defensa
                            </h4>
                            <ul v-if="mejorDefensa.length" class="space-y-3">
                                <li v-for="fila in mejorDefensa" :key="'def-' + (fila.equipo.id || fila.equipo.nombre)" class="flex items-center gap-3">
                                    <img v-if="fila.equipo.bandera" :src="fila.equipo.bandera" :alt="fila.equipo.nombre" class="h-5 w-8 shrink-0 rounded-sm object-cover" />
                                    <span class="w-28 shrink-0 truncate text-xs font-bold text-gray-300 sm:w-36">{{ fila.equipo.nombre }}</span>
                                    <span class="flex-1 text-right text-[10px] uppercase tracking-widest text-gray-500">{{ fila.pj }} PJ</span>
                                    <span class="w-16 shrink-0 text-right font-display text-sm font-bold text-fnEmerald">{{ fila.gc }} GC</span>
                                </li>
                            </ul>
                            <p v-else class="py-6 text-center text-sm text-gray-500">Aún no hay equipos con 3 o más partidos.</p>
                        </div>
                    </div>

                    <!-- Récords -->
                    <div>
                        <div class="mb-5 flex items-center gap-4">
                            <h3 class="font-display text-xl font-bold uppercase tracking-wide text-white">Récords del torneo</h3>
                            <div class="h-px flex-1 bg-fuchsia-900/40"></div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            <div class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] p-5">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-fuchsia-500">Goleada más amplia</p>
                                <template v-if="recordGoleada">
                                    <p class="mt-3 font-display text-lg font-bold text-white">
                                        {{ recordGoleada.partido.local.nombre }} {{ marcadorTexto(recordGoleada.partido) }} {{ recordGoleada.partido.visita.nombre }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Diferencia de {{ recordGoleada.diferencia }} goles · {{ recordGoleada.partido.faseNombre }}
                                    </p>
                                </template>
                                <p v-else class="mt-3 text-sm text-gray-500">POR ANUNCIAR</p>
                            </div>

                            <div class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] p-5">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-fnMagenta">Partido con más goles</p>
                                <template v-if="recordGoleado">
                                    <p class="mt-3 font-display text-lg font-bold text-white">
                                        {{ recordGoleado.partido.local.nombre }} {{ marcadorTexto(recordGoleado.partido) }} {{ recordGoleado.partido.visita.nombre }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        {{ recordGoleado.total }} goles · {{ recordGoleado.partido.faseNombre }}
                                    </p>
                                </template>
                                <p v-else class="mt-3 text-sm text-gray-500">POR ANUNCIAR</p>
                            </div>

                            <div class="rounded-2xl border border-fnGold/30 bg-[#1a082c] p-5">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-fnGold">Series definidas por penales</p>
                                <ul v-if="partidosPorPenales.length" class="mt-3 space-y-2">
                                    <li v-for="p in partidosPorPenales" :key="'pen-' + p.id" class="text-[11px] text-gray-400">
                                        <span class="font-bold text-gray-200">{{ p.local.nombre }} {{ p.penalesLocal }}-{{ p.penalesVisita }} {{ p.visita.nombre }}</span>
                                        <span class="ml-1 text-gray-600">· {{ p.faseCorto }}</span>
                                    </li>
                                </ul>
                                <p v-else class="mt-3 text-sm text-gray-500">Ninguna serie se definió desde el manchón.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ======================= PESTAÑA: EQUIPOS ================== -->
                <section v-else-if="pestana === 'equipos'" class="space-y-8">
                    <div v-if="equiposPorGrupo.length" class="space-y-8">
                        <div v-for="grupo in equiposPorGrupo" :key="'eq-grupo-' + grupo.nombre">
                            <div class="mb-4 flex items-center gap-4">
                                <h3 class="font-display text-lg font-bold uppercase tracking-wide text-white">{{ grupo.nombre }}</h3>
                                <span class="rounded border border-fuchsia-900/50 px-2 py-0.5 text-[10px] font-bold text-fuchsia-400">{{ grupo.equipos.length }}</span>
                                <div class="h-px flex-1 bg-fuchsia-900/40"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                                <div
                                    v-for="eq in grupo.equipos"
                                    :key="'eq-' + (eq.id || eq.nombre)"
                                    class="brutal-card !border-fuchsia-900/40 !bg-[#1f0d36] rounded-xl p-4 text-center"
                                >
                                    <div class="mx-auto mb-3 flex h-12 w-16 items-center justify-center overflow-hidden rounded-md bg-[#2a134a] shadow-glowPurple">
                                        <img v-if="eq.bandera" :src="eq.bandera" :alt="eq.nombre" class="h-full w-full object-cover" />
                                        <i v-else class="ph-fill ph-flag text-xl text-gray-500"></i>
                                    </div>
                                    <div class="truncate text-sm font-bold text-white">{{ eq.nombre }}</div>
                                    <div class="mt-1 text-[10px] font-bold uppercase tracking-widest text-fuchsia-500">{{ eq.codigo }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="rounded-2xl border border-fuchsia-900/40 bg-[#1a082c] px-6 py-12 text-center text-sm text-gray-500">
                        Ningún equipo coincide con tu búsqueda.
                    </p>
                </section>
            </div>
        </main>

        <footer class="mt-12 border-t border-fuchsia-900/30 bg-[#120422] py-8 text-center">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                Datos: API pública worldcup26 · <Link href="/" class="text-fuchsia-500 transition-colors hover:text-fuchsia-400">Rankit.pro</Link>
            </p>
        </footer>
    </div>
</template>
