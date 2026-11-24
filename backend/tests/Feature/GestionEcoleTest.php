<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Frais;
use App\Models\Inscription;
use App\Models\Paiement;
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

    /** Id d'un frais de la grille, par libellé. */
    private function frais(string $libelle): int
    {
        return Frais::where('libelle', $libelle)->value('id');
    }

    /** Frais obligatoires d'un nouvel élève de MPS (droit d'entrée, assurance, FRAM, t-shirt) = 110 000 Ar. */
    private function fraisNouveauMps(): array
    {
        return array_map(fn ($l) => $this->frais($l), ["Droit d'entrée", 'Frais généraux - Assurance', 'Cotisation FRAM & S.E', 'T-shirt + cravate']);
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
            'inscription_id' => $id, 'frais' => $this->fraisNouveauMps(), 'mois' => ['2026-09'],
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
            ->assertJsonPath('situation.montant_en_retard', 2 * 25000);
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
            ->assertJsonPath('situation.frais_total', 110000)
            ->assertJsonPath('situation.total_du', 110000 + 25000 * 10);
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
            'inscription_id' => $id, 'frais' => $this->fraisNouveauMps(), 'mois' => ['2026-10', '2026-11'],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();

        $recu->assertJsonPath('total', 110000 + 2 * 25000)
            ->assertJsonPath('situation.droit_paye', true)
            ->assertJsonPath('situation.reste', 8 * 25000)
            ->assertJsonPath('lignes.1.designation', 'Frais généraux - Assurance');

        // doublons refusés
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'frais' => [$this->frais('Frais généraux - Assurance')], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('frais');

        // examen blanc : ne concerne pas la MPS
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'frais' => [$this->frais('Examen blanc')], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('frais');

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

        $this->getJson('/api/recus/'.$recu->json('numero_recu'))->assertOk()->assertJsonCount(6, 'lignes');
        $pdf = $this->get('/api/recus/'.$recu->json('numero_recu').'/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->getJson('/api/recus/INEXISTANT/pdf')->assertNotFound();
        $this->getJson('/api/paiements')->assertOk()->assertJsonPath('data.0.total', 160000);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('effectif', 1);
        $this->getJson('/api/inscriptions?q=Rabe')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/etudiants?q=Rak')->assertOk()->assertJsonPath('data.0.derniere_inscription.annee_scolaire.libelle', '2025-2026');
    }

    public function test_paiement_en_plusieurs_fois(): void
    {
        $this->travelTo('2026-11-15');
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated()->json('id'); // MPS : 25 000 / mois
        $assurance = 'frais-'.$this->frais('Frais généraux - Assurance');   // 60 000
        $payer = fn (array $corps) => $this->postJson('/api/paiements', $corps + [
            'inscription_id' => $id, 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ]);

        // 1er versement : 25 000 sur l'assurance (60 000) et 10 000 sur novembre (25 000)
        $r1 = $payer(['frais' => [$this->frais('Frais généraux - Assurance')], 'mois' => ['2026-11'], 'montants' => [$assurance => 25000, '2026-11' => 10000]])
            ->assertCreated()
            ->assertJsonPath('total', 35000)
            ->assertJsonPath('situation.droit_paye', false);
        $this->assertSame(35000, collect($r1->json('situation.frais'))->firstWhere('cle', $assurance)['reste']);
        $nov = collect($r1->json('situation.mois'))->firstWhere('mois', '2026-11');
        $this->assertSame([false, true, 10000, 15000], [$nov['paye'], $nov['partiel'], $nov['montant_paye'], $nov['reste']]);
        $this->assertSame(15000, collect($r1->json('lignes'))->firstWhere('mois', '2026-11')['reste_apres']);

        // un mois partiellement payé reste « en retard » / non payé dans la liste
        $this->getJson('/api/inscriptions?mois=2026-11&statut=paye')->assertJsonPath('total', 0);
        $this->getJson('/api/inscriptions?mois=2026-11&statut=en_retard')->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.ecolage_mois.montant_paye', 10000)
            ->assertJsonPath('data.0.ecolage_mois.reste', 15000);

        // on ne peut pas verser plus que le reste
        $payer(['mois' => ['2026-11'], 'montants' => ['2026-11' => 16000]])
            ->assertUnprocessable()->assertJsonValidationErrors('montants.2026-11');
        $payer(['frais' => [$this->frais('Frais généraux - Assurance')], 'montants' => [$assurance => 0]])->assertUnprocessable();

        // un changement de tarif ne modifie pas un élément déjà entamé
        Classe::where('nom', 'MPS')->update(['ecolage_mensuel' => 30000]);
        Frais::where('id', $this->frais('Frais généraux - Assurance'))->update(['montant' => 80000]);

        // 2e versement sans montant : solde exactement le reste
        $r2 = $payer(['frais' => [$this->frais('Frais généraux - Assurance')], 'mois' => ['2026-11']])->assertCreated()
            ->assertJsonPath('total', 35000 + 15000);
        $this->assertTrue(collect($r2->json('situation.frais'))->firstWhere('cle', $assurance)['solde']);
        $this->assertTrue(collect($r2->json('situation.mois'))->firstWhere('mois', '2026-11')['paye']);
        $this->assertSame(0, collect($r2->json('lignes'))->firstWhere('type', 'frais')['reste_apres']);
        $this->assertSame(25000, collect($r2->json('lignes'))->firstWhere('type', 'frais')['deja_paye']);

        // élément soldé : plus aucun versement accepté
        $payer(['mois' => ['2026-11'], 'montants' => ['2026-11' => 1000]])
            ->assertUnprocessable()->assertJsonValidationErrors('mois');
        $this->getJson('/api/inscriptions?mois=2026-11&statut=paye')->assertJsonPath('total', 1);
        $this->get('/api/recus/'.$r2->json('numero_recu').'/pdf')->assertOk();
    }

    public function test_annulation_d_un_recu(): void
    {
        $this->travelTo('2026-11-15 10:00');
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated()->json('id');
        $payer = fn (array $corps) => $this->postJson('/api/paiements', $corps + [
            'inscription_id' => $id, 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ]);

        $mauvais = $payer(['frais' => $this->fraisNouveauMps(), 'mois' => ['2026-11']])->assertCreated()
            ->assertJsonPath('encaisse_par', 'Administrateur')
            ->json('numero_recu');

        // motif obligatoire
        $this->postJson("/api/recus/{$mauvais}/annuler", [])->assertUnprocessable()->assertJsonValidationErrors('motif');

        $this->postJson("/api/recus/{$mauvais}/annuler", ['motif' => 'Erreur de montant'])->assertOk()
            ->assertJsonPath('annule', true)
            ->assertJsonPath('annule_par', 'Administrateur')
            ->assertJsonPath('annule_motif', 'Erreur de montant')
            ->assertJsonPath('situation.total_paye', 0)        // le reçu annulé ne compte plus
            ->assertJsonPath('situation.droit_paye', false);
        $this->postJson("/api/recus/{$mauvais}/annuler", ['motif' => 'encore'])->assertUnprocessable();
        $this->postJson('/api/recus/INEXISTANT/annuler', ['motif' => 'test'])->assertNotFound();

        // le mois redevient à payer, le journal garde le reçu marqué annulé, le tableau de bord ne le compte pas
        $this->getJson('/api/inscriptions?mois=2026-11&statut=paye')->assertJsonPath('total', 0);
        $this->getJson('/api/paiements')->assertJsonPath('data.0.numero_recu', $mauvais)->assertJsonPath('data.0.annule', true);
        $this->getJson('/api/dashboard')->assertJsonPath('encaisse_aujourdhui', 0);
        $this->get("/api/recus/{$mauvais}/pdf")->assertOk();

        // correction : on encaisse à nouveau, avec un nouveau numéro
        $bon = $payer(['frais' => [$this->frais('Frais généraux - Assurance')], 'montants' => ['frais-'.$this->frais('Frais généraux - Assurance') => 50000]])->assertCreated()
            ->assertJsonPath('situation.total_paye', 50000)->json('numero_recu');
        $this->assertNotSame($mauvais, $bon);

        // une inscription ayant un historique (même annulé) ne peut pas être supprimée
        $this->deleteJson("/api/inscriptions/{$id}")->assertUnprocessable();
    }

    public function test_numeros_de_recu_uniques_et_successifs(): void
    {
        $this->travelTo('2026-11-16 09:00');
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated()->json('id');
        // un reçu existait déjà ce jour-là avant la mise en place du compteur
        Paiement::create([
            'numero_recu' => 'REC-20261116-0007', 'inscription_id' => $id, 'type' => 'ecolage', 'mois' => '2026-09',
            'montant' => 23000, 'montant_du' => 23000, 'mode' => 'especes', 'date_paiement' => '2026-11-16',
        ]);

        $numeros = collect(['2026-10', '2026-11', '2026-12'])->map(fn ($m) => $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => [$m], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated()->json('numero_recu'))->all();

        $this->assertSame(['REC-20261116-0008', 'REC-20261116-0009', 'REC-20261116-0010'], $numeros);

        // nouveau jour : la numérotation repart à 0001
        $this->travelTo('2026-11-17 08:00');
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2027-01'], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertJsonPath('numero_recu', 'REC-20261117-0001');
    }

    public function test_sortie_d_un_eleve_renvoye_ou_parti(): void
    {
        $this->travelTo('2026-12-15');
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated()->json('id'); // MPS, 25 000 / mois
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'frais' => $this->fraisNouveauMps(), 'mois' => ['2026-09', '2026-10'],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();

        // motif et date obligatoires, date pas dans le futur
        $this->postJson("/api/inscriptions/{$id}/sortie", [])->assertUnprocessable()
            ->assertJsonValidationErrors(['motif_sortie', 'date_sortie']);
        $this->postJson("/api/inscriptions/{$id}/sortie", ['motif_sortie' => 'renvoye', 'date_sortie' => '2027-01-05'])
            ->assertUnprocessable()->assertJsonValidationErrors('date_sortie');

        // renvoyé le 20 novembre : septembre → novembre restent dus, décembre → juin ne le sont plus
        $s = $this->postJson("/api/inscriptions/{$id}/sortie", [
            'motif_sortie' => 'renvoye', 'date_sortie' => '2026-11-20', 'remarque_sortie' => 'Conseil de discipline',
        ])->assertOk()->assertJsonPath('motif_sortie', 'renvoye')->json('situation');
        $this->assertSame(['2026-09', '2026-10', '2026-11'], array_column($s['mois'], 'mois'));
        $this->assertSame(25000, $s['reste']);   // novembre seul reste à payer
        $this->assertTrue($s['sorti']);
        $this->postJson("/api/inscriptions/{$id}/sortie", ['motif_sortie' => 'abandon', 'date_sortie' => '2026-11-21'])
            ->assertUnprocessable();

        // plus parmi les présents ni dans l'effectif, mais visible dans les sortis
        $this->getJson('/api/inscriptions?q=Rabe')->assertJsonPath('total', 0);
        $this->getJson('/api/inscriptions?q=Rabe&presence=sortis')->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.ecolage_mois.statut', 'non_du');   // décembre (mois en cours)
        $this->getJson('/api/inscriptions?q=Rabe&presence=tous&mois=2026-11&statut=en_retard')->assertJsonPath('total', 1);
        $this->getJson('/api/inscriptions?q=Rabe&presence=tous&mois=2026-12&statut=non_paye')->assertJsonPath('total', 0);
        $this->getJson('/api/dashboard')->assertJsonPath('effectif', 0)->assertJsonPath('sortis', 1);

        // les mois après le départ ne peuvent plus être encaissés, les mois dus si
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2026-12'], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('mois');
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'mois' => ['2026-11'], 'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('situation.reste', 0);

        // une inscription avec paiements ne peut pas être supprimée ; réintégration possible
        $this->deleteJson("/api/inscriptions/{$id}")->assertUnprocessable();
        $this->deleteJson("/api/inscriptions/{$id}/sortie")->assertOk()
            ->assertJsonPath('date_sortie', null)
            ->assertJsonCount(10, 'situation.mois');
        $this->getJson('/api/dashboard')->assertJsonPath('effectif', 1);
    }

    public function test_suppression_d_une_inscription_faite_par_erreur(): void
    {
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->assertCreated()->json('id');
        $etudiantId = Inscription::find($id)->etudiant_id;

        // aucun paiement : suppression définitive de l'inscription et de la fiche de l'élève
        $this->deleteJson("/api/inscriptions/{$id}")->assertOk()->assertJsonPath('eleve_supprime', true);
        $this->assertDatabaseMissing('etudiants', ['id' => $etudiantId]);
    }

    public function test_grille_des_classes(): void
    {
        $classes = $this->getJson('/api/classes')->assertOk()->json();

        $this->assertSame(
            ['MPS', 'MMS', 'MGS', 'CP1', 'CP2', 'CE', 'CM1', 'CM2', '6ème', '5ème', '4ème', '3ème'],
            array_column($classes, 'nom')
        );
        $this->assertSame(25000, $classes[0]['ecolage_mensuel']);  // MPS
        $this->assertSame(25000, $classes[7]['ecolage_mensuel']);  // CM2
        $this->assertSame(28000, $classes[8]['ecolage_mensuel']);  // 6ème
        $this->assertSame(28000, $classes[9]['ecolage_mensuel']);  // 5ème
        $this->assertSame(30000, $classes[10]['ecolage_mensuel']); // 4ème
        $this->assertSame(30000, $classes[11]['ecolage_mensuel']); // 3ème
        $this->assertSame('3ème', end($classes)['nom']);           // classe finale
    }

    public function test_changement_de_tarif_ne_touche_pas_les_elements_deja_payes(): void
    {
        $id = $this->postJson('/api/inscriptions', $this->nouvelEleve())->json('id');
        $this->postJson('/api/paiements', [
            'inscription_id' => $id, 'frais' => $this->fraisNouveauMps(), 'mois' => ['2026-10'],
            'mode' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertCreated();

        Classe::find(1)->update(['ecolage_mensuel' => 30000]);
        Frais::query()->update(['montant' => 99000]);

        $this->getJson("/api/inscriptions/{$id}")->assertOk()
            ->assertJsonPath('situation.droit_paye', true)
            ->assertJsonPath('situation.total_paye', 110000 + 25000)
            ->assertJsonPath('situation.reste', 9 * 30000);   // seuls les 9 mois restants, au nouveau tarif
    }
}
