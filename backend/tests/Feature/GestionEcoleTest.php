<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GestionEcoleTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::where('identifiant', 'admin')->first());
    }

    private function anneeActive(): AnneeScolaire
    {
        return AnneeScolaire::active();
    }

    private function nouvelEleve(): array
    {
        return [
            'nom' => 'Rabe', 'prenom' => 'Mialy', 'sexe' => 'F', 'date_naissance' => '2014-02-10',
            'nom_pere' => 'Rabe Paul', 'profession_pere' => 'Chauffeur', 'telephone_pere' => '034 12 345 67',
            'nom_mere' => 'Rasoa Lala', 'profession_mere' => 'Couturière', 'telephone_mere' => '032 98 765 43',
            'classe_id' => 1, 'annee_scolaire_id' => $this->anneeActive()->id,
        ];
    }

    public function test_parents_pere_et_mere(): void
    {
        $res = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated();
        $res->assertJsonPath('etudiant.nom_pere', 'Rabe Paul')
            ->assertJsonPath('etudiant.profession_mere', 'Couturière')
            ->assertJsonMissingPath('etudiant.telephone');

        // un seul parent suffit (ex : mère seule), avec son téléphone
        $mereSeule = array_merge($this->nouvelEleve(), ['nom_pere' => null, 'profession_pere' => null, 'telephone_pere' => null]);
        $this->postJson('/api/inscriptions', $mereSeule)->assertCreated();

        // un parent nommé sans téléphone est refusé
        $this->postJson('/api/inscriptions', array_merge($this->nouvelEleve(), ['telephone_mere' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('telephone_mere');

        // modification de la fiche
        $id = $res->json('etudiant.id');
        $this->putJson("/api/etudiants/{$id}", array_merge($this->nouvelEleve(), ['profession_pere' => 'Mécanicien']))
            ->assertOk()->assertJsonPath('profession_pere', 'Mécanicien');
    }

    public function test_liste_des_eleves_avec_statut_ecolage_du_mois(): void
    {
        $this->travelTo('2026-11-15 09:00');
        $a = $this->postJson('/api/inscriptions', $this->nouvelEleve())->json('id');                         // Rabe
        $b = $this->postJson('/api/inscriptions', array_merge($this->nouvelEleve(), ['nom' => 'Zafy']))->json('id');
        $this->postJson('/api/paiements', [
            'inscription_id' => $a, 'mois' => ['2026-09', '2026-10', '2026-11'], 'mode' => 'especes', 'date_paiement' => '2026-11-15',
        ])->assertCreated();

        // Mois par défaut = mois en cours (novembre, échéance du 10 passée)
        $liste = $this->getJson('/api/inscriptions')->assertOk()->assertJsonPath('mois', '2026-11');
        $this->assertSame('paye', $liste->json('data.0.ecolage_mois.statut'));      // Rabe
        $this->assertSame('en_retard', $liste->json('data.1.ecolage_mois.statut')); // Zafy
        $this->assertSame(3, $liste->json('data.1.mois_en_retard'));                 // sept, oct, nov
        $this->assertArrayNotHasKey('paiements', $liste->json('data.0'));

        // Décembre : échéance pas encore passée
        $this->getJson('/api/inscriptions?mois=2026-12')
            ->assertJsonPath('data.0.ecolage_mois.statut', 'a_payer')
            ->assertJsonPath('data.0.ecolage_mois.echeance', '2026-12-10');

        // Filtres par statut
        $this->getJson('/api/inscriptions?statut=en_retard')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $b);
        $this->getJson('/api/inscriptions?statut=paye')->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $a);
        $this->getJson('/api/inscriptions?statut=a_payer')->assertJsonPath('total', 0);
        $this->getJson('/api/inscriptions?mois=2026-12&statut=a_payer')->assertJsonPath('total', 2);
    }

    public function test_annee_septembre_a_juin_et_echeance_le_10(): void
    {
        $this->assertSame(
            ['2026-09', '2026-10', '2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05', '2027-06'],
            $this->anneeActive()->mois
        );

        $this->travelTo('2026-11-10 16:00');   // le 10 : novembre n'est pas encore en retard
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->json('id');
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'droit' => true, 'mois' => ['2026-09'],
            'mode' => 'especes', 'date_paiement' => '2026-11-10',
        ])->assertCreated();

        $s = $this->getJson("/api/inscriptions/{$id}")->json('situation');
        $this->assertSame('2026-11-10', $s['mois'][2]['echeance']);
        $this->assertSame(1, $s['mois_en_retard']);            // seulement octobre
        $this->assertTrue($s['mois'][1]['en_retard']);          // octobre
        $this->assertFalse($s['mois'][2]['en_retard']);         // novembre, échéance aujourd'hui

        $this->travelTo('2026-11-11 08:00');   // le 11 : novembre passe en retard
        $this->getJson("/api/inscriptions/{$id}")
            ->assertJsonPath('situation.mois_en_retard', 2)
            ->assertJsonPath('situation.montant_en_retard', 2 * 23000);
        $this->getJson('/api/dashboard')->assertJsonPath('eleves_en_retard', 1);

        // juillet ne fait plus partie de l'année scolaire
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2027-07'], 'mode' => 'especes', 'date_paiement' => '2026-11-11',
        ])->assertUnprocessable()->assertJsonValidationErrors('mois');
    }

    public function test_inscription_nouvel_eleve(): void
    {
        $res = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated();

        $res->assertJsonPath('type', 'inscription')
            ->assertJsonPath('situation.droit_paye', false)
            ->assertJsonPath('situation.total_du', 220000 + 23000 * 10);
        $this->assertMatchesRegularExpression('/^ETU-\d{4}-\d{4}$/', $res->json('etudiant.matricule'));
    }

    public function test_inscription_champs_obligatoires(): void
    {
        $this->postJson('/api/inscriptions', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'sexe', 'date_naissance', 'nom_pere', 'nom_mere', 'telephone_pere', 'classe_id']);
    }

    public function test_reinscription_ancien_eleve(): void
    {
        $ancien = Etudiant::first();
        $payload = ['etudiant_id' => $ancien->id, 'classe_id' => 2, 'annee_scolaire_id' => $this->anneeActive()->id];

        $this->postJson('/api/reinscriptions', $payload)->assertCreated()->assertJsonPath('type', 'reinscription');
        // pas deux fois la même année
        $this->postJson('/api/reinscriptions', $payload)->assertUnprocessable()->assertJsonValidationErrors('etudiant_id');
    }

    public function test_paiement_droit_et_ecolages(): void
    {
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->json('id');

        $recu = $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'droit' => true, 'mois' => ['2026-10', '2026-11'],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();

        $recu->assertJsonPath('total', 220000 + 2 * 23000)
            ->assertJsonPath('situation.droit_paye', true)
            ->assertJsonPath('situation.reste', 8 * 23000);

        // doublons refusés
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'droit' => true, 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('droit');

        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2026-11'], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('mois');

        // mois hors année scolaire
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2027-09'], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable();

        // référence obligatoire hors espèces
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2026-12'], 'mode' => 'mobile_money', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('reference');

        $this->getJson('/api/recus/'.$recu->json('numero_recu'))->assertOk()->assertJsonCount(3, 'lignes');
        $pdf = $this->get('/api/recus/'.$recu->json('numero_recu').'/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->getJson('/api/recus/INEXISTANT/pdf')->assertNotFound();
        $this->getJson('/api/paiements')->assertOk()->assertJsonPath('data.0.total', 266000);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('effectif', 1);
        $this->getJson('/api/inscriptions?q=Rabe')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/etudiants?q=Rak')->assertOk()->assertJsonPath('data.0.derniere_inscription.annee_scolaire.libelle', '2025-2026');
    }

    public function test_grille_des_classes(): void
    {
        $classes = $this->getJson('/api/classes')->assertOk()->json();

        $this->assertSame(
            ['MPS', 'MMS', 'MGS', 'CP1', 'CP2', 'CE', 'CM1', 'CM2', '6ème', '5ème', '4ème', '3ème'],
            array_column($classes, 'nom')
        );
        $this->assertSame([220000], array_values(array_unique(array_column($classes, 'droit_inscription'))));
        $this->assertSame(23000, $classes[7]['ecolage_mensuel']);  // CM2
        $this->assertSame(25000, $classes[8]['ecolage_mensuel']);  // 6ème
        $this->assertSame('3ème', end($classes)['nom']);           // classe finale
    }

    public function test_changement_de_tarif_ne_touche_pas_les_elements_deja_payes(): void
    {
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->json('id');
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'droit' => true, 'mois' => ['2026-10'],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();

        Classe::find(1)->update(['droit_inscription' => 300000, 'ecolage_mensuel' => 30000]);

        $this->getJson("/api/inscriptions/{$id}")->assertOk()
            ->assertJsonPath('situation.droit_paye', true)
            ->assertJsonPath('situation.total_paye', 220000 + 23000)
            ->assertJsonPath('situation.reste', 9 * 30000);   // seuls les 9 mois restants, au nouveau tarif
    }
}
