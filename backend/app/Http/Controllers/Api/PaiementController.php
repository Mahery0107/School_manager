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
                DB::raw('MIN(inscription_id) as inscription_id')
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
            'etudiant' => $inscriptions->get($r->inscription_id)?->etudiant,
            'classe' => $inscriptions->get($r->inscription_id)?->classe?->nom,
        ]));

        return $recus;
    }

    /**
     * Enregistre un paiement : droit d'inscription et/ou un ou plusieurs mois d'écolage.
     * Les montants sont calculés côté serveur à partir des tarifs de la classe.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'droit' => 'boolean',
            'mois' => 'array',
            'mois.*' => 'string|date_format:Y-m',
            'mode' => 'required|in:especes,mobile_money,cheque,virement',
            'reference' => 'nullable|string|max:100|required_unless:mode,especes',
            'date_paiement' => 'required|date|before_or_equal:today',
        ], [
            'reference.required_unless' => 'La référence est obligatoire pour ce mode de paiement.',
        ]);

        $payerDroit = (bool) ($data['droit'] ?? false);
        $mois = array_values(array_unique($data['mois'] ?? []));
        sort($mois);

        if (! $payerDroit && empty($mois)) {
            throw ValidationException::withMessages([
                'mois' => "Sélectionnez le droit d'inscription et/ou au moins un mois d'écolage.",
            ]);
        }

        $numero = DB::transaction(function () use ($data, $payerDroit, $mois) {
            $inscription = Inscription::with(['classe', 'anneeScolaire'])
                ->lockForUpdate()->findOrFail($data['inscription_id']);
            $deja = $inscription->paiements()->get();

            if ($payerDroit && $deja->where('type', 'droit')->isNotEmpty()) {
                throw ValidationException::withMessages(['droit' => "Le droit d'inscription est déjà payé."]);
            }

            $moisValides = $inscription->anneeScolaire->mois;
            foreach ($mois as $m) {
                if (! in_array($m, $moisValides, true)) {
                    throw ValidationException::withMessages(['mois' => "Le mois {$m} ne fait pas partie de l'année scolaire."]);
                }
                if ($deja->where('type', 'ecolage')->where('mois', $m)->isNotEmpty()) {
                    throw ValidationException::withMessages(['mois' => "L'écolage du mois {$m} est déjà payé."]);
                }
            }

            $numero = Paiement::genererNumeroRecu();
            $commun = [
                'numero_recu' => $numero,
                'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null,
                'date_paiement' => $data['date_paiement'],
            ];

            if ($payerDroit) {
                $inscription->paiements()->create($commun + [
                    'type' => 'droit', 'mois' => null, 'montant' => $inscription->classe->droit_inscription,
                ]);
            }
            foreach ($mois as $m) {
                $inscription->paiements()->create($commun + [
                    'type' => 'ecolage', 'mois' => $m, 'montant' => $inscription->classe->ecolage_mensuel,
                ]);
            }

            return $numero;
        });

        return response()->json($this->construireRecu($numero), 201);
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
        $lignes = Paiement::where('numero_recu', $numero)->orderBy('type')->orderBy('mois')->get();
        abort_if($lignes->isEmpty(), 404, 'Reçu introuvable.');

        $premiere = $lignes->first();
        $inscription = Inscription::with(['etudiant', 'classe', 'anneeScolaire'])->find($premiere->inscription_id);

        return [
            'ecole' => config('app.ecole'),
            'numero_recu' => $numero,
            'date_paiement' => $premiere->date_paiement->format('Y-m-d'),
            'mode' => $premiere->mode,
            'reference' => $premiere->reference,
            'inscription_id' => $inscription->id,
            'etudiant' => $inscription->etudiant,
            'classe' => $inscription->classe->nom,
            'annee_scolaire' => $inscription->anneeScolaire->libelle,
            'lignes' => $lignes->map(fn ($l) => [
                'type' => $l->type, 'mois' => $l->mois, 'montant' => $l->montant,
            ])->values(),
            'total' => (int) $lignes->sum('montant'),
            'situation' => $inscription->situation(),
        ];
    }
}
