<?php

namespace App\Models;

// À ADAPTER : remplace par le vrai namespace de ton trait.
use App\Models\Concerns\AppartientAStructure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mesure extends Model
{
    use AppartientAStructure;

    protected $table = 'mesures';

    // Pas de created_at/updated_at : une mesure ne se modifie jamais.
    // L'heure de réception est dans recu_le (valeur par défaut en base).
    public $timestamps = false;

    protected $fillable = ['uuid', 'mesure_le', 'temperature', 'humidite', 'luminosite'];

    protected function casts(): array
    {
        return [
            'mesure_le' => 'immutable_datetime',
            'recu_le' => 'immutable_datetime',
            // decimal est renvoyé en chaîne par PostgreSQL : on le convertit en nombre.
            'temperature' => 'float',
            'humidite' => 'float',
            'luminosite' => 'float',
        ];
    }

    public function serre(): BelongsTo
    {
        return $this->belongsTo(Serre::class);
    }

    public function boitier(): BelongsTo
    {
        return $this->belongsTo(Boitier::class);
    }
}
