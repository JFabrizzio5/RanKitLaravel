<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #050505; color: #ffffff; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #0a0a0a; border: 1px solid #333; border-radius: 8px; overflow: hidden; margin-top: 40px; margin-bottom: 40px; }
        .header { background-color: #000000; padding: 30px; text-align: center; border-bottom: 2px solid #b700ff; }
        .header h1 { margin: 0; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; color: #ffffff; font-style: italic; }
        .header span { color: #b700ff; }
        .content { padding: 40px 30px; }
        h2 { font-size: 22px; margin-top: 0; color: #ffffff; }
        p { font-size: 15px; line-height: 1.6; color: #cccccc; }
        .fecha { margin: 24px 0; padding: 20px; background-color: #12061c; border: 1px solid #b700ff; border-radius: 6px; text-align: center; }
        .fecha .etiqueta { display: block; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #b700ff; margin-bottom: 8px; }
        .fecha .valor { display: block; font-size: 22px; font-weight: bold; text-transform: uppercase; color: #ffffff; }
        .nota { margin: 20px 0; padding: 16px; background-color: #101010; border-left: 3px solid #00e0b8; border-radius: 4px; }
        .nota p { margin: 0; color: #dddddd; white-space: pre-line; }
        .footer { background-color: #000000; padding: 20px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1>Rank<span>it</span></h1></div>
        <div class="content">
            <h2>¡Hola, {{ $playerName }}!</h2>
            <p>Te escribimos porque <strong>{{ $tournamentName }}</strong> se recorrió: cambiamos la fecha del evento.</p>

            <div class="fecha">
                <span class="etiqueta">Nueva fecha</span>
                <span class="valor">{{ $dateLabel }}</span>
            </div>

            @if (!empty($note))
                <div class="nota">
                    <p>{{ $note }}</p>
                </div>
            @endif

            <p><strong>Tu lugar sigue apartado</strong>: no tienes que volver a inscribirte.</p>
            <p>La hora del lobby y el código de la partida te llegan por WhatsApp y por este mismo correo antes de que arranque el evento.</p>
            <p>Perdón por el movimiento y gracias por la paciencia. Nos vemos en el lobby.</p>
        </div>
        <div class="footer">&copy; {{ date('Y') }} Rankit.Pro. Todos los derechos reservados.</div>
    </div>
</body>
</html>
