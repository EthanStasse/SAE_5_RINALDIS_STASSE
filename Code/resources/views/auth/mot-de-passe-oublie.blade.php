{{-- EF-D-04 : demande du lien de réinitialisation.
     EF-D-07 : le même message s'affiche que l'adresse ait un compte ou non. --}}
<x-layouts.app titre="Mot de passe oublié">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Mot de passe oublié</h1>
        <p class="mt-1 text-sm text-stone-600">
            Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.
        </p>

        @if (session('status'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('email')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">
                Recevoir le lien
            </button>
        </form>

        <p class="mt-4 text-center text-sm">
            <a href="{{ route('login') }}" class="text-green-800 underline hover:text-green-900">Revenir à la connexion</a>
        </p>
    </div>
</x-layouts.app>
