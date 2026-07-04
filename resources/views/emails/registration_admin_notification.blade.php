<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f4f4f5; color: #18181b; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 8px; overflow: hidden; }
        .header { background-color: #18181b; padding: 20px; text-align: center; border-bottom: 2px solid #b700ff; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; color: #ffffff; }
        .content { padding: 30px; }
        h2 { font-size: 20px; margin-top: 0; color: #18181b; }
        p { font-size: 14px; line-height: 1.6; color: #3f3f46; }
        .details { background-color: #f4f4f5; padding: 15px; border-radius: 6px; margin: 20px 0; font-size: 13px; }
        .details strong { color: #18181b; }
        .btn { display: inline-block; background-color: #b700ff; color: #ffffff; text-decoration: none; padding: 10px 20px; font-weight: bold; border-radius: 4px; font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1>Nueva Inscripción</h1></div>
        <div class="content">
            <h2>Alerta de Registro</h2>
            <p>Se ha recibido una nueva inscripción en el torneo <strong>{{ $tournamentName }}</strong>.</p>
            <div class="details">
                <p><strong>Jugador:</strong> {{ $playerName }}</p>
                <p><strong>Correo:</strong> {{ $playerEmail }}</p>
                <p><strong>Estado Actual:</strong> Pendiente</p>
            </div>
            <p>Por favor, revisa el panel de administración para aceptar o rechazar esta solicitud.</p>
            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ url('/admin/dashboard') }}" class="btn">Ir al Panel</a>
            </div>
        </div>
    </div>
</body>
</html>
