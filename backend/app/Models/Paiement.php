<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Paiement extends Model
{
    protected $fillable = [
        'numero_recu', 'inscription_id', 'type', 'mois', 'frais_id', 'montant', 'montant_du', 'mode', 'reference', 'date_paiement',
        'encaisse_par', 'annule_le', 'annule_par', 'annule_motif',
    ];

    protected $casts = [
        'montant' => 'integer',
        'montant_du' => 'integer',
        'frais_id' => 'integer',
        'date_paiement' => 'date:Y-m-d',
        'annule_le' => 'datetime',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function frais(): BelongsTo
    {
        return $this->belongsTo(Frais::class);
    }

    /** Libellé de la ligne (reçu, historique) : frais, écolage du mois, ou ancien droit d'inscription. */
    public function designation(): string
    {
        return match ($this->type) {
            'frais' => $this->frais?->libelle ?? 'Frais',
            'droit' => "Droit d'inscription",
            default => 'Écolage — '.ucfirst(Carbon::createFromFormat('Y-m-d', $this->mois.'-01')->locale('fr')->translatedFormat('F Y')),
        };
    }

    /** Paiements non annulés (les seuls qui comptent dans les situations et les totaux). */
    public function scopeActifs(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('annule_le'));
    }

    /**
     * Génère un numéro de reçu du type REC-20261117-0001, unique même si deux postes encaissent en même temps :
     * le compteur du jour (table recu_sequences) est verrouillé jusqu'à la fin de la transaction en cours.
     * À appeler à l'intérieur d'une transaction (DB::transaction).
     */
    public static function genererNumeroRecu(): string
    {
        $jour = now()->format('Ymd');
        $prefixe = "REC-{$jour}-";

        if (! DB::table('recu_sequences')->where('jour', $jour)->exists()) {
            // premier reçu du jour avec le compteur : on repart du plus grand numéro déjà émis ce jour-là
            $dernier = static::where('numero_recu', 'like', $prefixe.'%')->max('numero_recu');
            DB::table('recu_sequences')->insertOrIgnore([
                'jour' => $jour,
                'dernier' => $dernier ? (int) substr($dernier, strlen($prefixe)) : 0,
            ]);
        }

        $numero = (int) DB::table('recu_sequences')->where('jour', $jour)->lockForUpdate()->value('dernier') + 1;
        DB::table('recu_sequences')->where('jour', $jour)->update(['dernier' => $numero]);

        return $prefixe.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
