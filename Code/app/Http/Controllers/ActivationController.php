<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * EF-D-02 : activation d'un compte créé par l'équipe. La personne choisit son
 * mot de passe (politique EF-D-05) grâce au lien reçu par e-mail.
 * ENF-SEC-06 : jeton haché, usage unique, 72 heures (broker « activations »).
 */
class ActivationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, string $token): View
    {
        return view('auth.activation', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // La politique est vérifiée AVANT de consommer le lien : une saisie
        // refusée ne fait pas perdre le lien.
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $statut = Password::broker('activations')->reset(
            [
                'email' => Str::lower((string) $request->input('email')),
                'password' => (string) $request->input('password'),
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $request->input('token'),
            ],
            function (User $compte, string $motDePasse): void {
                // Avoir reçu et ouvert le lien prouve que l'adresse est la bonne.
                $compte->forceFill([
                    'password' => $motDePasse,
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();
            },
        );

        if ($statut === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __('auth.activated'));
        }

        // EF-D-07 : même message pour un jeton faux, expiré, déjà utilisé ou une adresse sans compte.
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __('auth.activation_invalid')]);
    }
}
