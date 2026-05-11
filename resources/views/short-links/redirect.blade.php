<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Redirigiendo...</title>
    <meta http-equiv="refresh" content="1;url={{ $destinationUrl }}">
</head>
<body>
    <p>Te estamos redirigiendo...</p>

    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'gtm.start': Date.now(),
            event: 'gtm.js',
        });
    </script>
    <script async src="https://www.googletagmanager.com/gtm.js?id={{ urlencode($tagManagerId) }}"></script>

    <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'short_link_click',
            short_link_id: @json($shortLink->id),
            slug: @json($shortLink->slug),
            destination_url: @json($destinationUrl),
            redirected_at: @json(now()->toISOString()),
        });

        window.setTimeout(function () {
            window.location.replace(@json($destinationUrl));
        }, {{ (int) $redirectDelayMs }});
    </script>

    <noscript>
        <p>
            Si no redirige automaticamente, haz clic en
            <a href="{{ $destinationUrl }}">continuar</a>.
        </p>
    </noscript>
</body>
</html>
