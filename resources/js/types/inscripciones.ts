// Una inscripción del jugador tal como la arma App\Services\Inscripciones\MisInscripciones.
// La usan /mis-torneos y la sección "Mis torneos" del dashboard.

export type EstadoInscripcion = 'pending' | 'paid' | 'rejected'

export interface TorneoInscrito {
  id: number
  torneo_id: number
  nombre: string
  jugador: string
  juego: string
  tipo: 'semanal' | 'liga' | 'torneo'
  privado: boolean
  banner: string | null
  fecha: string | null // 'YYYY-MM-DD'
  fecha_tipo: 'programada' | 'jugada' | null
  estado: EstadoInscripcion
  proximo: boolean
  url: string
  inscrito_el: string
}
