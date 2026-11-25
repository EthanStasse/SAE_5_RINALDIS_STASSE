{{-- EF-D-14 : page adhérent minimale. EF-D-03 : changement de mot de passe avec l'ancien.
     Les coordonnées sont en lecture seule : l'e-mail est l'identifiant et le canal de
     récupération ; le modifier passe par l'équipe du Jardin (évite la prise de compte). --}}
@php
    $compte = auth()->user();
    $jardins = \App\Models\Structure::query()->whereIn('id', $compte->structureIds())->orderBy('nom_commercial')->pluck('nom_commercial');
@endphp

<x-layouts.app titre="Mon compte">
    <div class="mx-auto max-w-xl space-y-6">
        <h1 class="text-2xl font-semibold">Mon compte</h1>

        <section class="rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Mes coordonnées</h2>
            <dl class="mt-4 grid grid-cols-3 gap-y-2 text-sm">
                <dt class="text-stone-600 dark:text-stone-400">Nom</dt>
                <dd class="col-span-2">{{ $compte->name }}</dd>
                <dt class="text-stone-600 dark:text-stone-400">Adresse e-mail</dt>
                <dd class="col-span-2">{{ $compte->email }}</dd>
                <dt class="text-stone-600 dark:text-stone-400">Jardin(s)</dt>
                <dd class="col-span-2">{{ $jardins->join(', ') }}</dd>
            </dl>
            <p class="mt-4 text-sm text-stone-600 dark:text-stone-400">Une coordonnée à corriger ? Contactez l'équipe de votre Jardin.</p>
        </section>

        <section class="rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Changer mon mot de passe</h2>

            @if (session('status') === 'password-updated')
                <p class="mt-4 rounded-md bg-green-50 dark:bg-green-950 px-3 py-2 text-sm text-green-800 dark:text-green-400" role="status">
                    Votre mot de passe a été modifié. Vos autres sessions ouvertes ont été fermées.
                </p>
            @endif

            <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
                Au moins 12 caractères, avec une majuscule, une minuscule, un chiffre et un caractère spécial.
            </p>

            <form method="POST" action="{{ url('/user/password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="block text-sm font-medium">Mot de passe actuel</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                           class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                    @error('current_password', 'updatePassword')
                        <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Nouveau mot de passe</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password" maxlength="64"
                           class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                    @error('password', 'updatePassword')
                        <p class="mt-1 text-sm text-red-700 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium">Confirmer le nouveau mot de passe</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="64"
                           class="mt-1 w-full rounded-md border border-stone-300 dark:border-stone-600 dark:bg-stone-800 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                </div>

                <button type="submit" class="rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900">
                    Changer mon mot de passe
                </button>
            </form>
        </section>

        <section class="rounded-xl border border-stone-200 dark:border-stone-700 bg-white dark:bg-stone-900 p-6 shadow-sm">
            <h2 class="text-lg font-semibold">Double authentification</h2>
            <p class="mt-2 text-sm">
                @if ($compte->hasEnabledTwoFactorAuthentication())
                    Activée sur votre compte.
                @else
                    Non activée.
                @endif
                <a href="{{ route('double-authentification') }}" class="text-green-800 dark:text-green-400 underline">Gérer</a>
            </p>
        </section>
    </div>
</x-layouts.app>
