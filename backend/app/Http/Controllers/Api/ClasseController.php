<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClasseController extends Controller
{
    public function index()
    {
        return Classe::orderBy('ordre')->orderBy('nom')->get();
    }

    public function store(Request $request)
    {
        return response()->json(Classe::create($this->valider($request)), 201);
    }

    public function update(Request $request, Classe $classe)
    {
        $classe->update($this->valider($request, $classe));

        return $classe;
    }

    public function destroy(Classe $classe)
    {
        if ($classe->inscriptions()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer une classe qui a des inscriptions.'], 422);
        }
        $classe->delete();

        return response()->noContent();
    }

    private function valider(Request $request, ?Classe $classe = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:50', Rule::unique('classes', 'nom')->ignore($classe)],
            'ordre' => 'required|integer|min:0',
            'ecolage_mensuel' => 'required|integer|min:0',
        ]);
    }
}
