<?php

namespace App\Models;

use App\Support\Familles;
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
     * État d'un élément à payer : écolage d'un mois YYYY-MM, frais ($frais), ou ancien droit d'inscription.
     * Un élément peut être réglé en plusieurs versements. Son montant dû est figé au premier versement
     * (montant_du) ; tant qu'il n'est pas entamé, il suit le tarif actuel de la classe.
     *
     * @return array{montant_du:int, montant_paye:int, reste:int, solde:bool, partiel:bool}
     */
    public function etatElement(string $type, ?string $mois = null, ?Frais $frais = null): array
    {
        $this->loadMissing(['classe', 'paiements']);

        $versements = $this->paiements->filter(fn (Paiement $p) => $p->type === $type && $p->mois === $mois
            && $p->frais_id === $frais?->id);
        $tarif = match ($type) {
            'frais' => $frais->montant,
            'droit' => $this->classe->droit_inscription,
            default => $this->classe->ecolage_mensuel,
        };
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
     * Frais annuels de l'inscription (hors écolage), avec leur état de paiement.
     *
     * - Ancien « droit d'inscription » déjà payé (avant la grille de frais) : il couvre les nouveaux frais
     *   de cette inscription ; seuls les frais facultatifs (ex. t-shirt) restent proposés.
     * - Frais « par famille » : dû sur un seul enfant (le premier inscrit encore présent) ;
     *   pour les frères et sœurs il est indiqué « réglé / à payer par la famille ».
     * - Un frais facultatif ne compte dans le reste à payer que s'il a été commencé.
     *
     * @return list<array<string, mixed>>
     */
    public function elementsFrais(): array
    {
        $this->loadMissing(['classe', 'paiements', 'etudiant']);
        $elements = [];

        $ancienDroit = $this->paiements->contains('type', 'droit');
        if ($ancienDroit) {
            $etat = $this->etatElement('droit');
            $elements[] = [
                'cle' => 'droit', 'type' => 'droit', 'frais_id' => null,
                'libelle' => "Droit d'inscription (ancien tarif)",
                'obligatoire' => true, 'facultatif' => false,
                'payable' => ! $etat['solde'], 'compte' => true,
                'note' => 'Couvre les frais de cette année',
            ] + $etat;
        }

        foreach (app('frais.actifs') as $frais) {
            if (! $frais->concerne($this->classe_id)) {
                continue;
            }
            $etat = $this->etatElement('frais', null, $frais);
            $commence = $etat['montant_paye'] > 0;
            $obligatoire = ! $ancienDroit && $frais->obligatoirePour($this);
            $facultatif = ! $obligatoire && $frais->facultatif_autres;

            if (! $obligatoire && ! $facultatif && ! $commence) {
                continue;
            }

            $note = $facultatif && ! $commence ? 'Facultatif (à la demande)' : null;
            $payable = ! $etat['solde'];
            $compte = $obligatoire || $commence;

            if ($frais->portee === 'famille' && ! $commence) {
                [$noteFamille, $parFamille] = $this->fraisFamille($frais);
                $note = $noteFamille ?? $note;
                if ($parFamille) {
                    $payable = false;
                    $compte = false;
                    $etat['reste'] = 0;
                }
            }

            $elements[] = [
                'cle' => 'frais-'.$frais->id,
                'type' => 'frais',
                'frais_id' => $frais->id,
                'libelle' => $frais->libelle,
                'obligatoire' => $obligatoire,
                'facultatif' => $facultatif,
                'payable' => $payable,
                'compte' => $compte,
                'note' => $note,
            ] + $etat;
        }

        return $elements;
    }

    /**
     * Frais par famille pas encore payé sur cet enfant : déjà réglé par un frère / une sœur,
     * ou à payer sur l'inscription du premier enfant ?
     *
     * @return array{0: ?string, 1: bool} [note, pris en charge par un autre membre de la famille]
     */
    private function fraisFamille(Frais $frais): array
    {
        $familles = app(Familles::class);
        $autres = array_values(array_diff(array_column($familles->membres($this), 'id'), [$this->id]));
        if (! $autres) {
            return [null, false];
        }

        $payeur = Paiement::actifs()->where('type', 'frais')->where('frais_id', $frais->id)
            ->whereIn('inscription_id', $autres)->with('inscription.etudiant')->orderBy('id')->first();
        if ($payeur) {
            return ['Réglé par la famille ('.self::nom($payeur->inscription->etudiant).')', true];
        }

        $titulaire = $familles->titulaire($this);
        if ($titulaire !== $this->id) {
            $nom = self::nom(Inscription::with('etudiant')->find($titulaire)?->etudiant);

            return ["Une fois par famille : à payer avec {$nom}", true];
        }

        return ['Une fois par famille (frères et sœurs exemptés)', false];
    }

    private static function nom(?Etudiant $e): string
    {
        return $e ? trim($e->nom.' '.$e->prenom) : 'un frère ou une sœur';
    }

    /**
     * Situation financière : frais et mois (soldés, partiellement payés ou impayés), totaux.
     * Un mois est « en retard » s'il n'est pas soldé après son échéance (le N du mois).
     */
    public function situation(): array
    {
        $this->loadMissing(['classe', 'anneeScolaire', 'paiements']);

        $frais = collect($this->elementsFrais());
        $fraisComptes = $frais->where('compte', true);
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
        $reste = (int) $fraisComptes->sum('reste') + (int) $mois->sum('reste');

        return [
            'ecolage_mensuel' => $this->classe->ecolage_mensuel,
            // frais obligatoires (et frais commencés) tous réglés ?
            'droit_paye' => $fraisComptes->every(fn ($f) => $f['reste'] === 0),
            'frais' => $frais->values()->all(),
            'frais_total' => (int) $fraisComptes->sum('montant_du'),
            'frais_reste' => (int) $fraisComptes->sum('reste'),
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
