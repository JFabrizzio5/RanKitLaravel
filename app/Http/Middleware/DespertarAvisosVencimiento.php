<?php

namespace App\Http\Middleware;

use App\Services\Planes\AvisosVencimiento;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin cron confirmado en el hosting: la primera visita de cada día dispara la
 * revisión de vencimientos, después de mandar la respuesta. Ver AvisosVencimiento.
 */
class DespertarAvisosVencimiento
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if ($request->isMethod('GET') && !app()->runningUnitTests()) {
            AvisosVencimiento::despertar();
        }

        return $respuesta;
    }
}
