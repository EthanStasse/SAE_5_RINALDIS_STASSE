<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

/**
 * EF-D-07 : une seule réponse pour toutes les demandes de lien, que le lien
 * soit envoyé, que l'adresse soit inconnue ou que la demande soit trop
 * rapprochée. L'écran ne révèle donc jamais si un compte existe.
 */
class LienReinitialisationDemande implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        $message = __('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
