<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#f8f3ea">
    <meta name="description" content="Un espacio privado de recuerdos.">
    <title>Un pedacito de todos</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cpath d='M16 27s-10-6.2-10-13.2A5.6 5.6 0 0 1 16 10a5.6 5.6 0 0 1 10 3.8C26 20.8 16 27 16 27z' fill='%23b7837a'/%3E%3C/svg%3E">
    {{-- Precarga de las tipografías principales: evita el salto de letra al cargar. --}}
    @foreach ([
        'node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2',
        'node_modules/@fontsource/cormorant-garamond/files/cormorant-garamond-latin-400-normal.woff2',
        'node_modules/@fontsource/cormorant-garamond/files/cormorant-garamond-latin-500-normal.woff2',
    ] as $font)
        @php($fontUrl = rescue(fn () => Vite::asset($font), null, false))
        @if ($fontUrl && ! Vite::isRunningHot())
            <link rel="preload" href="{{ $fontUrl }}" as="font" type="font/woff2" crossorigin>
        @endif
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/main.ts'])
</head>
<body class="bg-cream text-ink antialiased">
    <div id="app"></div>
</body>
</html>
