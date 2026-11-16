<?php

namespace App\Models;

use App\Models\Concerns\AppartientAStructure;
use Database\Factories\SerreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Serre surveillée (module C). Premier modèle métier : sert d'exemple pour l'isolation.
 *
 * @property-read int|null $boitiers_count Rempli par withCount('boitiers').
 */
class Serre extends Model
{
    use AppartientAStructure;

    /** @use HasFactory<SerreFactory> */
    use HasFactory;

    protected $fillable = [
        'structure_id',
        'nom',
        'emplacement',
    ];

    /**
     * @return HasMany<Boitier, $this>
     */
    public function boitiers(): HasMany
    {
        return $this->hasMany(Boitier::class);
    }

    /**
     * @return HasMany<Seuil, $this>
     */
    public function seuils(): HasMany
    {
        return $this->hasMany(Seuil::class);
    }

    /**
     * @return HasMany<Mesure, $this>
     */
    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }
}
