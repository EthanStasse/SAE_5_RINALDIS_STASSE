{{-- EF-D-11 : journal des connexions. ENF-RGPD : ce qui est gardé, et combien de temps, est rappelé. --}}
<x-layouts.app titre="Journal des connexions">
    <h1 class="text-2xl font-semibold">Journal des connexions</h1>

    <p class="mt-2 text-sm text-stone-600">
        @if ($reseau)
            Toutes les connexions du réseau, y compris les essais sur des adresses qui n'ont pas de compte.
        @else
            Les connexions des comptes de votre Jardin.
        @endif
        Chaque ligne est conservée 12 mois. Les adresses e-mail et mots de passe saisis ne sont jamais enregistrés.
    </p>

    <div class="mt-6 overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                <tr>
                    <th scope="col" class="px-4 py-2 font-medium">Date</th>
                    <th scope="col" class="px-4 py-2 font-medium">Compte</th>
                    <th scope="col" class="px-4 py-2 font-medium">Événement</th>
                    <th scope="col" class="px-4 py-2 font-medium">Adresse IP</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($lignes as $ligne)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2">{{ $ligne->created_at->timezone('Europe/Paris')->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2">
                            @if ($ligne->user)
                                {{ $ligne->user->name }}
                                <span class="block text-xs text-stone-500">{{ $ligne->user->email }}</span>
                            @else
                                <em class="text-stone-500">Adresse inconnue</em>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-green-50 text-green-800' => $ligne->evenement === \App\Models\JournalConnexion::REUSSIE,
                                'bg-amber-50 text-amber-800' => $ligne->evenement === \App\Models\JournalConnexion::ECHOUEE,
                                'bg-red-50 text-red-800' => $ligne->evenement === \App\Models\JournalConnexion::BLOCAGE,
                            ])>{{ $ligne->libelle() }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono">{{ $ligne->ip }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-stone-500">Aucune connexion enregistrée.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $lignes->links() }}</div>
</x-layouts.app>
