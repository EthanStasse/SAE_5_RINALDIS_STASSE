<x-layouts.app titre="Accueil">
    <h1 class="text-2xl font-semibold">Bonjour {{ auth()->user()->name }}</h1>

    @if ($structureCourante)
        <p class="mt-2 text-stone-600">Vous travaillez dans le Jardin <strong>{{ $structureCourante->nom_commercial }}</strong>.</p>
    @endif

    @if ($nombreAlertes > 0)
        <a href="{{ route('alertes.index') }}" class="mt-6 block rounded-xl border-2 border-red-500 bg-red-50 p-4 font-medium text-red-900 hover:bg-red-100">
            {{ $nombreAlertes }} alerte(s) en cours dans les serres — voir le détail
        </a>
    @endif
</x-layouts.app>
