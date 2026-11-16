<x-layouts.app titre="Connexion">
    <div class="mx-auto mt-12 max-w-sm rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Connexion</h1>
        <p class="mt-1 text-sm text-stone-600">Logiciel de gestion des Jardins de Cocagne</p>

        @if (session('status'))
            <p class="mt-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('email')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium">Mot de passe</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2 focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('password')
                    <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" class="rounded border-stone-300">
                Rester connecté
            </label>

            <button type="submit" class="w-full rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2">
                Se connecter
            </button>
        </form>
    </div>
</x-layouts.app>
