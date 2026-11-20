<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EF-D-11 : une ligne du journal des connexions.
 * ENF-RGPD : compte visé (ou null), événement, adresse IP, date. Rien d'autre.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $evenement
 * @property string|null $ip
 * @property Carbon $created_at
 */
class JournalConnexion extends Model
{
    use MassPrunable;

    public const REUSSIE = 'connexion_reussie';

    public const ECHOUEE = 'connexion_echouee';

    public const BLOCAGE = 'compte_bloque';

    /** Un journal ne se modifie pas : pas de colonne updated_at. */
    public const UPDATED_AT = null;

    protected $table = 'journal_connexions';

    protected $fillable = ['user_id', 'evenement', 'ip'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Écrit une ligne : le compte visé (null pour une adresse inconnue) et l'IP de la requête. */
    public static function noter(string $evenement, ?int $userId): void
    {
        static::query()->create([
            'user_id' => $userId,
            'evenement' => $evenement,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Libellé en français, pour la page de consultation. */
    public function libelle(): string
    {
        return match ($this->evenement) {
            self::REUSSIE => 'Connexion réussie',
            self::ECHOUEE => 'Connexion échouée',
            self::BLOCAGE => 'Compte bloqué 15 min',
            default => $this->evenement,
        };
    }

    /** ENF-RGPD : durée de conservation du journal. */
    public const DUREE_CONSERVATION_MOIS = 12;

    /**
     * ENF-RGPD : lignes à supprimer (php artisan model:prune, planifié chaque nuit).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subMonths(self::DUREE_CONSERVATION_MOIS));
    }
}
