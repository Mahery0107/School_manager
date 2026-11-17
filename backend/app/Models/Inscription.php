<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscription extends Model
{
    protected $fillable = ['etudiant_id', 'classe_id', 'annee_scolaire_id', 'type', 'date_inscription'];

    protected $casts = [
        'date_inscription' => 'date:Y-m-d',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * Situation financière : droit payé ?, mois payés / impayés, totaux.
     * Un élément déjà payé est soldé au montant encaissé ; seuls les éléments restants
     * sont valorisés au tarif actuel de la classe (un changement de tarif ne touche pas le passé).
     */
    public function situation(): array
    {
        $this->loadMissing(['classe', 'anneeScolaire', 'paiements']);

        $moisScolaires = $this->anneeScolaire->mois;
        $moisPayes = $this->paiements->where('type', 'ecolage')->pluck('mois')->all();
        $droitPaye = $this->paiements->where('type', 'droit')->isNotEmpty();

        $moisRestants = count(array_diff($moisScolaires, $moisPayes));
        $reste = ($droitPaye ? 0 : $this->classe->droit_inscription) + $moisRestants * $this->classe->ecolage_mensuel;
        $totalPaye = (int) $this->paiements->sum('montant');
        $totalDu = $totalPaye + $reste;

        // Échéance : l'écolage d'un mois doit être payé au plus tard le N du mois (config app.jour_limite_ecolage).
        $aujourdhui = now()->toDateString();
        $mois = collect($moisScolaires)->map(function ($m) use ($moisPayes, $aujourdhui) {
            $paye = in_array($m, $moisPayes, true);
            $echeance = self::echeance($m);

            return [
                'mois' => $m,
                'paye' => $paye,
                'echeance' => $echeance,
                'en_retard' => ! $paye && $aujourdhui > $echeance,
            ];
        });
        $moisEnRetard = $mois->where('en_retard', true)->count();

        return [
            'droit_inscription' => $this->classe->droit_inscription,
            'ecolage_mensuel' => $this->classe->ecolage_mensuel,
            'droit_paye' => $droitPaye,
            'mois' => $mois->all(),
            'mois_en_retard' => $moisEnRetard,
            'montant_en_retard' => $moisEnRetard * $this->classe->ecolage_mensuel,
            'total_du' => $totalDu,
            'total_paye' => $totalPaye,
            'reste' => $reste,
        ];
    }

    /** Date limite de paiement de l'écolage d'un mois (YYYY-MM) : le N du mois, au format YYYY-MM-DD. */
    public static function echeance(string $mois): string
    {
        $jour = max(1, min(28, (int) config('app.jour_limite_ecolage', 10)));

        return $mois.'-'.str_pad((string) $jour, 2, '0', STR_PAD_LEFT);
    }
}
