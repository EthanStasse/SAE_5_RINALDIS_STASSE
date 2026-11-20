<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * EF-D-12 : prévient le propriétaire du compte que son mot de passe a changé.
 * Le nouveau mot de passe n'est jamais envoyé. Si ce n'est pas lui, il sait quoi
 * faire : choisir un nouveau mot de passe et prévenir l'équipe de son Jardin.
 */
class MotDePasseModifie extends Notification
{
    public function __construct(private string $quand) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        // Anti-phishing : lien construit depuis APP_URL, jamais depuis la requête.
        $lien = rtrim((string) config('app.url'), '/').route('password.request', [], false);

        return (new MailMessage)
            ->subject('Votre mot de passe a été modifié')
            ->greeting('Bonjour,')
            ->line("Le mot de passe de votre compte du logiciel de gestion des Jardins de Cocagne a été modifié le {$this->quand}.")
            ->line("Si c'est vous, vous n'avez rien à faire.")
            ->line("Si ce n'est pas vous, choisissez tout de suite un nouveau mot de passe et prévenez l'équipe de votre Jardin.")
            ->action('Choisir un nouveau mot de passe', $lien)
            ->salutation('Les Jardins de Cocagne');
    }
}
