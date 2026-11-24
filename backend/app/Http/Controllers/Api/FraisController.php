<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Frais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Paramètres → Frais annuels (en plus de l'écolage) : liste, ajout, modification, suppression. */
class FraisController extends Controller
{
    public function index()
    {
        return Frais::with('classes:id,nom')->orderBy('ordre')->orderBy('id')->get()
            ->map(fn (Frais $f) => $f->toArray() + ['classe_ids' => $f->classes->pluck('id')]);
    }

    public function store(Request $request)
    {
        return response()->json($this->enregistrer(new Frais, $request), 201);
    }

    public function update(Request $request, Frais $frais)
    {
        return $this->enregistrer($frais, $request);
    }

    /** Un frais déjà encaissé ne peut pas être supprimé (historique des reçus) : on le désactive. */
    public function destroy(Frais $frais)
    {
        if ($frais->paiements()->exists()) {
            return response()->json([
                'message' => "Ce frais a déjà été encaissé : il ne peut pas être supprimé. Décochez « Actif » pour ne plus l'appliquer.",
            ], 422);
        }
        $frais->delete();

        return response()->noContent();
    }

    private function enregistrer(Frais $frais, Request $request): array
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:100', Rule::unique('frais', 'libelle')->ignore($frais)],
            'montant' => 'required|integer|min:0',
            'portee' => ['required', Rule::in(array_keys(Frais::PORTEES))],
            'obligatoire' => ['required', Rule::in(array_keys(Frais::OBLIGATOIRE))],
            'facultatif_autres' => 'boolean',
            'actif' => 'boolean',
            'ordre' => 'nullable|integer|min:0',
            'classe_ids' => 'array',
            'classe_ids.*' => 'integer|exists:classes,id',
        ], [], ['libelle' => 'libellé', 'portee' => 'portée', 'classe_ids' => 'classes']);

        DB::transaction(function () use ($frais, $data) {
            $frais->fill(collect($data)->except('classe_ids')->all() + ['ordre' => $data['ordre'] ?? (Frais::max('ordre') + 1)]);
            $frais->save();
            $frais->classes()->sync($data['classe_ids'] ?? []);
        });

        $frais->load('classes:id,nom');

        return $frais->toArray() + ['classe_ids' => $frais->classes->pluck('id')];
    }
}
