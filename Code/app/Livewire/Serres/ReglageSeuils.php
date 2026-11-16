<?php

namespace App\Livewire\Serres;

use App\Enums\Grandeur;
use App\Http\Requests\Api\V1\EnregistrerMesureRequest;
use App\Models\Serre;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Seuils minimum et maximum d'une serre, par grandeur (EF-C-08).
 * Permission « gerer-seuils » (administrateur et équipe).
 *
 * Un champ vide = pas de borne. Les deux champs vides = pas de seuil pour
 * cette grandeur (le seuil existant est supprimé).
 */
class ReglageSeuils extends Component
{
    public Serre $serre;

    /** @var array<string, array{minimum: string, maximum: string}> */
    public array $valeurs = [];

    public ?string $confirmation = null;

    public function mount(Serre $serre): void
    {
        $this->authorize('gerer-seuils');
        $this->serre = $serre;

        $existants = $serre->seuils()->get()->keyBy(fn ($seuil) => $seuil->grandeur->value);

        foreach (Grandeur::cases() as $grandeur) {
            $seuil = $existants->get($grandeur->value);
            $this->valeurs[$grandeur->value] = [
                'minimum' => self::versTexte($seuil?->minimum),
                'maximum' => self::versTexte($seuil?->maximum),
            ];
        }
    }

    public function enregistrer(): void
    {
        $this->authorize('gerer-seuils');
        $this->confirmation = null;

        // On accepte la virgule décimale à la française (« 12,5 »).
        foreach ($this->valeurs as $grandeur => $bornes) {
            foreach ($bornes as $borne => $saisie) {
                $this->valeurs[$grandeur][$borne] = trim(str_replace(',', '.', (string) $saisie));
            }
        }

        $this->validate();

        // Le minimum doit être strictement inférieur au maximum (même règle qu'en base).
        foreach (Grandeur::cases() as $grandeur) {
            [$minimum, $maximum] = $this->bornes($grandeur);
            if ($minimum !== null && $maximum !== null && $minimum >= $maximum) {
                $this->addError(
                    "valeurs.{$grandeur->value}.maximum",
                    "Le maximum doit être supérieur au minimum ({$grandeur->libelle()})."
                );
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () {
            foreach (Grandeur::cases() as $grandeur) {
                [$minimum, $maximum] = $this->bornes($grandeur);

                if ($minimum === null && $maximum === null) {
                    $this->serre->seuils()->where('grandeur', $grandeur->value)->delete();

                    continue;
                }

                // structure_id est rempli par le trait AppartientAStructure.
                $this->serre->seuils()->updateOrCreate(
                    ['grandeur' => $grandeur->value],
                    ['minimum' => $minimum, 'maximum' => $maximum],
                );
            }
        });

        $this->confirmation = 'Les seuils de la serre ont été enregistrés.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $regles = [];
        foreach (Grandeur::cases() as $grandeur) {
            foreach (['minimum', 'maximum'] as $borne) {
                $regles["valeurs.{$grandeur->value}.{$borne}"] = [
                    'nullable', 'numeric', EnregistrerMesureRequest::plage($grandeur),
                ];
            }
        }

        return $regles;
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $messages = [];
        foreach (Grandeur::cases() as $grandeur) {
            $plage = "entre {$grandeur->minimumPlausible()} et {$grandeur->maximumPlausible()} {$grandeur->unite()}";
            foreach (['minimum', 'maximum'] as $borne) {
                $messages["valeurs.{$grandeur->value}.{$borne}.numeric"] = "Le {$borne} doit être un nombre.";
                $messages["valeurs.{$grandeur->value}.{$borne}.between"] = "Le {$borne} doit être compris {$plage}.";
            }
        }

        return $messages;
    }

    public function render(): View
    {
        return view('livewire.serres.reglage-seuils', [
            'grandeurs' => Grandeur::cases(),
        ]);
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function bornes(Grandeur $grandeur): array
    {
        $saisie = $this->valeurs[$grandeur->value] ?? ['minimum' => '', 'maximum' => ''];

        return [self::versNombre($saisie['minimum']), self::versNombre($saisie['maximum'])];
    }

    private static function versNombre(mixed $saisie): ?float
    {
        $texte = trim((string) $saisie);

        return $texte === '' ? null : (float) $texte;
    }

    private static function versTexte(?float $valeur): string
    {
        return $valeur === null ? '' : rtrim(rtrim(number_format($valeur, 1, '.', ''), '0'), '.');
    }
}
