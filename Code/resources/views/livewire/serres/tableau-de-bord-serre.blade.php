<div class="space-y-6" wire:poll.60s>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('serres.index') }}" class="text-sm text-green-800 underline hover:text-green-950">← Serres</a>
            <h1 class="mt-2 text-2xl font-semibold">{{ $serre->nom }}</h1>
            @if ($serre->emplacement)
                <p class="text-sm text-stone-600">{{ $serre->emplacement }}</p>
            @endif
        </div>
        <div class="flex gap-4 text-sm">
            @can('gerer-seuils')
                <a href="{{ route('serres.seuils', $serre) }}" class="text-green-800 underline hover:text-green-950">Régler les seuils</a>
            @endcan
            @can('parametrer-structure')
                <a href="{{ route('serres.boitiers', $serre) }}" class="text-green-800 underline hover:text-green-950">Boîtiers</a>
            @endcan
        </div>
    </div>

    {{-- Dernières valeurs --}}
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($cartes as $carte)
            @php($grandeur = $carte['grandeur'])
            <section class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm" aria-labelledby="carte-{{ $grandeur->value }}">
                <h2 id="carte-{{ $grandeur->value }}" class="text-sm font-medium text-stone-600">{{ $grandeur->libelle() }}</h2>

                @if ($carte['valeur'] === null)
                    <p class="mt-2 text-2xl font-semibold text-stone-400">—</p>
                    <p class="mt-1 text-sm text-stone-500">Aucune mesure</p>
                @else
                    <p class="mt-2 text-3xl font-semibold">{{ $grandeur->formater($carte['valeur']) }}</p>
                    <p @class(['mt-1 text-sm', 'text-amber-700 font-medium' => $carte['ancienne'], 'text-stone-500' => ! $carte['ancienne']])>
                        {{ $carte['ancienne'] ? 'Dernière mesure ancienne :' : 'Mesuré le' }} {{ $carte['mesureLe']->timezone('Europe/Paris')->format('d/m à H\hi') }}
                    </p>
                @endif

                <p class="mt-3 text-sm">
                    @if ($carte['seuil'] === null)
                        <span class="text-stone-500">Pas de seuil</span>
                    @elseif ($carte['horsSeuil'])
                        <span class="rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-800">Hors seuil</span>
                    @else
                        <span class="rounded-full bg-green-50 px-2 py-0.5 text-green-800">Dans le seuil</span>
                    @endif
                    @if ($carte['seuil'])
                        <span class="ml-1 text-stone-500">
                            ({{ $carte['seuil']->minimum !== null ? $grandeur->formater($carte['seuil']->minimum) : '…' }}
                            à {{ $carte['seuil']->maximum !== null ? $grandeur->formater($carte['seuil']->maximum) : '…' }})
                        </span>
                    @endif
                </p>
            </section>
        @endforeach
    </div>

    {{-- Courbes sur 24 h --}}
    @foreach ($cartes as $carte)
        @php($grandeur = $carte['grandeur'])
        <figure class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm" wire:key="courbe-{{ $grandeur->value }}">
            <figcaption class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <span class="font-medium">{{ $grandeur->libelle() }} ({{ $grandeur->unite() }}) — dernières 24 h</span>
                <span class="text-sm text-stone-500">{{ $carte['nombreMesures'] }} mesure(s)</span>
            </figcaption>

            @if ($carte['courbe']->estVide())
                <p class="py-8 text-center text-stone-500">Aucune mesure sur les dernières 24 heures.</p>
            @else
                <x-courbe :courbe="$carte['courbe']" :titre="$grandeur->libelle()" :unite="$grandeur->unite()" />
            @endif
        </figure>
    @endforeach

    {{-- Boîtiers --}}
    <section class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm" aria-labelledby="titre-boitiers">
        <h2 id="titre-boitiers" class="font-medium">Boîtiers capteurs</h2>
        @if ($boitiers->isEmpty())
            <p class="mt-2 text-sm text-stone-500">Aucun boîtier dans cette serre.</p>
        @else
            <ul class="mt-2 divide-y divide-stone-100 text-sm">
                @foreach ($boitiers as $boitier)
                    <li class="flex flex-wrap justify-between gap-2 py-2" wire:key="boitier-{{ $boitier->id }}">
                        <span class="font-medium">{{ $boitier->nom }}</span>
                        <span class="text-stone-600">
                            @if ($boitier->estRevoque())
                                Révoqué
                            @elseif ($boitier->derniere_communication_le)
                                Dernière communication le {{ $boitier->derniere_communication_le->timezone('Europe/Paris')->format('d/m à H\hi') }}
                            @else
                                Jamais connecté
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
