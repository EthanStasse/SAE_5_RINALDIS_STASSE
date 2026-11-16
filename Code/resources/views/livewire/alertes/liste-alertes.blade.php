<div class="space-y-8" wire:poll.60s>
    <h1 class="text-2xl font-semibold">Alertes des serres</h1>

    <section aria-labelledby="titre-en-cours" class="space-y-3">
        <h2 id="titre-en-cours" class="text-lg font-semibold">En cours</h2>

        @forelse ($enCours as $alerte)
            <article class="rounded-xl border-l-4 border-red-600 bg-white p-4 shadow-sm" wire:key="alerte-{{ $alerte->id }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="font-medium text-red-800">{{ $alerte->type->libelle() }}
                        @if ($alerte->serre)
                            — <a href="{{ route('serres.show', $alerte->serre) }}" class="underline hover:text-red-950">{{ $alerte->serre->nom }}</a>
                        @endif
                    </p>
                    <p class="text-sm text-stone-500">depuis le {{ $alerte->debut_le->timezone('Europe/Paris')->format('d/m à H\hi') }}</p>
                </div>
                <p class="mt-1">{{ $alerte->message }}</p>
                @if ($alerte->notifiee_le)
                    <p class="mt-1 text-xs text-stone-500">E-mail envoyé le {{ $alerte->notifiee_le->timezone('Europe/Paris')->format('d/m à H\hi') }}</p>
                @endif
            </article>
        @empty
            <p class="rounded-xl bg-green-50 px-4 py-3 text-green-800">Aucune alerte en cours : toutes les serres sont dans leurs seuils.</p>
        @endforelse
    </section>

    <section aria-labelledby="titre-historique" class="space-y-3">
        <h2 id="titre-historique" class="text-lg font-semibold">Terminées ces {{ \App\Livewire\Alertes\ListeAlertes::JOURS_HISTORIQUE }} derniers jours</h2>

        @if ($historique->isEmpty())
            <p class="text-stone-500">Aucune alerte terminée récemment.</p>
        @else
            <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-medium">Serre</th>
                            <th scope="col" class="px-4 py-2 font-medium">Alerte</th>
                            <th scope="col" class="px-4 py-2 font-medium">Début</th>
                            <th scope="col" class="px-4 py-2 font-medium">Fin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($historique as $alerte)
                            <tr wire:key="historique-{{ $alerte->id }}">
                                <td class="px-4 py-2 font-medium">{{ $alerte->serre?->nom }}</td>
                                <td class="px-4 py-2">{{ $alerte->message }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ $alerte->debut_le->timezone('Europe/Paris')->format('d/m H\hi') }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ $alerte->fin_le?->timezone('Europe/Paris')->format('d/m H\hi') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
