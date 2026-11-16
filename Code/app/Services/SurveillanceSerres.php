<?php

namespace App\Services;

use App\Enums\TypeAlerte;
use App\Models\Alerte;
use App\Models\Boitier;
use App\Models\Serre;
use App\Models\Seuil;
use App\Models\Structure;
use App\Models\User;
use App\Notifications\AlerteSerre;
use App\Support\StructureCourante;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Détection des alertes des serres.
 *
 *  - EF-C-09 : une grandeur sort de son seuil sur 2 mesures consécutives.
 *    Une seule mesure hors seuil (parasite, porte ouverte un instant) ne
 *    déclenche rien. L'alerte se ferme dès qu'une mesure revient dans le seuil.
 *  - EF-C-10 : un boîtier actif n'a rien envoyé depuis 30 minutes.
 *    L'alerte se ferme dès que le boîtier communique à nouveau.
 *  - EF-C-11 : chaque nouvelle alerte est envoyée par e-mail aux comptes
 *    du Jardin qui peuvent régler les seuils (« gerer-seuils »).
 *
 * Toutes les requêtes passent par le filtre par structure : le service
 * travaille toujours dans le Jardin courant. Hors requête HTTP (tâche
 * planifiée), surveillerTout() fixe le Jardin courant pour chaque structure.
 */
class SurveillanceSerres
{
    public const MESURES_CONSECUTIVES = 2;

    public const SILENCE_MAX_MINUTES = 30;

    public const PERMISSION_DESTINATAIRES = 'gerer-seuils';

    /** @var array{ouvertes: int, fermees: int, notifiees: int} */
    private array $bilan = ['ouvertes' => 0, 'fermees' => 0, 'notifiees' => 0];

    public function __construct(private StructureCourante $courante) {}

    /**
     * Surveille tous les Jardins, l'un après l'autre (tâche planifiée).
     *
     * @return array{structures: int, ouvertes: int, fermees: int, notifiees: int}
     */
    public function surveillerTout(): array
    {
        $this->bilan = ['ouvertes' => 0, 'fermees' => 0, 'notifiees' => 0];
        $identifiants = Structure::query()->orderBy('id')->pluck('id');

        foreach ($identifiants as $id) {
            $this->courante->definir((int) $id);
            setPermissionsTeamId((int) $id);
            try {
                $this->surveillerJardinCourant();
                $this->notifierNouvellesAlertes((int) $id);
            } finally {
                $this->courante->oublier();
                setPermissionsTeamId(null);
            }
        }

        return ['structures' => $identifiants->count(), ...$this->bilan];
    }

    public function surveillerJardinCourant(): void
    {
        foreach (Serre::query()->with('seuils')->get() as $serre) {
            $this->verifierSeuils($serre);
        }
        $this->verifierCapteursMuets();
    }

    /**
     * EF-C-09 : ouvre ou ferme les alertes de seuil d'une serre.
     */
    public function verifierSeuils(Serre $serre): void
    {
        $seuils = $serre->seuils()->get()->keyBy(fn (Seuil $seuil) => $seuil->grandeur->value);

        // Seuil supprimé entre-temps : ses alertes n'ont plus de raison d'être.
        $orphelines = $serre->alertes()->ouvertes()
            ->where('type', TypeAlerte::Seuil->value)
            ->whereNotIn('grandeur', $seuils->keys()->all())
            ->get();
        foreach ($orphelines as $alerte) {
            $this->fermer($alerte);
        }

        foreach ($seuils as $seuil) {
            $champ = $seuil->grandeur->value;

            // Les dernières mesures où CETTE grandeur a une valeur (capteur en panne = ignorée).
            $dernieres = $serre->mesures()
                ->whereNotNull($champ)
                ->latest('mesure_le')
                ->limit(self::MESURES_CONSECUTIVES)
                ->pluck($champ)
                ->map(fn ($valeur) => (float) $valeur);

            $ouverte = $serre->alertes()->ouvertes()
                ->where('type', TypeAlerte::Seuil->value)
                ->where('grandeur', $champ)
                ->first();

            if ($dernieres->isEmpty()) {
                continue;
            }

            $derniere = $dernieres->first();
            $toutesHorsSeuil = $dernieres->count() === self::MESURES_CONSECUTIVES
                && $dernieres->every(fn (float $valeur) => $seuil->estDepassePar($valeur));

            if ($ouverte === null && $toutesHorsSeuil) {
                $this->ouvrir($serre, [
                    'type' => TypeAlerte::Seuil,
                    'grandeur' => $seuil->grandeur,
                    'valeur' => $derniere,
                    'message' => $this->messageSeuil($serre, $seuil, $derniere),
                ]);
            } elseif ($ouverte !== null && ! $seuil->estDepassePar($derniere)) {
                $this->fermer($ouverte);
            } elseif ($ouverte !== null) {
                // Toujours hors seuil : on garde la valeur la plus récente.
                $ouverte->valeur = $derniere;
                $ouverte->save();
            }
        }
    }

