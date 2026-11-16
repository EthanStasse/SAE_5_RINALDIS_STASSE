<?php

namespace App\Notifications;

use App\Models\Alerte;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail envoyé à l'ouverture d'une alerte de serre (EF-C-11).
 */
class AlerteSerre extends Notification
{
    use Queueable;

    public function __construct(public readonly Alerte $alerte) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $serre = $this->alerte->serre;
        $nomSerre = $serre !== null ? $serre->nom : 'inconnue';
        $debut = $this->alerte->debut_le->timezone('Europe/Paris')->format('d/m/Y à H\hi');

        $message = (new MailMessage)
            ->subject("Alerte serre « {$nomSerre} » : {$this->alerte->type->libelle()}")
            ->greeting('Bonjour,')
            ->line($this->alerte->message)
            ->line("Début de l'alerte : le {$debut} (heure de Paris).");

        if ($serre !== null) {
            $message->action('Voir la serre', route('serres.show', $serre));
        }

        return $message
            ->line('Vous recevez ce message car vous pouvez régler les seuils des serres de ce Jardin.')
            ->salutation('Logiciel de gestion des Jardins de Cocagne');
    }
}
