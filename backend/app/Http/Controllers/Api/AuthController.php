<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Connexion du compte administrateur (jeton Sanctum) et modification du compte. */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'identifiant' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], ['identifiant' => 'identifiant', 'password' => 'mot de passe']);

        $user = User::where('identifiant', $data['identifiant'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifiant' => 'Identifiant ou mot de passe incorrect.',
            ]);
        }

        return [
            'token' => $user->createToken('gestion-ecole')->plainTextToken,
            'user' => $user,
        ];
    }

    public function moi(Request $request)
    {
        return $request->user();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    /** Modifie le nom, l'identifiant et/ou le mot de passe (mot de passe actuel obligatoire). */
    public function modifierCompte(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'identifiant' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'password_actuel' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'password_actuel.current_password' => 'Le mot de passe actuel est incorrect.',
        ], [
            'name' => 'nom', 'password_actuel' => 'mot de passe actuel', 'password' => 'nouveau mot de passe',
        ]);

        $user->name = $data['name'];
        $user->identifiant = $data['identifiant'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            // déconnecte les autres sessions, garde la session en cours
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }

        $user->save();

        return $user;
    }
}
