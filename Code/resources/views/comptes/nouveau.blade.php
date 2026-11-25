{{-- EF-D-02 : création d'un compte par l'équipe. Aucun champ de mot de passe :
     la personne le choisit elle-même grâce au lien d'activation. --}}
<x-layouts.app titre="Créer un compte">
    <div class="mx-auto max-w-xl rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Créer un compte</h1>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            La personne recevra un e-mail avec un lien pour choisir elle-même son mot de passe.
            Le lien est valable 72 heures. Aucun mot de passe n'est envoyé par e-mail.
        </p>

        @if (session('status'))
            <p class="mt-4 rounded-md bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-800 dark:text-green-400" role="status">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('comptes.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium">Nom et prénom</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" autocomplete="off"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('name')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('email')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="role" class="block text-sm font-medium">Rôle dans ce Jardin</label>
                <select id="role" name="role" required
                        class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                    @foreach ($roles as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(old('role', 'adherent') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
                @error('role')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900">
                Créer le compte et envoyer le lien
            </button>
        </form>
    </div>
</x-layouts.app>
