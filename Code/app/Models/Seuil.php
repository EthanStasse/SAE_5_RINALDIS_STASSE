<?php

namespace App\Models;

use App\Enums\Grandeur;
use App\Models\Concerns\AppartientAStructure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Seuil minimum et/ou maximum d'une grandeur pour une serre (EF-C-08).
 *
 * @property Grandeur $grandeur
 * @property float|null $minimum
 * @property float|null $maximum
 */
class Seuil extends Model
{
    use AppartientAStructure;

    protected $table = 'seuils';

    protected $fillable = [
        'grandeur',
        'minimum',
        'maximum',
    ];

    protected function casts(): array
    {
        return [
            'grandeur' => Grandeur::class,
            'minimum' => 'float',
            'maximum' => 'float',
        ];
    }

    /**
     * Vrai si la valeur sort du seuil. Une valeur absente (capteur en panne)
     * ne déclenche pas d'alerte de seuil : c'est l'alerte « capteur muet » qui s'en charge.
     */
    public function estDepassePar(?float $valeur): bool
    {
        if ($valeur === null) {
            return false;
        }

        return ($this->minimum !== null && $valeur < $this->minimum)
            || ($this->maximum !== null && $valeur > $this->maximum);
    }

    /**
     * @return BelongsTo<Serre, $this>
     */
    public function serre(): BelongsTo
    {
        return $this->belongsTo(Serre::class);
    }
}
