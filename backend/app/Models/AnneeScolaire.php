<?php

namespace App\Models;

use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeScolaire extends Model
{
    protected $table = 'annees_scolaires';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'active'];

    protected $casts = [
        'date_debut' => 'date:Y-m-d',
        'date_fin' => 'date:Y-m-d',
        'active' => 'boolean',
    ];

    protected $appends = ['mois'];

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public static function active(): ?self
    {
        return static::where('active', true)->first();
    }

    /** Liste des mois scolaires au format YYYY-MM (ex : 2026-10 … 2027-07). */
    public function getMoisAttribute(): array
    {
        if (! $this->date_debut || ! $this->date_fin) {
            return [];
        }

        $periode = CarbonPeriod::create(
            $this->date_debut->copy()->startOfMonth(),
            '1 month',
            $this->date_fin->copy()->startOfMonth()
        );

        return collect($periode)->map(fn ($d) => $d->format('Y-m'))->values()->all();
    }
}
