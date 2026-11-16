<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Grandeur;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation d'une mesure envoyée par un boîtier (EF-C-04, EF-C-05).
 *
 * Plages plausibles : définies dans App\Enums\Grandeur (fiches techniques des capteurs).
 * Une valeur en dehors ne peut pas venir d'un capteur qui fonctionne :
 * c'est une panne, un parasite ou une requête forgée. Elle est refusée (422).
 *
 * Les champs inconnus (structure_id, serre_id...) sont ignorés : un boîtier
 * ne choisit jamais où sa mesure est rangée.
 */
class EnregistrerMesureRequest extends FormRequest
{
    /** Tolérance pour une horloge de passerelle légèrement en avance. */
    public const AVANCE_MAX_MINUTES = 5;

    /** Date ISO 8601 avec fuseau obligatoire (Z ou +01:00) : aucune ambiguïté sur l'heure. */
    private const FORMAT_DATE = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

    public function authorize(): bool
    {
        // L'authentification est faite par le middleware AuthentifierBoitier.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $limite = now()->addMinutes(self::AVANCE_MAX_MINUTES)->toIso8601String();

        return [
            'uuid' => ['required', 'uuid'],
            // bail : on s'arrête à la première erreur, pour un seul message clair.
            'mesure_le' => ['bail', 'required', 'string', 'regex:'.self::FORMAT_DATE, 'date', 'before_or_equal:'.$limite],
            'temperature' => [
                'nullable', 'numeric', 'required_without_all:humidite,luminosite',
                self::plage(Grandeur::Temperature),
            ],
            'humidite' => ['nullable', 'numeric', self::plage(Grandeur::Humidite)],
            'luminosite' => ['nullable', 'numeric', self::plage(Grandeur::Luminosite)],
        ];
    }

    /**
     * Messages explicites en français (EF-C-05).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'uuid.required' => "L'identifiant unique de la mesure (uuid) est obligatoire.",
            'uuid.uuid' => "L'identifiant de la mesure doit être un UUID.",
            'mesure_le.required' => 'La date de la mesure est obligatoire.',
            'mesure_le.string' => 'La date de la mesure doit être une chaîne ISO 8601.',
            'mesure_le.regex' => 'La date doit être au format ISO 8601 avec fuseau, par exemple 2026-11-16T09:55:00Z.',
            'mesure_le.date' => "La date de la mesure n'existe pas.",
            'mesure_le.before_or_equal' => 'La date de la mesure est dans le futur : vérifiez l\'horloge de la passerelle.',
            'temperature.required_without_all' => 'La mesure doit contenir au moins une valeur (température, humidité ou luminosité).',
            'temperature.numeric' => 'La température doit être un nombre.',
            'temperature.between' => 'La température doit être comprise entre -40 et 80 °C.',
            'humidite.numeric' => "L'humidité doit être un nombre.",
            'humidite.between' => "L'humidité doit être comprise entre 0 et 100 %.",
            'luminosite.numeric' => 'La luminosité doit être un nombre.',
            'luminosite.between' => 'La luminosité doit être comprise entre 0 et 65 535 lx.',
        ];
    }

    /** Règle « between » construite à partir de la plage plausible de la grandeur. */
    public static function plage(Grandeur $grandeur): string
    {
        return 'between:'.$grandeur->minimumPlausible().','.$grandeur->maximumPlausible();
    }
}
