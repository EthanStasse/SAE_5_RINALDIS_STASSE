{{-- EF-D-08 : activation de la double authentification (TOTP).
     Trois états : pas encore activée, en attente du premier code, activée. --}}
@php($compte = auth()->user())

<x-layouts.app titre="Double authentification">
    <div class="mx-auto max-w-xl space-y-6 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold">Double authentification</h1>

        @if (session('avertissement'))
            <p class="rounded-md border-2 border-amber-400 bg-amber-50 px-3 py-2 text-sm" role="alert">{{ session('avertissement') }}</p>
        @endif
        
        @if (! $compte->two_factor_secret)
            {{-- 1. Pas encore activée --}}
            <p class="text-sm text-stone-600">
                En plus de votre mot de passe, un code à 6 chiffres affiché par une application de votre téléphone
                (Google Authenticator, Microsoft Authenticator, FreeOTP…) vous sera demandé à chaque connexion.
                Un mot de passe volé ne suffira plus à entrer dans votre compte.
            </p>

            <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                @csrf
                <button type="submit" class="rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900">
                    Activer la double authentification
                </button>
            </form>
        @elseif (! $compte->two_factor_confirmed_at)
            {{-- 2. En attente du premier code --}}
            <ol class="list-decimal space-y-1 pl-5 text-sm text-stone-700">
                <li>Ouvrez l'application d'authentification de votre téléphone.</li>
                <li>Scannez ce QR code, ou saisissez la clé à la main.</li>
                <li>Tapez ci-dessous le code à 6 chiffres affiché par l'application.</li>
            </ol>

            {{-- SVG fabriqué par le serveur à partir du secret : aucune saisie d'utilisateur, donc sans risque XSS. --}}
            <div class="inline-block rounded-md border border-stone-200 bg-white p-3">
                {!! $compte->twoFactorQrCodeSvg() !!}
            </div>

            <p class="text-sm">
                Clé à saisir à la main :
                <code class="rounded bg-stone-100 px-2 py-1 font-mono tracking-wider">{{ decrypt($compte->two_factor_secret) }}</code>
            </p>

            <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="space-y-3">
                @csrf
                <label for="code" class="block text-sm font-medium">Code de vérification</label>
                <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autocomplete="one-time-code"
                       class="w-40 rounded-md border border-stone-300 px-3 py-2 text-center text-lg tracking-widest focus:border-green-700 focus:outline-none focus:ring-1 focus:ring-green-700">
                @error('code', 'confirmTwoFactorAuthentication')
                    <p class="text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
                <div>
                    <button type="submit" class="rounded-md bg-green-800 px-4 py-2 font-medium text-white hover:bg-green-900">
                        Confirmer
                    </button>
                </div>
            </form>
        @else
            {{-- 3. Activée --}}
            <p class="rounded-md bg-green-50 px-3 py-2 text-sm text-green-800" role="status">
                La double authentification est activée sur votre compte.
            </p>

            {{-- Les codes de secours ne s'affichent qu'une fois, juste après la confirmation ou une régénération. --}}
            @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                <div class="rounded-md border-2 border-amber-400 bg-amber-50 p-4" role="alert">
                    <h2 class="font-semibold">Codes de secours</h2>
                    <p class="mt-1 text-sm text-stone-700">
                        Notez-les maintenant et rangez-les en lieu sûr : ils ne seront plus affichés.
                        Chacun permet une connexion si vous perdez votre téléphone, une seule fois.
                    </p>
                    <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                        @foreach ($compte->recoveryCodes() as $codeSecours)
                            <li class="rounded bg-white px-2 py-1">{{ $codeSecours }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}">
                @csrf
                <button type="submit" class="text-sm text-green-800 underline">
                    Générer de nouveaux codes de secours (les anciens ne fonctionneront plus)
                </button>
            </form>
        @endif
    </div>
</x-layouts.app>
