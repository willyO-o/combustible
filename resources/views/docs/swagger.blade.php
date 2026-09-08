<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Documentación API · {{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('vendor/swagger-ui/favicon-32x32.png') }}" sizes="32x32">
    <link rel="icon" type="image/png" href="{{ asset('vendor/swagger-ui/favicon-16x16.png') }}" sizes="16x16">
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
    <style>
        html { box-sizing: border-box; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }

        /* Barra superior propia, en lugar del topbar por defecto de Swagger UI */
        .doc-topbar {
            background: #1b1f27;
            color: #fff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .doc-topbar h1 {
            font-size: 16px;
            margin: 0;
            font-weight: 600;
        }
        .doc-topbar span {
            font-size: 12px;
            color: #9aa4b2;
        }
    </style>
</head>
<body>
    <div class="doc-topbar">
        <h1>{{ config('app.name') }} — API de Gestión de Maquinaria y Operaciones</h1>
        <span>Especificación: <a href="{{ asset('docs/openapi.yaml') }}" style="color:#9aa4b2;">openapi.yaml</a></span>
    </div>

    <div id="swagger-ui"></div>

    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script src="{{ asset('vendor/swagger-ui/swagger-ui-standalone-preset.js') }}"></script>
    <script>
        window.onload = function() {
            window.ui = SwaggerUIBundle({
                url: "{{ asset('docs/openapi.yaml') }}",
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                docExpansion: "list",
                defaultModelsExpandDepth: 1,
                persistAuthorization: true,
            });
        };
    </script>
</body>
</html>
