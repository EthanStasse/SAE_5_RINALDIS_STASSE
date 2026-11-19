<?php

/*
 * EF-D-05 : politique de mot de passe.
 * Création, réinitialisation et changement passent tous par le trait
 * PasswordValidationRules : on teste la règle à cet endroit.
 */

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Support\Facades\Validator;

/** @return array<int, string> les erreurs du champ password */
function erreursMotDePasse(string $motDePasse, ?string $confirmation = null): array
{
    $regles = new class
    {
        use PasswordValidationRules;

        /** @return array<int, mixed> */
        public function pour(): array
        {
            return $this->passwordRules();
        }
    };

    return Validator::make(
        ['password' => $motDePasse, 'password_confirmation' => $confirmation ?? $motDePasse],
        ['password' => $regles->pour()],
    )->errors()->get('password');
}

it('accepte un mot de passe conforme', function (string $motDePasse) {
    expect(erreursMotDePasse($motDePasse))->toBeEmpty();
})->with([
    '12 caractères tout juste' => 'Abcdefgh12!x',
    'phrase de passe avec espaces' => 'Mon jardin Bio 2026',
    'lettres accentuées' => 'Éléphant-Vert-2026',
    '64 caractères tout juste' => 'Aa1!'.str_repeat('x', 60),
]);

it('refuse un mot de passe faible', function (string $motDePasse, string $message) {
    expect(erreursMotDePasse($motDePasse))->toContain($message);
})->with([
    '11 caractères' => ['Abcdefg12!x', 'Le champ mot de passe doit contenir au moins 12 caractères.'],
    'sans majuscule' => ['jardin-cocagne-2026', 'Le champ mot de passe doit contenir au moins une majuscule et une minuscule.'],
    'sans minuscule' => ['JARDIN-COCAGNE-2026', 'Le champ mot de passe doit contenir au moins une majuscule et une minuscule.'],
    'sans chiffre' => ['Jardin-Cocagne-Bio', 'Le champ mot de passe doit contenir au moins un chiffre.'],
    'sans caractère spécial' => ['JardinCocagne2026', 'Le champ mot de passe doit contenir au moins un caractère spécial.'],
    '65 caractères' => ['Aa1!'.str_repeat('x', 61), 'Le champ mot de passe ne doit pas dépasser 64 caractères.'],
]);

it('exige une confirmation identique', function () {
    expect(erreursMotDePasse('Abcdefgh12!x', 'Abcdefgh12!y'))
        ->toContain('La confirmation du champ mot de passe ne correspond pas.');
});