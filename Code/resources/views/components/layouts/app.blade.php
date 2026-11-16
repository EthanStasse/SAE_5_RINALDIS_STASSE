@props(['titre' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre ? $titre.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-800 antialiased">
    @auth
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <nav class="flex items-center gap-5" aria-label="Menu principal">
                    <a href="{{ route('accueil') }}" class="font-semibold text-green-800">{{ config('app.name') }}</a>
                    @can('voir-serres')
                        <a href="{{ route('serres.index') }}" @class(['text-sm hover:text-green-800', 'font-semibold text-green-800' => request()->routeIs('serres.*')])>Serres</a>
                    @endcan
                </nav>

                <div class="flex flex-wrap items-center gap-4 text-sm">
                    @if ($structureCourante)
                        <span class="rounded-full bg-green-50 px-3 py-1 text-green-800">{{ $structureCourante->nom_commercial }}</span>
                    @endif
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-stone-600 underline hover:text-stone-900">Se déconnecter</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
