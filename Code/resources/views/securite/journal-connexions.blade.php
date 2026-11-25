{{-- EF-D-11 : journal des connexions. ENF-RGPD : ce qui est gardé, et combien de temps, est rappelé. --}}
<x-layouts.app titre="Journal des connexions">
    <h1 class="text-2xl font-semibold">Journal des connexions</h1>

    <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">
        @if ($reseau)
            Toutes les connexions du réseau, y compris les essais sur des adresses qui n'ont pas de compte.
        @else
            Les connexions des comptes de votre Jardin.
        @endif
        Chaque ligne est conservée 12 mois. Les adresses e-mail et mots de passe saisis ne sont jamais enregistrés.
    </p>

    <div class="mt-6 overflow-x-auto rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 dark:border-stone-700 bg-stone-50 dark:bg-stone-950 text-stone-600 dark:text-stone-400">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">Date</th>
                    <th scope="col" class="px-4 py-2 font-medium">Compte</th>
                    <th scope="col" class="px-4 py-2 font-medium">Événement</th>
                    <th scope="col" class="px-4 py-2 font-medium">Adresse IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                @forelse ($lignes as $ligne)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2">{{ $ligne->created_at->timezone('Europe/Paris')->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2">
                            @if ($ligne->user)
                                {{ $ligne->user->name }}
                                <span class="block text-xs text-stone-500 dark:text-stone-400">{{ $ligne->user->email }}</span>
                            @else
                                <em class="text-stone-500 dark:text-stone-400">Adresse inconnue</em>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-green-50 dark:bg-green-950 text-green-800 dark:text-green-400' => $ligne->evenement === \App\Models\JournalConnexion::REUSSIE,
                                'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-200' => $ligne->evenement === \App\Models\JournalConnexion::ECHOUEE,
                                'bg-red-50 dark:bg-red-950 text-red-800 dark:text-red-300' => $ligne->evenement === \App\Models\JournalConnexion::BLOCAGE,
                            ])>{{ $ligne->libelle() }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono">{{ $ligne->ip }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500 dark:text-stone-400">Aucune connexion enregistrée.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lignes->links() }}</div>
</x-layouts.app>
