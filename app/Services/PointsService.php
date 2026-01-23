<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para interactuar con el microservicio de Python (Rankit Points).
 */
class PointsService
{
    protected string $baseUrl;

    public function __construct()
    {
        // Obtiene la URL desde el .env
        $this->baseUrl = config('services.points_service.url', env('POINTS_SERVICE_URL', 'http://localhost:8011'));
    }

    /**
     * Obtiene el estado de los usuarios conectados y la DB.
     */
    public function getStatus()
    {
        try {
            $response = Http::get("{$this->baseUrl}/status");
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Error conectando con Points Service: " . $e->getMessage());
            return ['error' => 'No se pudo conectar con el servicio de puntos'];
        }
    }

    /**
     * Realiza el sorteo y obtiene un ganador.
     */
    public function drawWinner()
    {
        try {
            $response = Http::get("{$this->baseUrl}/draw-winner");
            
            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => $response->json()['detail'] ?? 'Error desconocido en el sorteo'
                ];
            }

            return [
                'success' => true,
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error("Error ejecutando sorteo: " . $e->getMessage());
            return ['success' => false, 'message' => 'Servicio de sorteos no disponible'];
        }
    }
}