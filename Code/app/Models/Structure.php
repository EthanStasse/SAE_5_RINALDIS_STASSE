<?php

namespace App\Models;

use Database\Factories\StructureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un Jardin de Cocagne (CDC §1). Cette table n'est pas filtrée par structure.
 */
class Structure extends Model
{
    /** @use HasFactory<StructureFactory> */
    use HasFactory;

    protected $fillable = [
        'nom_commercial',
        'raison_sociale',
        'logo_chemin',
        'siege_social',
        'adresse_gestion',
        'telephone',
        'email',
        'contact_nom',
        'site_web',
        'siret',
        'assujettie_tva',
        'bic',
        'iban',
        'ics',
        'modules_actifs',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'assujettie_tva' => 'boolean',
            'actif' => 'boolean',
            'modules_actifs' => 'array',
            // Coordonnées bancaires chiffrées en base (non utilisées tant que SEPA est hors périmètre).
            'bic' => 'encrypted',
            'iban' => 'encrypted',
            'ics' => 'encrypted',
        ];
    }

    /**
     * @return HasMany<Serre, $this>
     */
    public function serres(): HasMany
    {
        return $this->hasMany(Serre::class);
    }
}
