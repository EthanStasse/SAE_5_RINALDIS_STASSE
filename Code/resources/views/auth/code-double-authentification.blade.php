{{-- EF-D-08 : deuxième étape de la connexion des administrateurs.
     Le code de secours est dans un <details> : pas de JavaScript nécessaire. --}}
<x-layouts.app titre="Double authentification">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Double authentification</h1>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            Ouvrez l'application d'authentification de votre téléphone et saisissez le code à 6 chiffres.
        </p>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="code" class="block text-sm font-medium">Code de vérification</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 text-center text-lg tracking-widest focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('code')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">
                Valider
            </button>
        </form>

        <details class="mt-6 text-sm" @error('recovery_code') open @enderror>
            <summary class="cursor-pointer text-green-800 dark:text-green-400 underline">Utiliser un code de secours</summary>
            <p class="mt-2 text-stone-600 dark:text-stone-400">Téléphone perdu ? Saisissez l'un des codes de secours notés lors de l'activation. Chaque code ne sert qu'une fois.</p>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-3 space-y-3">
                @csrf
                <label for="recovery_code" class="block font-medium">Code de secours</label>
                <input id="recovery_code" name="recovery_code" type="text" required autocomplete="off"
                       class="w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('recovery_code')
                    <p class="text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" class="w-full rounded-md border border-green-800 px-4 py-2 font-medium text-green-800 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-950">
                    Valider le code de secours
                </button>
            </form>
        </details>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="text-stone-600 dark:text-stone-400 underline">Revenir à la connexion</a>
        </p>
    </div>
</x-layouts.app>
