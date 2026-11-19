<?php

/*
 * Messages du mot de passe oublié (EF-D-04).
 * EF-D-07 : « sent » et « throttled » sont identiques, ainsi que « token » et
 * « user » : un message différent révélerait si l'adresse a un compte.
 */
$lienEnvoye = "Si un compte correspond à cette adresse, un lien de réinitialisation vient d'y être envoyé. Il est valable 60 minutes.";
$lienInvalide = 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.';

return [
    'reset' => 'Votre mot de passe a été modifié. Vous pouvez vous connecter.',
    'sent' => $lienEnvoye,
    'throttled' => $lienEnvoye,
    'token' => $lienInvalide,
    'user' => $lienInvalide,
];
