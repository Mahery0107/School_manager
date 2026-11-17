<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscription extends Model
{
    protected $fillable = [
        'etudiant_id', 'classe_id', 'annee_scolaire_id', 'type', 'date_inscription',
        'date_sortie', 'motif_sortie', 'remarque_sortie',
    ];

    public const MOTIFS_SORTIE = [
        'renvoye' => 'Renvoyé',
        'abandon' => "A quitté l'école",
        'transfert' => 'Transféré dans une autre école',
        'autre' => 'Autre motif',
    ];

    protected $casts = [
        'date_inscription' => 'date:Y-m-d',
        'date_sortie' => 'date:Y-m-d',
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

    /** Paiements actifs (les reçus annulés ne comptent plus). */
    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class)->whereNull('annule_le');
    }

    /** Tous les paiements, y compris annulés (historique). */
    public function tousPaiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    /**
     * État d'un élément à payer (droit d'inscription, ou écolage d'un mois YYYY-MM).
     * Un élément peut être réglé en plusieurs versements. Son montant dû est figé au premier versement
     * (montant_du) ; tant qu'il n'est pas entamé, il suit le tarif actuel de la classe.
     *
     * @return array{montant_du:int, montant_paye:int, reste:int, solde:bool, partiel:bool}
     */
    public function etatElement(string $type, ?string $mois = null): array
    {
        $this->loadMissing(['classe', 'paiements']);

        $versements = $this->paiements->filter(fn (Paiement $p) => $p->type === $type && $p->mois === $mois);
        $tarif = $type === 'droit' ? $this->classe->droit_inscription : $this->classe->ecolage_mensuel;
        $du = $versements->isNotEmpty() ? (int) $versements->max('montant_du') : (int) $tarif;
        $paye = (int) $versements->sum('montant');
        $reste = max(0, $du - $paye);

        return [
            'montant_du' => $du,
            'montant_paye' => $paye,
            'reste' => $reste,
            'solde' => $reste === 0,
            'partiel' => $paye > 0 && $reste > 0,
        ];
    }

    /**
     * Mois d'écolage dus : toute l'année scolaire, ou seulement jusqu'au mois de sortie de l'élève
     * (les mois après son départ ne sont plus dus, sauf s'ils ont déjà reçu un versement).
     *
     * @return list<string>
     */
    public function moisDus(): array
    {
        $this->loadMissing(['anneeScolaire', 'paiements']);
        $mois = $this->anneeScolaire->mois;

        if (! $this->date_sortie) {
            return $mois;
        }

        $limite = $this->date_sortie->format('Y-m');
        $verses = $this->paiements->where('type', 'ecolage')->pluck('mois')->all();

        return array_values(array_filter($mois, fn ($m) => $m <= $limite || in_array($m, $verses, true)));
    }

    /**
     * Situation financière : droit et mois (soldés, partiellement payés ou impayés), totaux.
     * Un mois est « en retard » s'il n'est pas soldé après son échéance (le N du mois).
     */
    public function situation(): array
    {
        $this->loadMissing(['classe', 'anneeScolaire', 'paiements']);

        $droit = $this->etatElement('droit');
        $aujourdhui = now()->toDateString();

        $mois = collect($this->moisDus())->map(function ($m) use ($aujourdhui) {
            $etat = $this->etatElement('ecolage', $m);
            $echeance = self::echeance($m);

            return [
                'mois' => $m,
                'paye' => $etat['solde'],
                'partiel' => $etat['partiel'],
                'montant_du' => $etat['montant_du'],
                'montant_paye' => $etat['montant_paye'],
                'reste' => $etat['reste'],
                'echeance' => $echeance,
                'en_retard' => ! $etat['solde'] && $aujourdhui > $echeance,
            ];
        });

        $enRetard = $mois->where('en_retard', true);
        $totalPaye = (int) $this->paiements->sum('montant');
        $reste = $droit['reste'] + (int) $mois->sum('reste');

        return [
            'droit_inscription' => $droit['montant_du'],
            'ecolage_mensuel' => $this->classe->ecolage_mensuel,
            'droit_paye' => $droit['solde'],
            'droit' => $droit,
            'mois' => $mois->values()->all(),
            'mois_en_retard' => $enRetard->count(),
            'montant_en_retard' => (int) $enRetard->sum('reste'),
            'total_du' => $totalPaye + $reste,
            'total_paye' => $totalPaye,
            'reste' => $reste,
            'sorti' => $this->date_sortie !== null,
        ];
    }

    /** Date limite de paiement de l'écolage d'un mois (YYYY-MM) : le N du mois, au format YYYY-MM-DD. */
    public static function echeance(string $mois): string
    {
        $jour = max(1, min(28, (int) config('app.jour_limite_ecolage', 10)));

        return $mois.'-'.str_pad((string) $jour, 2, '0', STR_PAD_LEFT);
    }
}
