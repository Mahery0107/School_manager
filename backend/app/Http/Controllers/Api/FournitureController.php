<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Fourniture;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fournitures scolaires par année et par classe :
 * consultation, impression (PDF), et gestion dans Paramètres (ajout, modification, suppression, copie).
 */
class FournitureController extends Controller
{
    /** Liste d'une classe (ou de toutes les classes) pour une année ; année active par défaut. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'annee_scolaire_id' => 'nullable|exists:annees_scolaires,id',
            'classe_id' => 'nullable|exists:classes,id',
        ]);
        $annee = isset($data['annee_scolaire_id']) ? AnneeScolaire::find($data['annee_scolaire_id']) : AnneeScolaire::active();

        return Fourniture::where('annee_scolaire_id', $annee?->id)
            ->when($data['classe_id'] ?? null, fn ($q, $c) => $q->where('classe_id', $c))
            ->orderBy('classe_id')->orderBy('ordre')->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $this->valider($request, true);
        $data['ordre'] = (int) Fourniture::where('annee_scolaire_id', $data['annee_scolaire_id'])
            ->where('classe_id', $data['classe_id'])->max('ordre') + 1;

        return response()->json(Fourniture::create($data), 201);
    }

    public function update(Request $request, Fourniture $fourniture)
    {
        $fourniture->update($this->valider($request, false));

        return $fourniture;
    }

    public function destroy(Fourniture $fourniture)
    {
        $fourniture->delete();

        return response()->noContent();
    }

    /** Change l'ordre d'affichage : liste des ids dans le nouvel ordre. */
    public function ordonner(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:fournitures,id']);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $i => $id) {
                Fourniture::whereKey($id)->update(['ordre' => $i + 1]);
            }
        });

        return response()->noContent();
    }

    /**
     * Copie la liste d'une autre classe et/ou d'une autre année vers une classe (ajoutée à la suite).
     * Pratique pour reprendre la liste de l'an dernier, ou celle d'une classe voisine.
     */
    public function copier(Request $request)
    {
        $data = $request->validate([
            'source_annee_id' => 'required|exists:annees_scolaires,id',
            'source_classe_id' => 'required|exists:classes,id',
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'classe_id' => 'required|exists:classes,id',
        ]);

        if ($data['source_annee_id'] == $data['annee_scolaire_id'] && $data['source_classe_id'] == $data['classe_id']) {
            throw ValidationException::withMessages(['source_classe_id' => 'Choisissez une autre classe ou une autre année à copier.']);
        }

        $source = Fourniture::where('annee_scolaire_id', $data['source_annee_id'])
            ->where('classe_id', $data['source_classe_id'])->orderBy('ordre')->orderBy('id')->get();
        if ($source->isEmpty()) {
            throw ValidationException::withMessages(['source_classe_id' => "Il n'y a aucune fourniture à copier dans cette classe pour cette année."]);
        }

        $copiees = DB::transaction(function () use ($data, $source) {
            $ordre = (int) Fourniture::where('annee_scolaire_id', $data['annee_scolaire_id'])
                ->where('classe_id', $data['classe_id'])->max('ordre');
            foreach ($source as $f) {
                Fourniture::create([
                    'annee_scolaire_id' => $data['annee_scolaire_id'],
                    'classe_id' => $data['classe_id'],
                    'designation' => $f->designation,
                    'quantite' => $f->quantite,
                    'remarque' => $f->remarque,
                    'ordre' => ++$ordre,
                ]);
            }

            return $source->count();
        });

        return response()->json(['copiees' => $copiees], 201);
    }

    /** Liste imprimable (A4) : une classe, ou toutes les classes (une page par classe). */
    public function pdf(Request $request)
    {
        $data = $request->validate([
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'classe_id' => 'nullable|exists:classes,id',
        ]);
        $annee = AnneeScolaire::find($data['annee_scolaire_id']);
        $fournitures = Fourniture::where('annee_scolaire_id', $annee->id)
            ->when($data['classe_id'] ?? null, fn ($q, $c) => $q->where('classe_id', $c))
            ->orderBy('ordre')->orderBy('id')->get()->groupBy('classe_id');

        $classes = Classe::orderBy('ordre')
            ->when($data['classe_id'] ?? null, fn ($q, $c) => $q->whereKey($c), fn ($q) => $q->whereIn('id', $fournitures->keys()))
            ->get();

        abort_if($classes->isEmpty(), 404, "Aucune fourniture n'est encore saisie pour cette année.");

        return Pdf::loadView('fournitures.pdf', [
            'annee' => $annee,
            'classes' => $classes,
            'fournitures' => $fournitures,
            'ecole' => config('app.ecole'),
        ])->setPaper('a4', 'portrait')->stream('fournitures-'.$annee->libelle.'.pdf');
    }

    private function valider(Request $request, bool $creation): array
    {
        return $request->validate(($creation ? [
            'annee_scolaire_id' => 'required|exists:annees_scolaires,id',
            'classe_id' => 'required|exists:classes,id',
        ] : []) + [
            'designation' => 'required|string|max:150',
            'quantite' => 'nullable|string|max:30',
            'remarque' => 'nullable|string|max:150',
        ], [], ['designation' => 'désignation', 'quantite' => 'quantité']);
    }
}
