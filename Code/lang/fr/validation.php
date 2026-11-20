<?php

/*
 * Messages de validation en français. Seules les règles utilisées sont
 * traduites : une clé absente retombe sur l'anglais (fallback_locale).
 */
return [
    'required' => 'Le champ :attribute est obligatoire.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'string' => 'Le champ :attribute doit être un texte.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'unique' => 'Cette :attribute est déjà utilisée par un compte.',
    'in' => "La valeur choisie pour le champ :attribute n'est pas autorisée.",
    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'max' => [
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],

    // EF-D-05 : politique de mot de passe.
    'password' => [
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un caractère spécial.',
    ],

    'attributes' => [
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'name' => 'nom',
        'role' => 'rôle',
    ],
];
