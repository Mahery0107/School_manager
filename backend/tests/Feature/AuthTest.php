<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // même base de départ que GestionEcoleTest (la base en mémoire n'est migrée/seedée qu'une fois)
    protected $seed = true;

    public function test_api_protegee_sans_connexion(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/inscriptions')->assertUnauthorized();
        $this->postJson('/api/paiements', [])->assertUnauthorized();
        // reste public : nom de l'école (écran de connexion)
        $this->getJson('/api/etablissement')->assertOk();
    }

    public function test_connexion_compte_admin_par_defaut(): void
    {
        $this->postJson('/api/login', ['identifiant' => 'admin', 'password' => 'mauvais'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifiant');

        $token = $this->postJson('/api/login', ['identifiant' => 'admin', 'password' => 'admin123'])
            ->assertOk()
            ->assertJsonPath('user.identifiant', 'admin')
            ->assertJsonMissingPath('user.password')
            ->json('token');

        $this->withToken($token)->getJson('/api/moi')->assertOk()->assertJsonPath('name', 'Administrateur');
        $this->withToken($token)->getJson('/api/dashboard')->assertOk();

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_modifier_le_compte_admin(): void
    {
        $token = $this->postJson('/api/login', ['identifiant' => 'admin', 'password' => 'admin123'])->json('token');

        // mot de passe actuel obligatoire et vérifié
        $this->withToken($token)->putJson('/api/compte', [
            'name' => 'Directeur', 'identifiant' => 'directeur', 'password_actuel' => 'faux',
        ])->assertUnprocessable()->assertJsonValidationErrors('password_actuel');

        // confirmation du nouveau mot de passe obligatoire
        $this->withToken($token)->putJson('/api/compte', [
            'name' => 'Directeur', 'identifiant' => 'directeur', 'password_actuel' => 'admin123',
            'password' => 'nouveau-mdp', 'password_confirmation' => 'autre',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->withToken($token)->putJson('/api/compte', [
            'name' => 'Directeur', 'identifiant' => 'directeur', 'password_actuel' => 'admin123',
            'password' => 'nouveau-mdp', 'password_confirmation' => 'nouveau-mdp',
        ])->assertOk()->assertJsonPath('identifiant', 'directeur');

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['identifiant' => 'admin', 'password' => 'admin123'])->assertUnprocessable();
        $this->postJson('/api/login', ['identifiant' => 'directeur', 'password' => 'nouveau-mdp'])->assertOk();
        $this->assertSame(1, User::count());
    }

    public function test_sauvegardes_protegees(): void
    {
        $this->getJson('/api/sauvegardes')->assertUnauthorized();

        $token = $this->postJson('/api/login', ['identifiant' => 'admin', 'password' => 'admin123'])->json('token');
        $this->withToken($token)->getJson('/api/sauvegardes')->assertOk()->assertJsonStructure(['sauvegardes', 'conserver_jours']);

        // seuls des noms de fichiers .sql / .sql.gz du dossier des sauvegardes sont téléchargeables
        $this->withToken($token)->get('/api/sauvegardes/..%2F..%2F.env')->assertNotFound();
        $this->withToken($token)->get('/api/sauvegardes/.env')->assertNotFound();
        $this->withToken($token)->get('/api/sauvegardes/inexistant.sql.gz')->assertNotFound();
    }
}
