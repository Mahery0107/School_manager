<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Inscription;
use App\Models\Paiement;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $annee = AnneeScolaire::active();
        if (! $annee) {
            return ['annee' => null];
        }

        $inscriptions = Inscription::with(['classe', 'anneeScolaire', 'paiements'])
            ->where('annee_scolaire_id', $annee->id)->get();

        // Élèves présents (hors sortis) : effectif, retards. Les sommes dues / payées incluent tout le monde.
        $presents = $inscriptions->whereNull('date_sortie');

        $totalDu = 0;
        $totalPaye = 0;
        $enRetard = 0;
        $montantEnRetard = 0;
        foreach ($inscriptions as $i) {
            $s = $i->situation();
            $totalDu += $s['total_du'];
            $totalPaye += $s['total_paye'];
            $montantEnRetard += $s['montant_en_retard'];
            // En retard : droit non payé, ou au moins un écolage non payé après le 10 du mois
            if (! $i->date_sortie && (! $s['droit_paye'] || $s['mois_en_retard'] > 0)) {
                $enRetard++;
            }
        }

        return [
            'annee' => $annee,
            'effectif' => $presents->count(),
            'sortis' => $inscriptions->count() - $presents->count(),
            'nouveaux' => $presents->where('type', 'inscription')->count(),
            'reinscrits' => $presents->where('type', 'reinscription')->count(),
            'total_du' => $totalDu,
            'total_paye' => $totalPaye,
            'reste' => max(0, $totalDu - $totalPaye),
            'eleves_en_retard' => $enRetard,
            'montant_ecolage_en_retard' => $montantEnRetard,
            'jour_limite_ecolage' => (int) config('app.jour_limite_ecolage'),
            'encaisse_aujourdhui' => (int) Paiement::actifs()->whereDate('date_paiement', today())->sum('montant'),
            'par_classe' => $presents->sortBy('classe.ordre')->groupBy('classe.nom')
                ->map(fn ($g, $nom) => ['classe' => $nom, 'effectif' => $g->count()])->values(),
        ];
    }
}
