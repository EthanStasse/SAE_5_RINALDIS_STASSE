{{-- EF-D-06 : page affichée quand la limite par adresse IP est atteinte (429).
     Le message parle du réseau, jamais d'un compte : il ne révèle rien (EF-D-07). --}}
<x-layouts.app titre="Trop de tentatives">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Trop de tentatives</h1>

        <p class="mt-2 text-sm text-stone-600">
            Trop de tentatives de connexion depuis ce réseau. Pour votre sécurité,
            patientez {{ $exception->getHeaders()['Retry-After'] ?? 60 }} secondes avant de réessayer.
        </p>

        <a href="{{ route('login') }}" class="mt-4 inline-block text-sm font-medium text-green-800 underline">
            Revenir à la connexion
        </a>
    </div>
</x-layouts.app>