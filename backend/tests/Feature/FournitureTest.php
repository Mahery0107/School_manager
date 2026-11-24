<?php

namespace Tests\Feature;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Fournitures scolaires par année et par classe : saisie, ordre, copie, liste, PDF. */
class FournitureTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::where('identifiant', 'admin')->first());
    }

    private function ajouter(int $annee, int $classe, string $designation, ?string $quantite = null): int
    {
        return $this->postJson('/api/fournitures', [
            'annee_scolaire_id' => $annee, 'classe_id' => $classe, 'designation' => $designation, 'quantite' => $quantite,
        ])->assertCreated()->json('id');
    }

    public function test_saisie_modification_ordre_et_suppression(): void
    {
        $annee = AnneeScolaire::active()->id;
        $cp1 = Classe::where('nom', 'CP1')->value('id');

        $cahier = $this->ajouter($annee, $cp1, 'Cahier 100 pages', '4');
        $stylo = $this->ajouter($annee, $cp1, 'Stylo bleu', '2');
        $this->postJson('/api/fournitures', ['annee_scolaire_id' => $annee, 'classe_id' => $cp1])
            ->assertUnprocessable()->assertJsonValidationErrors('designation');

        // liste de la classe, dans l'ordre de saisie (année active par défaut)
        $this->getJson("/api/fournitures?classe_id={$cp1}")->assertOk()
            ->assertJsonCount(2)->assertJsonPath('0.designation', 'Cahier 100 pages')->assertJsonPath('1.ordre', 2);

        // modification
        $this->putJson("/api/fournitures/{$cahier}", ['designation' => 'Cahier 100 pages grand format', 'quantite' => '5', 'remarque' => 'couvert en bleu'])
            ->assertOk()->assertJsonPath('quantite', '5')->assertJsonPath('remarque', 'couvert en bleu');

        // nouvel ordre
        $this->postJson('/api/fournitures/ordre', ['ids' => [$stylo, $cahier]])->assertNoContent();
        $this->getJson("/api/fournitures?classe_id={$cp1}")->assertJsonPath('0.designation', 'Stylo bleu');

        $this->deleteJson("/api/fournitures/{$stylo}")->assertNoContent();
        $this->getJson("/api/fournitures?classe_id={$cp1}")->assertJsonCount(1);
    }

    public function test_copie_depuis_une_autre_annee_et_pdf(): void
    {
        $ancienne = AnneeScolaire::where('active', false)->value('id');
        $active = AnneeScolaire::active()->id;
        $cm2 = Classe::where('nom', 'CM2')->value('id');
        $troisieme = Classe::where('nom', '3ème')->value('id');

        $this->ajouter($ancienne, $cm2, 'Dictionnaire', '1');
        $this->ajouter($ancienne, $cm2, 'Règle 30 cm', '1');

        // copie de la liste de l'an dernier vers cette année
        $this->postJson('/api/fournitures/copier', [
            'source_annee_id' => $ancienne, 'source_classe_id' => $cm2, 'annee_scolaire_id' => $active, 'classe_id' => $cm2,
        ])->assertCreated()->assertJsonPath('copiees', 2);
        $this->getJson("/api/fournitures?annee_scolaire_id={$active}&classe_id={$cm2}")->assertJsonCount(2);
        $this->getJson("/api/fournitures?annee_scolaire_id={$ancienne}&classe_id={$cm2}")->assertJsonCount(2); // source intacte

        // rien à copier / copie sur elle-même refusées
        $this->postJson('/api/fournitures/copier', [
            'source_annee_id' => $active, 'source_classe_id' => $troisieme, 'annee_scolaire_id' => $active, 'classe_id' => $cm2,
        ])->assertUnprocessable();
        $this->postJson('/api/fournitures/copier', [
            'source_annee_id' => $active, 'source_classe_id' => $cm2, 'annee_scolaire_id' => $active, 'classe_id' => $cm2,
        ])->assertUnprocessable();

        // PDF d'une classe, de toutes les classes ; année sans fournitures
        $pdf = $this->get("/api/fournitures/pdf?annee_scolaire_id={$active}&classe_id={$cm2}")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get("/api/fournitures/pdf?annee_scolaire_id={$active}")->assertOk();
        $vide = AnneeScolaire::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-06-30']);
        $this->getJson("/api/fournitures/pdf?annee_scolaire_id={$vide->id}")->assertNotFound();
    }
}
