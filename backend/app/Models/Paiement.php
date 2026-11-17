<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $fillable = [
        'numero_recu', 'inscription_id', 'type', 'mois', 'montant', 'mode', 'reference', 'date_paiement',
    ];

    protected $casts = [
        'montant' => 'integer',
        'date_paiement' => 'date:Y-m-d',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /** Génère un numéro de reçu du type REC-20261117-0001. */
    public static function genererNumeroRecu(): string
    {
        $prefix = 'REC-'.now()->format('Ymd').'-';
        $dernier = static::where('numero_recu', 'like', $prefix.'%')->orderByDesc('numero_recu')->value('numero_recu');
        $numero = $dernier ? ((int) substr($dernier, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
