<x-layouts.app titre="Accueil">
    <h1 class="text-2xl font-semibold">Bonjour {{ auth()->user()->name }}</h1>

    @if ($structureCourante)
        <p class="mt-2 text-stone-600">Vous travaillez dans le Jardin <strong>{{ $structureCourante->nom_commercial }}</strong>.</p>
    @endif
</x-layouts.app>