    /**
     * EF-C-10 : ouvre ou ferme les alertes « capteur muet » du Jardin courant.
     */
    public function verifierCapteursMuets(): void
    {
        $limite = now()->subMinutes(self::SILENCE_MAX_MINUTES);

        foreach (Boitier::query()->with('serre')->get() as $boitier) {
            // Un boîtier jamais connecté est muet depuis sa création.
            $derniere = $boitier->derniere_communication_le ?? $boitier->created_at;
            $muet = ! $boitier->estRevoque() && $derniere !== null && $derniere->lt($limite);
            $ouverte = $this->alerteMuetOuverte($boitier);

            if ($muet && $ouverte === null && $boitier->serre !== null) {
                $depuis = $derniere->timezone('Europe/Paris')->format('d/m à H\hi');
                $this->ouvrir($boitier->serre, [
                    'type' => TypeAlerte::CapteurMuet,
                    'boitier_id' => $boitier->id,
                    'message' => "Le boîtier « {$boitier->nom} » de la serre « {$boitier->serre->nom} » "
                        ."n'a rien envoyé depuis le {$depuis}.",
                ]);
            } elseif (! $muet && $ouverte !== null) {
                $this->fermer($ouverte);
            }
        }
    }

    /**
     * Appelé par l'API à chaque message d'un boîtier : il n'est plus muet.
     */
    public function signalerCommunication(Boitier $boitier): void
    {
        $ouverte = $this->alerteMuetOuverte($boitier);
        if ($ouverte !== null) {
            $this->fermer($ouverte);
        }
    }

    /**
     * EF-C-11 : envoie par e-mail les alertes du Jardin courant pas encore notifiées.
     *
     * Les e-mails partent de la tâche planifiée (au plus 1 minute après
     * l'ouverture) et non de l'API : la réponse au boîtier reste rapide, et un
     * e-mail qui échoue est simplement retenté à la minute suivante.
     */
    public function notifierNouvellesAlertes(int $structureId): void
    {
        $alertes = Alerte::query()->ouvertes()->whereNull('notifiee_le')->with('serre')->orderBy('id')->get();
        if ($alertes->isEmpty()) {
            return;
        }

        $destinataires = $this->destinataires($structureId);

        foreach ($alertes as $alerte) {
            try {
                Notification::send($destinataires, new AlerteSerre($alerte));
            } catch (Throwable $erreur) {
                // Serveur d'e-mail indisponible : on réessaiera à la prochaine minute.
                report($erreur);

                continue;
            }
            $alerte->notifiee_le = now()->toImmutable();
            $alerte->save();
            $this->bilan['notifiees']++;
        }
    }

    /**
     * Comptes actifs ayant, dans CE Jardin, un rôle qui donne « gerer-seuils ».
     *
     * @return Collection<int, User>
     */
    public function destinataires(int $structureId): Collection
    {
        $tables = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key');

        return User::query()
            ->where('actif', true)
            ->whereIn('id', function (Builder $requete) use ($tables, $morphKey, $structureId) {
                $requete->select("mhr.{$morphKey}")
                    ->from("{$tables['model_has_roles']} as mhr")
                    ->join("{$tables['role_has_permissions']} as rhp", 'rhp.role_id', '=', 'mhr.role_id')
                    ->join("{$tables['permissions']} as p", 'p.id', '=', 'rhp.permission_id')
                    ->where('mhr.model_type', (new User)->getMorphClass())
                    ->where('mhr.structure_id', $structureId)
                    ->where('p.name', self::PERMISSION_DESTINATAIRES);
            })
            ->orderBy('id')
            ->get();
    }

    public function serreEnAlerte(Serre $serre): bool
    {
        return $serre->alertes()->ouvertes()->exists();
    }

    private function alerteMuetOuverte(Boitier $boitier): ?Alerte
    {
        return Alerte::query()->ouvertes()
            ->where('type', TypeAlerte::CapteurMuet->value)
            ->where('boitier_id', $boitier->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributs
     */
    private function ouvrir(Serre $serre, array $attributs): void
    {
        $alerte = new Alerte([...$attributs, 'debut_le' => now()]);
        $alerte->serre_id = $serre->id;

        try {
            // structure_id est rempli par le trait AppartientAStructure.
            $alerte->save();
            $this->bilan['ouvertes']++;
        } catch (UniqueConstraintViolationException) {
            // Une autre exécution vient d'ouvrir la même alerte : rien à faire.
        }
    }

    private function fermer(Alerte $alerte): void
    {
        $alerte->fermer();
        $this->bilan['fermees']++;
    }

    private function messageSeuil(Serre $serre, Seuil $seuil, float $valeur): string
    {
        $grandeur = $seuil->grandeur;
        $minimum = $seuil->minimum !== null ? $grandeur->formater($seuil->minimum) : '…';
        $maximum = $seuil->maximum !== null ? $grandeur->formater($seuil->maximum) : '…';
        $sens = $seuil->minimum !== null && $valeur < $seuil->minimum ? 'trop basse' : 'trop haute';

        return "{$grandeur->libelle()} {$sens} dans la serre « {$serre->nom} » : "
            ."{$grandeur->formater($valeur)} (seuil de {$minimum} à {$maximum}).";
    }
}
