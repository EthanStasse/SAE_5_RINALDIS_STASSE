@props(['titre' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre ? $titre.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 dark:bg-stone-950 text-stone-800 dark:text-stone-100 antialiased">
    @auth
        <header class="border-b border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <nav class="flex items-center gap-5" aria-label="Menu principal">
                    <a href="{{ route('accueil') }}" class="font-semibold text-green-800 dark:text-green-400">{{ config('app.name') }}</a>
                    @can('voir-serres')
                        <a href="{{ route('serres.index') }}" @class(['text-sm hover:text-green-800 dark:hover:text-green-300', 'font-semibold text-green-800 dark:text-green-400' => request()->routeIs('serres.*')])>Serres</a>
                        <a href="{{ route('alertes.index') }}" @class(['flex items-center gap-1 text-sm hover:text-green-800 dark:hover:text-green-300', 'font-semibold text-green-800 dark:text-green-400' => request()->routeIs('alertes.*')])>
                            Alertes
                            @if ($nombreAlertes > 0)
                                <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold text-white" aria-label="{{ $nombreAlertes }} alerte(s) en cours">{{ $nombreAlertes }}</span>
                            @endif
                        </a>
                    @endcan

                                    @can('voir-audit')
                                                        @can('gerer-comptes')
                        <a href="{{ route('comptes.create') }}" @class(['text-sm hover:text-green-800 dark:hover:text-green-300', 'font-semibold text-green-800 dark:text-green-400' => request()->routeIs('comptes.*')])>Créer un compte</a>
                    @endcan
                        <a href="{{ route('journal-connexions') }}" @class(['text-sm hover:text-green-800 dark:hover:text-green-300', 'font-semibold text-green-800 dark:text-green-400' => request()->routeIs('journal-connexions')])>Journal des connexions</a>
                    @endcan
                </nav>

                <div class="flex flex-wrap items-center gap-4 text-sm">
                    @if ($structureCourante)
                        <span class="rounded-full bg-green-50 dark:bg-green-950 px-3 py-1 text-green-800 dark:text-green-400">{{ $structureCourante->nom_commercial }}</span>
                    @endif
                                        {{-- EF-D-14 : accès à Mon compte depuis toutes les pages. --}}
                    <a href="{{ route('mon-compte') }}" class="hover:text-green-800 dark:hover:text-green-300" title="Mon compte">
                        {{ auth()->user()->name }} · <span class="underline">Mon compte</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-stone-600 dark:text-stone-400 underline hover:text-stone-900 dark:hover:text-stone-100">Se déconnecter</button>
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
