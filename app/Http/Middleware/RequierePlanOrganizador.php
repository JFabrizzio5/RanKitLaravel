<?php

namespace App\Http\Middleware;

use App\Services\Planes\PlanOrganizador;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los paneles de organizador (Fortnite, LoL/Valorant, fútbol) solo con plan
 * vigente. Sin plan se manda a /planes; las llamadas JSON reciben 403.
 */
class RequierePlanOrganizador
{
    public function __construct(private PlanOrganizador $planes)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->planes->puedeEntrar($request->user())) {
            return $next($request);
        }

        $mensaje = 'Necesitas un plan activo para usar el panel de organizador.';

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['message' => $mensaje, 'planes' => route('planes.index')], 403);
        }

        return redirect()->route('planes.index', ['motivo' => 'sin-plan']);
    }
}
