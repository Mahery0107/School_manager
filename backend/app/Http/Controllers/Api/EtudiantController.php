<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use Illuminate\Http\Request;

class EtudiantController extends Controller
{
    /** Liste / recherche des étudiants (nom, prénom, matricule). */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        return Etudiant::query()
            ->with(['derniereInscription.classe', 'derniereInscription.anneeScolaire'])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('matricule', 'like', "%{$q}%");
            }))
            ->orderBy('nom')->orderBy('prenom')
            ->paginate((int) $request->query('per_page', 20));
    }

    /** Fiche étudiant avec l'historique des inscriptions et leur situation financière. */
    public function show(Etudiant $etudiant)
    {
        $etudiant->load([
            'inscriptions' => fn ($q) => $q->orderByDesc('annee_scolaire_id'),
            'inscriptions.classe', 'inscriptions.anneeScolaire', 'inscriptions.paiements.frais',
        ]);

        $etudiant->inscriptions->each(fn ($i) => $i->setAttribute('situation', $i->situation()));

        return $etudiant;
    }

    public function update(Request $request, Etudiant $etudiant)
    {
        $etudiant->update($request->validate(
            InscriptionController::reglesEtudiant(),
            InscriptionController::messagesEtudiant()
        ));

        return $etudiant;
    }
}
