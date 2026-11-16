<div class="space-y-6">
    <div>
        <a href="{{ route('serres.index') }}" class="text-sm text-green-800 underline hover:text-green-950">← Serres</a>
        <h1 class="mt-2 text-2xl font-semibold">Boîtiers de la serre « {{ $serre->nom }} »</h1>
        <p class="mt-1 text-sm text-stone-600">Chaque boîtier capteur s'authentifie auprès de l'application avec son propre jeton.</p>
    </div>

    @if ($confirmation)
        <p class="rounded-md bg-green-50 px-4 py-2 text-sm text-green-800" role="status">{{ $confirmation }}</p>
    @endif

    @if ($jetonAffiche)
        <div class="space-y-3 rounded-xl border-2 border-amber-400 bg-amber-50 p-5" role="alert" x-data="{ copie: false }">
            <h2 class="font-semibold text-amber-900">Jeton du boîtier « {{ $boitierDuJeton }} »</h2>
            <p class="text-sm text-amber-900">
                Copiez-le maintenant dans la configuration du boîtier (<code>JETON_BOITIER</code> dans <code>secrets.h</code>).
                <strong>Il ne sera plus jamais affiché</strong> : seule son empreinte est conservée.
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <code class="break-all rounded bg-white px-3 py-2 font-mono text-sm select-all" x-ref="jeton">{{ $jetonAffiche }}</code>
                <button type="button" class="rounded-md border border-amber-500 px-3 py-2 text-sm hover:bg-amber-100"
                        x-on:click="navigator.clipboard.writeText($refs.jeton.textContent); copie = true">
                    <span x-show="! copie">Copier</span>
                    <span x-show="copie" x-cloak>Copié ✓</span>
                </button>
            </div>
            <button type="button" wire:click="masquerJeton" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700">
                J'ai copié le jeton
            </button>
        </div>
    @endif

    <form wire:submit="creer" class="flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        <div>
            <label for="nom" class="block text-sm font-medium">Nouveau boîtier</label>
            <input id="nom" type="text" wire:model="nom" maxlength="100" required placeholder="Ex. : Boîtier ESP32 nord"
                   class="mt-1 w-72 max-w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
            @error('nom')
                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="rounded-md bg-green-800 px-4 py-2 text-sm font-medium text-white hover:bg-green-900">
            Déclarer le boîtier
        </button>
    </form>

    @if ($boitiers->isEmpty())
        <p class="text-stone-600">Aucun boîtier n'est déclaré dans cette serre.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-medium">Nom</th>
                        <th scope="col" class="px-4 py-2 font-medium">Jeton</th>
                        <th scope="col" class="px-4 py-2 font-medium">État</th>
                        <th scope="col" class="px-4 py-2 font-medium">Dernière communication</th>
                        <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($boitiers as $boitier)
                        <tr wire:key="boitier-{{ $boitier->id }}">
                            <td class="px-4 py-2 font-medium">{{ $boitier->nom }}</td>
                            <td class="px-4 py-2 font-mono text-stone-600">{{ $boitier->jeton_prefixe }}…</td>
                            <td class="px-4 py-2">
                                @if ($boitier->estRevoque())
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-red-800">Révoqué le {{ $boitier->revoque_le->timezone('Europe/Paris')->format('d/m/Y à H:i') }}</span>
                                @else
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-green-800">Actif</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-stone-600">
                                {{ $boitier->derniere_communication_le?->timezone('Europe/Paris')->format('d/m/Y à H:i') ?? 'Jamais' }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" wire:click="regenerer({{ $boitier->id }})"
                                        wire:confirm="Générer un nouveau jeton pour « {{ $boitier->nom }} » ? L'ancien cessera immédiatement de fonctionner."
                                        class="text-green-800 underline hover:text-green-950">
                                    {{ $boitier->estRevoque() ? 'Réactiver (nouveau jeton)' : 'Nouveau jeton' }}
                                </button>
                                @unless ($boitier->estRevoque())
                                    <button type="button" wire:click="revoquer({{ $boitier->id }})"
                                            wire:confirm="Révoquer « {{ $boitier->nom }} » ? Ses envois seront refusés."
                                            class="ml-3 text-red-700 underline hover:text-red-900">Révoquer</button>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
