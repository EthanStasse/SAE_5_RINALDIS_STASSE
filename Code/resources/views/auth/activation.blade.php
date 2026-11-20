{{-- EF-D-02 : activation du compte. La personne choisit elle-même son mot de passe. --}}
<x-layouts.app titre="Activer mon compte">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Activer mon compte</h1>
        <p class="mt-1 text-sm text-stone-600">
            Choisissez votre mot de passe : au moins 12 caractères, avec une majuscule, une minuscule,
            un chiffre et un caractère spécial. Les espaces sont acceptés : une phrase de passe est une bonne idée.
        </p>

        <form method="POST" action="{{ route('activation.store') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username"
                       class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('email')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Mot de passe</label>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" maxlength="64"
                       class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('password')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirmer le mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="64"
                       class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
            </div>

            <button type="submit" class="w-full rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900">
                Activer mon compte
            </button>
        </form>
    </div>
</x-layouts.app>
