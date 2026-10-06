<!DOCTYPE html>
{{-- Páginas de error autónomas: CSS en línea para que funcionen aunque falle Vite o la base de datos. --}}
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>@yield('title') · {{ config('app.name') }}</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
                background: #fcf9f4; color: #101727; font: 15px/1.5 Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
            main { width: 100%; max-width: 420px; background: #fff; border: 1px solid #e6e1d8; border-radius: 8px; padding: 32px 28px; }
            .code { font-variant-numeric: tabular-nums; font-size: 13px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; margin: 0 0 8px; }
            h1 { font-size: 22px; line-height: 1.3; margin: 0 0 8px; }
            p { margin: 0 0 24px; color: #4b5563; }
            a { display: inline-flex; align-items: center; height: 40px; padding: 0 16px; border-radius: 6px; background: #2664eb; color: #fff;
                text-decoration: none; font-weight: 500; transition: background-color .15s; }
            a:hover { background: #1d4fc4; }
            a:focus-visible { outline: 2px solid #2664eb; outline-offset: 2px; }
            .bar { height: 3px; background: #101727; border-radius: 8px 8px 0 0; margin: -33px -29px 28px; }
        </style>
    </head>
    <body>
        <main>
            <div class="bar"></div>
            <p class="code">Error @yield('code')</p>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            @hasSection('action')
                @yield('action')
            @else
                <a href="{{ url('/') }}">Ir al inicio</a>
            @endif
        </main>
    </body>
</html>
