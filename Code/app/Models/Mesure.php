<?php

namespace App\Models;

use App\Models\Concerns\AppartientAStructure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mesure envoyée par un boîtier (module C, EF-C-03 et EF-C-04).
 * Une grandeur vaut null si son capteur était en erreur.
 */
class Mesure extends Model
{
    use AppartientAStructure;

    protected $table = 'mesures';

    /** Une mesure ne se modifie jamais : l'heure de réception est dans recu_le. */
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'mesure_le',
        'temperature',
        'humidite',
        'luminosite',
    ];

    protected function casts(): array
    {
        return [
            'mesure_le' => 'immutable_datetime',
            'recu_le' => 'immutable_datetime',
            // PostgreSQL renvoie les decimal sous forme de chaîne : on les convertit.
            'temperature' => 'float',
            'humidite' => 'float',
            'luminosite' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Serre, $this>
     */
    public function serre(): BelongsTo
    {
        return $this->belongsTo(Serre::class);
    }

    /**
     * @return BelongsTo<Boitier, $this>
     */
    public function boitier(): BelongsTo
    {
        return $this->belongsTo(Boitier::class);
    }
}
