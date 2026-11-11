<?php

namespace App\Http\Controllers;

use App\Services\Inscripciones\MisInscripciones;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /mis-torneos: los torneos a los que está inscrito el jugador con sesión,
 * sin importar con qué correo llenó el formulario (ver MisInscripciones).
 */
class MisTorneosController extends Controller
{
    public function __invoke(Request $request, MisInscripciones $inscripciones): Response
    {
        return Inertia::render('MisTorneos', [
            'torneos' => $inscripciones->de($request->user()),
        ]);
    }
}
