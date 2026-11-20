<?php

namespace App\Models;

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
}
