{{-- EF-D-04 : choix du nouveau mot de passe depuis le lien reçu par e-mail.
     Le jeton voyage dans un champ caché ; la politique EF-D-05 est rappelée. --}}
<x-layouts.app titre="Nouveau mot de passe">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Nouveau mot de passe</h1>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            Au moins 12 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.
            Les espaces sont acceptés : une phrase de passe est une bonne idée.
        </p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->query('email')) }}" required autocomplete="username"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('email')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Nouveau mot de passe</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" maxlength="64"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('password')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirmer le mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="64"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
            </div>

            <button type="submit" class="w-full rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">
                Enregistrer le mot de passe
            </button>
        </form>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-green-800 dark:text-green-400 underline hover:text-green-900 dark:hover:text-green-300">Demander un nouveau lien</a>
        </p>
    </div>
</x-layouts.app>
