<?php

return [
    'failed' => 'Ces identifiants ne correspondent à aucun compte actif.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Réessayez dans :seconds secondes.',
    // EF-D-06 et EF-D-07 : même message que l'adresse existe ou non.
    'blocked' => "Trop d'échecs de connexion. Pour votre sécurité, réessayez dans :minutes min.",
    // EF-D-10 : session expirée (inactivité ou 12 heures).
    'expired' => 'Votre session a expiré. Reconnectez-vous pour continuer.',
    // EF-D-03 : ancien mot de passe faux lors d'un changement.
    'current_password' => 'Le mot de passe actuel est incorrect.',
];
