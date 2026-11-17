<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnneeScolaireController extends Controller
{
    public function index()
    {
        return AnneeScolaire::orderByDesc('date_debut')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'libelle' => 'required|string|max:20|unique:annees_scolaires,libelle',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'active' => 'boolean',
        ]);

        return DB::transaction(function () use ($data) {
            if (! empty($data['active'])) {
                AnneeScolaire::query()->update(['active' => false]);
            }

            return response()->json(AnneeScolaire::create($data), 201);
        });
    }

    /** Définit l'année comme année scolaire en cours. */
    public function activer(AnneeScolaire $annee)
    {
        DB::transaction(function () use ($annee) {
            AnneeScolaire::query()->update(['active' => false]);
            $annee->update(['active' => true]);
        });

        return $annee->fresh();
    }
}
