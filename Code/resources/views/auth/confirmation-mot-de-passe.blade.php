{{-- Mot de passe redemandé avant une action sensible (ex. activer la double authentification). --}}
<x-layouts.app titre="Confirmez votre mot de passe">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Confirmez votre mot de passe</h1>
        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">Pour votre sécurité, saisissez de nouveau votre mot de passe avant de continuer.</p>

        <form method="POST" action="{{ url('/user/confirm-password') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium">Mot de passe</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                       class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('password')
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900">
                Confirmer
            </button>
        </form>
    </div>
</x-layouts.app>
