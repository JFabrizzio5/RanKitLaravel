<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #050505; color: #ffffff; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #0a0a0a; border: 1px solid #333; border-radius: 8px; overflow: hidden; margin-top: 40px; margin-bottom: 40px; }
        .header { background-color: #000000; padding: 30px; text-align: center; border-bottom: 2px solid #ff0055; }
        .header h1 { margin: 0; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; color: #ffffff; font-style: italic; }
        .header span { color: #ff0055; }
        .content { padding: 40px 30px; }
        h2 { font-size: 22px; margin-top: 0; color: #ffffff; }
        p { font-size: 15px; line-height: 1.6; color: #cccccc; }
        .footer { background-color: #000000; padding: 20px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #333; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1>Rank<span>it</span></h1></div>
        <div class="content">
            <h2>¡Hola, {{ $playerName }}!</h2>
            <p>Lamentamos informarte que tu solicitud de inscripción para el torneo <strong>{{ $tournamentName }}</strong> ha sido rechazada.</p>
            <p>Esto puede deberse a múltiples factores, como falta de cupo, problemas con el pago o no cumplir con los requisitos del torneo.</p>
            <p>Si consideras que esto es un error, por favor comunícate con el administrador del torneo vía WhatsApp o Discord.</p>
        </div>
        <div class="footer">&copy; {{ date('Y') }} Rankit.Pro. Todos los derechos reservados.</div>
    </div>
</body>
</html>
