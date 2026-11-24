<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Frais;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Grille des frais annuels : règles par classe, nouveaux élèves, famille, facultatif, ancien droit. */
class FraisTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::where('identifiant', 'admin')->first());
    }

    private function frais(string $libelle): int
    {
        return Frais::where('libelle', $libelle)->value('id');
    }

    private function inscrire(string $classe, array $parents = []): int
    {
        return $this->postJson('/api/inscriptions', $parents + [
            'nom' => 'Rabe', 'prenom' => 'Mialy', 'sexe' => 'F', 'date_naissance' => '2014-02-10',
            'nom_pere' => 'Rabe Paul', 'telephone_pere' => '034 12 345 67',
            'classe_id' => Classe::where('nom', $classe)->value('id'),
            'annee_scolaire_id' => AnneeScolaire::active()->id,
        ])->assertCreated()->json('id');
    }

    /** Frais affichés pour une inscription : libellé => [obligatoire, facultatif, reste, compte]. */
    private function fraisDe(int $id): array
    {
        return collect($this->getJson("/api/inscriptions/{$id}")->assertOk()->json('situation.frais'))
            ->mapWithKeys(fn ($f) => [$f['libelle'] => [$f['obligatoire'], $f['facultatif'], $f['reste'], $f['compte']]])
            ->all();
    }

    public function test_frais_selon_la_classe_et_le_type_d_inscription(): void
    {
        // nouvel élève en MGS : droit d'entrée (préscolaire) + assurance + FRAM + t-shirt
        $mgs = $this->inscrire('MGS');
        $this->assertSame([
            "Droit d'entrée" => [true, false, 5000, true],
            'Frais généraux - Assurance' => [true, false, 60000, true],
            'Cotisation FRAM & S.E' => [true, false, 30000, true],
            'T-shirt + cravate' => [true, false, 15000, true],
        ], $this->fraisDe($mgs));

        // nouvel élève en 3ème : pas de droit d'entrée, mais examen blanc ; écolage 30 000
        $troisieme = $this->inscrire('3ème', ['telephone_pere' => '033 11 111 11']);
        $f = $this->fraisDe($troisieme);
        $this->assertArrayNotHasKey("Droit d'entrée", $f);
        $this->assertSame([true, false, 3000, true], $f['Examen blanc']);
        $this->getJson("/api/inscriptions/{$troisieme}")
            ->assertJsonPath('situation.ecolage_mensuel', 30000)
            ->assertJsonPath('situation.frais_total', 60000 + 30000 + 15000 + 3000);

        // élève réinscrit en CM2 : t-shirt facultatif (non compté), examen blanc obligatoire
        $ancien = Inscription::whereHas('anneeScolaire', fn ($q) => $q->where('active', false))->first()->etudiant_id;
        $reinscrit = $this->postJson('/api/reinscriptions', [
            'etudiant_id' => $ancien, 'classe_id' => Classe::where('nom', 'CM2')->value('id'),
            'annee_scolaire_id' => AnneeScolaire::active()->id,
        ])->assertCreated()->json('id');
        $f = $this->fraisDe($reinscrit);
        $this->assertSame([false, true, 15000, false], $f['T-shirt + cravate']);
        $this->assertSame([true, false, 3000, true], $f['Examen blanc']);
        $this->getJson("/api/inscriptions/{$reinscrit}")->assertJsonPath('situation.frais_total', 60000 + 30000 + 3000);

        // le t-shirt facultatif peut quand même être acheté ; il compte alors comme payé
        $this->postJson('/api/paiements', [
            'inscription_id' => $reinscrit, 'frais' => [$this->frais('T-shirt + cravate')],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('total', 15000)->assertJsonPath('lignes.0.designation', 'T-shirt + cravate');
    }

    public function test_cotisation_fram_une_seule_fois_par_famille(): void
    {
        // même père (numéro écrit différemment) => même famille
        $aine = $this->inscrire('5ème', ['telephone_pere' => '034 12 345 67']);
        $cadet = $this->inscrire('CP2', ['prenom' => 'Toky', 'telephone_pere' => '0341234567']);
        $autre = $this->inscrire('CP2', ['nom' => 'Zafy', 'telephone_pere' => '032 55 555 55']);
        $fram = $this->frais('Cotisation FRAM & S.E');

        $this->assertSame([true, false, 30000, true], $this->fraisDe($aine)['Cotisation FRAM & S.E']);   // 1er enfant
        $this->assertSame([true, false, 0, false], $this->fraisDe($cadet)['Cotisation FRAM & S.E']);    // exempté
        $this->assertSame([true, false, 30000, true], $this->fraisDe($autre)['Cotisation FRAM & S.E']);  // autre famille

        // pas encaissable sur le cadet…
        $this->postJson('/api/paiements', [
            'inscription_id' => $cadet, 'frais' => [$fram], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('frais');

        // …payée sur l'aîné, elle est indiquée « réglée par la famille » chez le cadet
        $this->postJson('/api/paiements', [
            'inscription_id' => $aine, 'frais' => [$fram], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();
        $note = collect($this->getJson("/api/inscriptions/{$cadet}")->json('situation.frais'))->firstWhere('frais_id', $fram)['note'];
        $this->assertStringContainsString('Réglé par la famille', $note);

        // le dashboard ne compte la cotisation qu'une fois
        $this->assertSame(0, collect($this->getJson("/api/inscriptions/{$cadet}")->json('situation.frais'))->firstWhere('frais_id', $fram)['reste']);
    }

    public function test_ancien_droit_d_inscription_couvre_les_nouveaux_frais(): void
    {
        $id = $this->inscrire('6ème');
        // paiement fait avant la nouvelle grille (ancien « droit d'inscription » de 220 000 Ar)
        Paiement::create([
            'numero_recu' => 'REC-20261101-0001', 'inscription_id' => $id, 'type' => 'droit', 'mois' => null,
            'montant' => 220000, 'montant_du' => 220000, 'mode' => 'especes', 'date_paiement' => '2026-11-01',
        ]);

        $s = $this->getJson("/api/inscriptions/{$id}")->assertOk()->json('situation');
        $this->assertTrue($s['droit_paye']);
        $this->assertSame(0, $s['frais_reste']);
        $this->assertSame(['droit', 'frais-'.$this->frais('T-shirt + cravate')], array_column($s['frais'], 'cle'));
        $this->assertSame(220000 + 10 * 28000, $s['total_du']);   // 6ème : 28 000 / mois

        // les frais obligatoires ne sont plus demandés
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'frais' => [$this->frais('Frais généraux - Assurance')],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('frais');
        $this->getJson('/api/recus/REC-20261101-0001')->assertJsonPath('lignes.0.designation', "Droit d'inscription");
    }

    public function test_parametrage_des_frais(): void
    {
        $this->getJson('/api/frais')->assertOk()->assertJsonCount(5)
            ->assertJsonPath('0.libelle', "Droit d'entrée")
            ->assertJsonPath('0.classe_ids', Classe::whereIn('nom', ['MPS', 'MMS', 'MGS', 'CP1'])->orderBy('id')->pluck('id')->all());

        // nouveau frais, pour tous, par élève
        $id = $this->postJson('/api/frais', [
            'libelle' => 'Carte scolaire', 'montant' => 2000, 'portee' => 'eleve', 'obligatoire' => 'tous',
            'facultatif_autres' => false, 'actif' => true, 'classe_ids' => [],
        ])->assertCreated()->json('id');
        $this->assertArrayHasKey('Carte scolaire', $this->fraisDe($this->inscrire('CE')));

        // modification du montant, validation
        $this->putJson("/api/frais/{$id}", [
            'libelle' => 'Carte scolaire', 'montant' => 2500, 'portee' => 'eleve', 'obligatoire' => 'tous', 'actif' => true,
        ])->assertOk()->assertJsonPath('montant', 2500);
        $this->postJson('/api/frais', ['libelle' => 'Carte scolaire', 'montant' => -1, 'portee' => 'x', 'obligatoire' => 'y'])
            ->assertUnprocessable()->assertJsonValidationErrors(['libelle', 'montant', 'portee', 'obligatoire']);

        // un frais déjà encaissé ne se supprime pas ; un frais jamais encaissé, si
        $assurance = $this->frais('Frais généraux - Assurance');
        $this->postJson('/api/paiements', [
            'inscription_id' => $this->inscrire('CM1', ['telephone_pere' => '032 77 777 77']), 'frais' => [$assurance],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();
        $this->deleteJson("/api/frais/{$assurance}")->assertUnprocessable();
        $this->deleteJson("/api/frais/{$id}")->assertNoContent();
    }
}
