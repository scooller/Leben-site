<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo contacto</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f6f8fb; color: #1f2937; font-family: Arial, Helvetica, sans-serif;">
    @php
        $fields = is_array($submission->fields ?? null) ? $submission->fields : [];

        $resolveField = static function (array $aliases, mixed $fallback = '-') use ($fields): mixed {
            foreach ($aliases as $alias) {
                if (! array_key_exists($alias, $fields)) {
                    continue;
                }

                $value = $fields[$alias];

                if (is_scalar($value) && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            }

            return $fallback;
        };

        $fullName = trim((string) ($submission->name ?: $resolveField(['nombre', 'name'], '')));
        $firstName = $resolveField(['nombre', 'name'], '');
        $lastName = $resolveField(['apellido', 'apellidos', 'last_name', 'lastname'], '');

        if ($firstName === '' && $fullName !== '') {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $firstName = $parts[0] ?? '';
            $lastName = $lastName !== '' ? $lastName : trim(implode(' ', array_slice($parts, 1)));
        }

        $rut = trim((string) ($submission->rut ?: $resolveField(['rut'], '-')));
        $phone = trim((string) ($submission->phone ?: $resolveField(['telefono', 'phone', 'celular', 'whatsapp'], '-')));
        $email = trim((string) ($submission->email ?: $resolveField(['email', 'correo'], '-')));
        $comuna = $resolveField(['comuna'], '-');
        $proyecto = $resolveField(['proyecto'], '-');
        $medio = $resolveField(['medio', 'origen', 'lead_source', 'utm_source'], 'Black');
        $rango = $resolveField(['rango', 'renta', 'renta_liquida', 'income_range'], '-');
        $codeudor = $resolveField(['codeudor', 'coudedor', 'co_deudor'], '-');
        $buscas = $resolveField(['buscas', 'objetivo', 'buying_for'], '-');
        $estadoLaboral = $resolveField(['elaboral', 'estado_laboral', 'laboral'], '-');
        $mensaje = $resolveField(['mensaje', 'message'], '-');
        $siteUrl = config('app.url', 'https://sale.ileben.cl');
    @endphp

    <div style="max-width: 720px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);">
        <div style="padding: 24px 28px; background: linear-gradient(135deg, #111827, #2563eb); color: #ffffff;">
            <h2 style="margin: 0 0 6px; font-size: 24px;">Nuevo contacto recibido</h2>
            <p style="margin: 0; opacity: 0.92;">{{ $siteName }}</p>
        </div>

        <div style="padding: 24px 28px;">
            <p style="margin: 0 0 16px; font-size: 14px; color: #6b7280;">
                <strong>ID:</strong> {{ $submission->id ?? '-' }}
                &nbsp;|&nbsp;
                <strong>Fecha:</strong> {{ optional($submission->submitted_at)->format('d/m/Y H:i') ?: '-' }}
            </p>

            <ul style="margin: 0; padding-left: 18px; line-height: 1.8;">
                <li><b>Nombre:</b> <span class="nombre">{{ $firstName !== '' ? $firstName : '-' }}</span></li>
                <li><b>Apellido:</b> <span class="apellido">{{ $lastName !== '' ? $lastName : '-' }}</span></li>
                <li><b>RUT:</b> <span class="rut">{{ $rut !== '' ? $rut : '-' }}</span></li>
                <li><b>Telefono:</b> <a href="{{ $phone !== '-' ? 'tel:' . preg_replace('/\s+/', '', $phone) : '#' }}"><span class="telefono">{{ $phone !== '' ? $phone : '-' }}</span></a></li>
                <li><b>Email:</b> <a href="{{ $email !== '-' ? 'mailto:' . $email : '#' }}"><span class="email">{{ $email !== '' ? $email : '-' }}</span></a></li>
                <li><b>Comuna:</b> <span class="comuna">{{ $comuna }}</span></li>
                <li><b>Proyecto:</b> <span class="proyecto">{{ $proyecto }}</span></li>
                <li><b>Medio de llegada:</b> <span class="medio">{{ $medio }}</span></li>
                <li><b>¿En qué rango se encuentra tu renta líquida?:</b> <span class="rango">{{ $rango }}</span></li>
                <li><b>¿Cuentas con posibilidad de codeudor?:</b> <span class="codeudor">{{ $codeudor }}</span></li>
                <li><b>¿Buscas tu nuevo depto para...?:</b> <span class="buscas">{{ $buscas }}</span></li>
                <li><b>¿Cuál es tu estado laboral?:</b> <span class="elaboral">{{ $estadoLaboral }}</span></li>
            </ul>

            @if($mensaje !== '-')
                <div style="margin-top: 18px; padding: 14px 16px; background-color: #f9fafb; border-left: 4px solid #2563eb; border-radius: 8px;">
                    <p style="margin: 0 0 6px;"><strong>Mensaje:</strong></p>
                    <p style="margin: 0; white-space: pre-line;">{{ $mensaje }}</p>
                </div>
            @endif
        </div>

        <div style="padding: 18px 28px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
            -- <br>
            This e-mail was sent from a contact form on {{ $siteName }}
            (<a href="{{ $siteUrl }}" style="color: #2563eb;">{{ $siteUrl }}</a>)
        </div>
    </div>
</body>
</html>
