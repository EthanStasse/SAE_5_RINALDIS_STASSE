<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * EF-D-02 : e-mail d'activation d'un compte créé par l'équipe.
 * Aucun mot de passe n'y figure : la personne le choisit elle-même via le lien.
 */
class ActivationCompte extends Notification
{
    public function __construct(private string $jeton, private string $jardin) {}

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
        $lien = rtrim((string) config('app.url'), '/')
            .'/activation/'.$this->jeton.'?email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Activez votre compte des Jardins de Cocagne')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line("Un compte a été créé pour vous par l'équipe du {$this->jardin} sur le logiciel de gestion des Jardins de Cocagne.")
            ->line("Pour l'activer, choisissez vous-même votre mot de passe : personne d'autre ne le connaîtra.")
            ->action('Activer mon compte', $lien)
            ->line("Ce lien est valable 72 heures et ne fonctionne qu'une seule fois.")
            ->line('Si vous ne vous attendiez pas à ce message, ignorez-le ou contactez votre Jardin.')
            ->salutation('Les Jardins de Cocagne');
    }
}
