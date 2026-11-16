<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">Serres</h1>

        @if ($peutGerer && ! $formulaireOuvert)
            <button type="button" wire:click="creer"
                    class="rounded-md bg-green-800 px-4 py-2 text-sm font-medium text-white hover:bg-green-900">
                Déclarer une serre
            </button>
        @endif
    </div>

    @if ($confirmation)
        <p class="rounded-md bg-green-50 px-4 py-2 text-sm text-green-800" role="status">{{ $confirmation }}</p>
    @endif

    @error('suppression')
        <p class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-800" role="alert">{{ $message }}</p>
    @enderror

    @if ($formulaireOuvert)
        <form wire:submit="enregistrer" class="space-y-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold">{{ $serreEnCours ? 'Modifier la serre' : 'Nouvelle serre' }}</h2>

            <div>
                <label for="nom" class="block text-sm font-medium">Nom</label>
                <input id="nom" type="text" wire:model="nom" maxlength="100" required
                       class="mt-1 w-full max-w-md rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('nom')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="emplacement" class="block text-sm font-medium">Emplacement <span class="font-normal text-stone-500">(facultatif)</span></label>
                <input id="emplacement" type="text" wire:model="emplacement" maxlength="255" placeholder="Ex. : parcelle 2, près du hangar"
                       class="mt-1 w-full max-w-md rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('emplacement')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-green-800 px-4 py-2 text-sm font-medium text-white hover:bg-green-900">
                    Enregistrer
                </button>
                <button type="button" wire:click="annuler" class="rounded-md border border-stone-300 px-4 py-2 text-sm hover:bg-stone-100">
                    Annuler
                </button>
            </div>
        </form>
    @endif

    @if ($serres->isEmpty())
        <p class="text-stone-600">Aucune serre n'est déclarée pour ce Jardin.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-medium">Nom</th>
                        <th scope="col" class="px-4 py-2 font-medium">Emplacement</th>
                        <th scope="col" class="px-4 py-2 font-medium">Boîtiers</th>
                        @if ($peutGerer)
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($serres as $serre)
                        <tr wire:key="serre-{{ $serre->id }}">
                            <td class="px-4 py-2 font-medium">{{ $serre->nom }}</td>
                            <td class="px-4 py-2 text-stone-600">{{ $serre->emplacement ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $serre->boitiers_count }}</td>
                            @if ($peutGerer)
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <button type="button" wire:click="modifier({{ $serre->id }})" class="text-green-800 underline hover:text-green-950">Modifier</button>
                                    <button type="button" wire:click="supprimer({{ $serre->id }})"
                                            wire:confirm="Supprimer la serre « {{ $serre->nom }} » ?"
                                            class="ml-3 text-red-700 underline hover:text-red-900">Supprimer</button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
