<?php

namespace App\Models;

use App\Models\Concerns\AppartientAStructure;
use Database\Factories\SerreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Serre surveillée (module C). Premier modèle métier : sert d'exemple pour l'isolation.
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
}
