<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Aviso por correo de que se pierden tickets sin usar o termina el Gestor
 * Pro. Se manda en el momento (sin cola): producción no tiene worker.
 * Plantilla: resources/views/mail/plan-por-vencer.blade.php.
 */
class PlanPorVencer extends Notification
{
    use Queueable;

    /** @param int|null $tickets null = Gestor Pro que no se renueva */
    public function __construct(
        public ?int $tickets,
        public string $vence,
        public int $dias,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fecha = Carbon::parse($this->vence)->timezone('America/Mexico_City')->locale('es')->isoFormat('D [de] MMMM');
        $cuando = match (true) {
            $this->dias <= 0 => 'hoy',
            $this->dias === 1 => 'mañana',
            default => "en {$this->dias} días",
        };
        $nombre = trim((string) strtok((string) $notifiable->name, ' ')) ?: 'organizador';

        if ($this->tickets === null) {
            $asunto = "Tu Gestor Pro de Rankit termina el {$fecha}";
            $datos = [
                'lineas' => [
                    "Tu Gestor Pro termina {$cuando} ({$fecha}) y no se va a renovar.",
                    'Después de esa fecha ya no podrás crear torneos. Los que ya creaste los podrás seguir administrando un tiempo y sus páginas públicas siguen visibles.',
                ],
                'boton' => 'Seguir con Gestor Pro',
                'url' => route('planes.index', ['quiero' => 'pro']),
                'nota' => null,
            ];
        } else {
            $asunto = "Tus tickets de Rankit vencen el {$fecha}";
            $cuantos = $this->tickets === 1
                ? "Tienes 1 ticket de torneo sin usar que vence {$cuando} ({$fecha})."
                : "Tienes {$this->tickets} tickets de torneo sin usar que vencen {$cuando} ({$fecha}).";
            $datos = [
                'lineas' => [$cuantos, 'Cada ticket es un torneo. Créalo antes de esa fecha o se pierde.'],
                'boton' => 'Crear torneo',
                'url' => route('game.selector'),
                'nota' => '¿Necesitas más? Puedes recargar desde el menú de tu cuenta.',
            ];
        }

        return (new MailMessage)
            ->from(config('mail.from.address'), 'Rankit')
            ->subject($asunto)
            ->markdown('mail.plan-por-vencer', $datos + ['nombre' => $nombre]);
    }
}
