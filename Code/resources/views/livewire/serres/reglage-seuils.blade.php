<div class="space-y-6">
    <div>
        <a href="{{ route('serres.index') }}" class="text-sm text-green-800 dark:text-green-400 underline hover:text-green-950 dark:hover:text-green-300">← Serres</a>
        <h1 class="mt-2 text-2xl font-semibold">Seuils de la serre « {{ $serre->nom }} »</h1>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            Une alerte est déclenchée quand une grandeur sort de son seuil sur deux mesures consécutives.
            Laissez un champ vide pour ne pas fixer cette borne.
        </p>
    </div>

    @if ($confirmation)
        <p class="rounded-md bg-green-50 dark:bg-green-950 px-4 py-2 text-sm text-green-800 dark:text-green-400" role="status">{{ $confirmation }}</p>
    @endif

    <form wire:submit="enregistrer" class="space-y-4 rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-5 shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="text-stone-600 dark:text-stone-400">
                <tr>
                    <th scope="col" class="pb-2 font-medium">Grandeur</th>
                    <th scope="col" class="pb-2 font-medium">Minimum</th>
                    <th scope="col" class="pb-2 font-medium">Maximum</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grandeurs as $grandeur)
                    <tr wire:key="seuil-{{ $grandeur->value }}" class="align-top">
                        <th scope="row" class="py-2 pr-4 font-medium">
                            {{ $grandeur->libelle() }}
                            <span class="block text-xs font-normal text-stone-500 dark:text-stone-400">
                                plage : {{ $grandeur->minimumPlausible() }} à {{ $grandeur->maximumPlausible() }} {{ $grandeur->unite() }}
                            </span>
                        </th>
                        @foreach (['minimum', 'maximum'] as $borne)
                            <td class="py-2 pr-4">
                                <label for="{{ $grandeur->value }}-{{ $borne }}" class="sr-only">{{ ucfirst($borne) }} — {{ $grandeur->libelle() }}</label>
                                <div class="flex items-center gap-2">
                                    <input id="{{ $grandeur->value }}-{{ $borne }}" type="text" inputmode="decimal"
                                           wire:model="valeurs.{{ $grandeur->value }}.{{ $borne }}"
                                           class="w-28 rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                                    <span class="text-stone-500 dark:text-stone-400">{{ $grandeur->unite() }}</span>
                                </div>
                                @error("valeurs.{$grandeur->value}.{$borne}")
                                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                                @enderror
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <button type="submit" class="rounded-md bg-green-800 px-4 py-2 text-sm font-medium text-white hover:bg-green-900">
            Enregistrer les seuils
        </button>
    </form>
</div>
