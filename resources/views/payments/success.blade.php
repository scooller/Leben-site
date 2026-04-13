<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .icon svg {
            width: 48px;
            height: 48px;
            color: white;
        }
        h1 {
            color: #1f2937;
            margin: 0 0 10px;
            font-size: 28px;
        }
        p {
            color: #6b7280;
            line-height: 1.6;
            margin: 0 0 30px;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .payment-id {
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
            margin-top: 20px;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1>¡Pago Exitoso!</h1>
        <p>Tu pago ha sido procesado correctamente. Recibirás un correo de confirmación en breve.</p>

        @if(session('success'))
            <p style="color: #10b981; font-weight: 600;">{{ session('success') }}</p>
        @endif

        @if($payment)
            <div class="payment-id">
                ID de Pago: {{ $payment }}
            </div>
        @endif

        <div style="margin-top: 30px;">
            <a href="{{ config('app.frontend_url', '/') }}" class="btn">Volver al inicio</a>
        </div>
    </div>
</body>
</html>
