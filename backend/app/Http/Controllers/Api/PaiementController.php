<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Paiement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    /** Journal des paiements, regroupés par reçu. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $recus = Paiement::query()
            ->select(
                'numero_recu',
                DB::raw('MAX(id) as dernier_id'),
                DB::raw('SUM(montant) as total'),
                DB::raw('MIN(date_paiement) as date_paiement'),
                DB::raw('MIN(mode) as mode'),
                DB::raw('MIN(inscription_id) as inscription_id'),
                DB::raw('MAX(annule_le) as annule_le'),
                DB::raw('MAX(encaisse_par) as encaisse_par')
            )
            ->when($request->query('du'), fn ($query, $d) => $query->whereDate('date_paiement', '>=', $d))
            ->when($request->query('au'), fn ($query, $d) => $query->whereDate('date_paiement', '<=', $d))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('numero_recu', 'like', "%{$q}%")
                ->orWhereHas('inscription.etudiant', fn ($e) => $e
                    ->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('matricule', 'like', "%{$q}%"))))
            ->groupBy('numero_recu')
            ->orderByDesc('dernier_id')
            ->paginate((int) $request->query('per_page', 20));

        $inscriptions = Inscription::with(['etudiant', 'classe'])
            ->whereIn('id', $recus->getCollection()->pluck('inscription_id'))
            ->get()->keyBy('id');

        $recus->setCollection($recus->getCollection()->map(fn ($r) => [
            'numero_recu' => $r->numero_recu,
            'total' => (int) $r->total,
            'date_paiement' => substr((string) $r->date_paiement, 0, 10),
            'mode' => $r->mode,
            'annule' => $r->annule_le !== null,
            'encaisse_par' => $r->encaisse_par,
            'etudiant' => $inscriptions->get($r->inscription_id)?->etudiant,
            'classe' => $inscriptions->get($r->inscription_id)?->classe?->nom,
        ]));

        return $recus;
    }

    /**
     * Enregistre un paiement : frais annuels (assurance, FRAM, t-shirt…) et/ou mois d'écolage.
     *   frais = [id, …] ; mois = ["2026-11", …] ; droit = true pour l'ancien droit d'inscription (reste éventuel).
     * Chaque élément peut être payé en plusieurs fois : montants = {"frais-2": 30000, "2026-11": 10000, "droit": …}.
     * Sans montant indiqué, l'élément est soldé (son reste à payer est encaissé).
     * Le montant d'un versement ne peut pas dépasser le reste à payer de l'élément.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'droit' => 'boolean',
            'frais' => 'array',
            'frais.*' => 'integer',
            'mois' => 'array',
            'mois.*' => 'string|date_format:Y-m',
            'montants' => 'array',
            'montants.*' => 'nullable|integer|min:1',
            'mode' => 'required|in:especes,mobile_money,cheque,virement',
            'reference' => 'nullable|string|max:100|required_unless:mode,especes',
            'date_paiement' => 'required|date|before_or_equal:today',
        ], [
            'reference.required_unless' => 'La référence est obligatoire pour ce mode de paiement.',
            'montants.*.min' => 'Le montant versé doit être supérieur à 0.',
            'montants.*.integer' => "Le montant versé doit être un nombre entier d'Ariary.",
        ]);

        $cles = array_merge(
            ($data['droit'] ?? false) ? ['droit'] : [],
            array_map(fn ($id) => 'frais-'.$id, array_values(array_unique($data['frais'] ?? []))),
        );
        $mois = array_values(array_unique($data['mois'] ?? []));
        sort($mois);
        $montants = $data['montants'] ?? [];

        if (! $cles && ! $mois) {
            throw ValidationException::withMessages([
                'mois' => "Sélectionnez au moins un frais ou un mois d'écolage.",
            ]);
        }

        $numero = DB::transaction(function () use ($request, $data, $cles, $mois, $montants) {
            $inscription = Inscription::with(['classe', 'anneeScolaire', 'etudiant'])
                ->lockForUpdate()->findOrFail($data['inscription_id']);
            $inscription->load('paiements'); // versements à jour, lus sous verrou

            // [type, mois, frais_id, clé, libellé, état]
            $elements = [];
            $frais = collect($inscription->elementsFrais())->keyBy('cle');
            foreach ($cles as $cle) {
                $f = $frais->get($cle);
                if (! $f) {
                    throw ValidationException::withMessages(['frais' => 'Ce frais ne concerne pas cet élève.']);
                }
                if (! $f['payable']) {
                    throw ValidationException::withMessages(['frais' => $f['solde'] && $f['montant_paye'] > 0
                        ? "« {$f['libelle']} » est déjà entièrement payé."
                        : "« {$f['libelle']} » n'est pas à payer pour cet élève : {$f['note']}."]);
                }
                $elements[] = [$f['type'], null, $f['frais_id'], $cle, "« {$f['libelle']} »", $f];
            }

            $moisValides = $inscription->moisDus();
            foreach ($mois as $m) {
                if (! in_array($m, $moisValides, true)) {
                    throw ValidationException::withMessages(['mois' => in_array($m, $inscription->anneeScolaire->mois, true)
                        ? "L'écolage du mois {$m} n'est pas dû : l'élève a quitté l'école le {$inscription->date_sortie->format('d/m/Y')}."
                        : "Le mois {$m} ne fait pas partie de l'année scolaire."]);
                }
                $etat = $inscription->etatElement('ecolage', $m);
                if ($etat['solde']) {
                    throw ValidationException::withMessages(['mois' => "L'écolage du mois {$m} est déjà entièrement payé."]);
                }
                $elements[] = ['ecolage', $m, null, $m, "l'écolage du mois {$m}", $etat];
            }

            $numero = Paiement::genererNumeroRecu();
            $commun = [
                'numero_recu' => $numero,
                'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null,
                'date_paiement' => $data['date_paiement'],
                'encaisse_par' => $request->user()?->name,
            ];

            foreach ($elements as [$type, $m, $fraisId, $cle, $libelle, $etat]) {
                $montant = (int) ($montants[$cle] ?? $etat['reste']);
                if ($montant > $etat['reste']) {
                    throw ValidationException::withMessages([
                        "montants.{$cle}" => sprintf(
                            'Le montant versé pour %s (%s Ar) dépasse le reste à payer (%s Ar).',
                            $libelle, number_format($montant, 0, ',', ' '), number_format($etat['reste'], 0, ',', ' ')
                        ),
                    ]);
                }

                $inscription->paiements()->create($commun + [
                    'type' => $type,
                    'mois' => $m,
                    'frais_id' => $fraisId,
                    'montant' => $montant,
                    'montant_du' => $etat['montant_du'],
                ]);
            }

            return $numero;
        });

        return response()->json($this->construireRecu($numero), 201);
    }

    /**
     * Annule un reçu entier (erreur de saisie, mauvais élève, mauvais montant…).
     * Les lignes restent en base avec le motif, la date et l'auteur de l'annulation, mais ne comptent plus :
     * les éléments redeviennent à payer. Pour corriger, on annule puis on encaisse à nouveau.
     */
    public function annuler(Request $request, string $numero)
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['motif' => "motif de l'annulation"]);

        DB::transaction(function () use ($request, $numero, $data) {
            $lignes = Paiement::where('numero_recu', $numero)->lockForUpdate()->get();
            abort_if($lignes->isEmpty(), 404, 'Reçu introuvable.');

            if ($lignes->whereNotNull('annule_le')->isNotEmpty()) {
                throw ValidationException::withMessages(['motif' => 'Ce reçu est déjà annulé.']);
            }

            Paiement::where('numero_recu', $numero)->update([
                'annule_le' => now(),
                'annule_par' => $request->user()?->name,
                'annule_motif' => $data['motif'],
            ]);
        });

        return $this->construireRecu($numero);
    }

    /** Détail d'un reçu (affichage / impression). */
    public function recu(string $numero)
    {
        return $this->construireRecu($numero);
    }

    /** Reçu au format PDF, affiché dans le navigateur (inline) pour aperçu puis impression. */
    public function pdf(string $numero)
    {
        $pdf = Pdf::loadView('recus.pdf', [
            'recu' => $this->construireRecu($numero),
            'ecole' => config('app.ecole'),
            'modes' => ['especes' => 'Espèces', 'mobile_money' => 'Mobile Money', 'cheque' => 'Chèque', 'virement' => 'Virement'],
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("recu-{$numero}.pdf");
    }

    private function construireRecu(string $numero): array
    {
        // frais d'abord (dans l'ordre de la grille), puis les mois d'écolage
        $lignes = Paiement::with('frais')->where('numero_recu', $numero)->get()
            ->sortBy(fn (Paiement $l) => [$l->type === 'ecolage' ? 1 : 0, $l->frais?->ordre ?? 0, (string) $l->mois])->values();
        abort_if($lignes->isEmpty(), 404, 'Reçu introuvable.');

        $premiere = $lignes->first();
        $inscription = Inscription::with(['etudiant', 'classe', 'anneeScolaire', 'paiements'])->find($premiere->inscription_id);

        return [
            'ecole' => config('app.ecole'),
            'numero_recu' => $numero,
            'date_paiement' => $premiere->date_paiement->format('Y-m-d'),
            'mode' => $premiere->mode,
            'reference' => $premiere->reference,
            'encaisse_par' => $premiere->encaisse_par,
            'annule' => $premiere->annule_le !== null,
            'annule_le' => $premiere->annule_le?->format('Y-m-d H:i'),
            'annule_par' => $premiere->annule_par,
            'annule_motif' => $premiere->annule_motif,
            'inscription_id' => $inscription->id,
            'etudiant' => $inscription->etudiant,
            'classe' => $inscription->classe->nom,
            'annee_scolaire' => $inscription->anneeScolaire->libelle,
            'lignes' => $lignes->map(function (Paiement $l) use ($inscription) {
                // versements actifs antérieurs sur le même élément (hors reçus annulés)
                $deja = (int) $inscription->paiements
                    ->filter(fn (Paiement $p) => $p->type === $l->type && $p->mois === $l->mois
                        && $p->frais_id === $l->frais_id && $p->id < $l->id)
                    ->sum('montant');

                return [
                    'type' => $l->type,
                    'mois' => $l->mois,
                    'frais_id' => $l->frais_id,
                    'designation' => $l->designation(),
                    'montant' => $l->montant,
                    'montant_du' => $l->montant_du,
                    'deja_paye' => $deja,
                    'reste_apres' => max(0, $l->montant_du - $deja - $l->montant),
                ];
            })->values(),
            'total' => (int) $lignes->sum('montant'),
            'situation' => $inscription->situation(),
        ];
    }
}
