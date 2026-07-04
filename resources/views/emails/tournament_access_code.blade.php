<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Inter', Helvetica, Arial, sans-serif;
            background-color: #050505;
            color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #0a0a0a;
            border: 1px solid #333;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 40px;
            margin-bottom: 40px;
        }
        .header {
            background-color: #000000;
            padding: 30px;
            text-align: center;
            border-bottom: 2px solid #b700ff; /* Rankit Neon */
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #ffffff;
            font-style: italic;
        }
        .header span {
            color: #b700ff;
        }
        .content {
            padding: 40px 30px;
        }
        h2 {
            font-size: 22px;
            margin-top: 0;
            color: #ffffff;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #cccccc;
        }
        .code-box {
            background-color: #1a1a1a;
            border: 1px solid #b700ff;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .code-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888888;
            margin-bottom: 10px;
            display: block;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            color: #b700ff;
            letter-spacing: 4px;
            margin: 0;
        }
        .btn {
            display: inline-block;
            background-color: #b700ff;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 4px;
            font-size: 14px;
            margin-top: 10px;
        }
        .footer {
            background-color: #000000;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            border-top: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Rank<span>it</span></h1>
        </div>
        
        <div class="content">
            <h2>¡Hola, {{ $playerName }}!</h2>
            <p>Ya tienes tu lugar asegurado para el torneo <strong>{{ $tournamentName }}</strong>. Como es un evento privado, necesitarás el siguiente código de acceso para ver las llaves, reglas y, lo más importante, <strong>los códigos de Custom Matchmaking</strong> el día del evento.</p>
            
            <div class="code-box">
                <span class="code-label">Código de Acceso</span>
                <p class="code">{{ $accessCode }}</p>
            </div>
            
            <p>Por favor, no compartas este código con nadie. Si detectamos ingresos no autorizados, los jugadores involucrados serán descalificados.</p>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ url('/') }}" class="btn">Ir a Rankit</a>
            </div>
        </div>
        
        <div class="footer">
            &copy; {{ date('Y') }} Rankit.Pro. Todos los derechos reservados.<br>
            Este es un correo automático, por favor no respondas a esta dirección.
        </div>
    </div>
</body>
</html>
