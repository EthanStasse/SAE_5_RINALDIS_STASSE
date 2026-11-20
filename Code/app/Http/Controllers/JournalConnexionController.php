<?php

namespace App\Http\Controllers;

use App\Models\JournalConnexion;
use App\Models\User;
use App\Support\StructureCourante;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EF-D-11 : consultation du journal des connexions (permission voir-audit).
 * - administrateur d'un Jardin : les comptes de son Jardin seulement ;
 * - administrateur du réseau : tout, y compris les adresses inconnues.
 */
class JournalConnexionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $structureId = app(StructureCourante::class)->id();
        abort_if($structureId === null, 403);

        $reseau = (bool) $request->user()?->can('gerer-structures');

        // Comptes qui ont au moins un rôle dans le Jardin courant.
        $comptesDuJardin = DB::table(config('permission.table_names.model_has_roles'))
            ->select(config('permission.column_names.model_morph_key'))
            ->where('structure_id', $structureId)
            ->where('model_type', (new User)->getMorphClass());

        $lignes = JournalConnexion::query()
            ->with('user:id,name,email')
            ->when(! $reseau, fn ($requete) => $requete->whereIn('user_id', $comptesDuJardin))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50);

        return view('securite.journal-connexions', ['lignes' => $lignes, 'reseau' => $reseau]);
    }
}
