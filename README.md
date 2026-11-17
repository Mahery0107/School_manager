# Gestion École — Collège FIANARAN-TSOA Ambohimiandra

Application de gestion scolaire :

- **inscription** des nouveaux élèves ;
- **réinscription** des anciens élèves ;
- **paiement** du droit d'inscription et des écolages mensuels ;
- **reçu PDF** affiché à l'écran puis imprimé.

| Dossier | Contenu |
|---|---|
| [`backend/`](backend/) | API REST **Laravel 13** (PHP 8.3), base **MySQL `school_manager`** |
| [`frontend/`](frontend/) | Interface **React 19 + Vite** + React Router |

---

## 1. Prérequis

- PHP 8.3 ou plus, avec les extensions `pdo_mysql`, `mbstring`, `gd` et `dom` (pour les PDF)
- [Composer](https://getcomposer.org/)
- Node.js 20.19 ou plus, avec npm
- Un serveur MySQL local avec phpMyAdmin (WAMP, XAMPP, Laragon…) : http://localhost/phpmyadmin

## 2. Installation (première fois)

```bash
# Base de données : créer la base "school_manager" dans phpMyAdmin (interclassement utf8mb4_unicode_ci)

# Backend
cd backend
composer install
cp .env.example .env          # puis régler les variables (voir section 3)
php artisan key:generate
php artisan migrate           # crée les tables
php artisan db:seed --class=ClasseSeeder   # crée les 12 classes et leurs tarifs

# Frontend
cd ../frontend
npm install
```

Connectez-vous avec le compte administrateur créé par `migrate` : identifiant **`admin`**, mot de passe **`admin123`**.
**Changez ce mot de passe tout de suite** dans **Paramètres → Compte administrateur**.
Créez ensuite l'année scolaire (ex. `2026-2027`) dans **Paramètres**.

## 3. Configuration (`backend/.env`)

```dotenv
APP_ECOLE="Collège FIANARAN-TSOA Ambohimiandra"   # nom affiché dans l'application et sur les reçus
JOUR_LIMITE_ECOLAGE=10                             # écolage à payer au plus tard le 10 du mois

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_manager
DB_USERNAME=root
DB_PASSWORD=
```

Relancez `php artisan serve` après chaque modification du `.env`.

> Les tables sont créées en **InnoDB**, imposé dans `config/database.php`. Ce serveur MySQL crée des tables MyISAM par défaut, et MyISAM ne gère ni les transactions ni les clés étrangères.

## 4. Démarrage (chaque jour)

Ouvrez deux terminaux :

```bash
# Terminal 1 : API
cd backend
php artisan serve        # http://127.0.0.1:8000

# Terminal 2 : interface
cd frontend
npm run dev              # http://localhost:5173  ← adresse à ouvrir dans le navigateur
```

Vite redirige automatiquement vers Laravel les appels faits à `/api`.

---

## 5. Fonctionnalités

| Page | Rôle |
|---|---|
| **Tableau de bord** | Effectif, sommes encaissées, élèves en retard, répartition par classe |
| **Élèves** | Liste des inscrits par année et par classe, avec le **statut de l'écolage du mois** (Payé / À payer avant le 10 / En retard). Filtres par mois et par statut |
| **Fiche élève** | Identité, parents (père et mère), historique des inscriptions, situation financière, modification |
| **Inscription** | Nouvel élève : le matricule est attribué automatiquement, puis l'élève est inscrit pour l'année choisie |
| **Réinscription** | Ancien élève : la classe supérieure est proposée. La 3ème est la classe finale |
| **Paiement** | Droit d'inscription et/ou plusieurs mois d'écolage en un seul reçu |
| **Journal** | Liste des reçus, avec réimpression |
| **Paramètres** | Années scolaires (dont l'année active), classes et tarifs, **compte administrateur** (nom, identifiant, mot de passe) |

**Bouton « Imprimer »** : le reçu s'ouvre d'abord en **PDF** dans une fenêtre d'aperçu. Vous pouvez ensuite l'**imprimer** ou le **télécharger**.

### Accès sécurisé

- Toute l'application est protégée par le **compte administrateur** : sans connexion, aucune donnée n'est accessible, même en appelant l'API directement.
- La session dure **12 heures** (`SANCTUM_EXPIRATION`, en minutes), puis il faut se reconnecter. Le bouton **Déconnexion** se trouve en bas du menu.
- Après 5 essais de connexion en une minute, les tentatives sont bloquées pendant une minute.
- Pour modifier le compte, le mot de passe actuel est obligatoire. Changer le mot de passe déconnecte les autres postes.
- **Mot de passe oublié** : dans un terminal, `cd backend` puis `php artisan tinker --execute="App\Models\User::where('identifiant','admin')->update(['password'=>bcrypt('nouveau-mdp')])"` (adapter l'identifiant si vous l'avez changé).

## 6. Règles de gestion

### Tarifs

| Classes | Droit d'inscription | Écolage mensuel |
|---|---|---|
| MPS, MMS, MGS (maternelle) | 220 000 Ar | 23 000 Ar |
| CP1, CP2, CE, CM1, CM2 | 220 000 Ar | 23 000 Ar |
| 6ème, 5ème, 4ème, 3ème | 220 000 Ar | 25 000 Ar |

Les tarifs se modifient dans **Paramètres**. Un mois déjà payé garde le montant payé à l'époque. Les mois non encore payés suivent le nouveau tarif.

### Année scolaire et écolage

- L'année scolaire va de **septembre à juin**, soit 10 mois d'écolage.
- L'écolage d'un mois se paie **au plus tard le 10** de ce mois. Passé cette date, le mois est marqué **En retard**.
- Le droit d'inscription se paie **une seule fois par année**.
- Un même mois ne peut jamais être payé deux fois.

### Élèves et inscriptions

- Les matricules suivent le format `ETU-AAAA-0001`.
- Un élève n'a qu'**une seule inscription par année scolaire**.
- Une réinscription n'est possible que si l'élève a déjà été inscrit une année précédente.

### Fiche élève (parents)

- Pour chaque parent : nom, profession et téléphone.
- Il faut renseigner au moins un parent et au moins un numéro de téléphone.

### Paiements et reçus

- Les montants sont **calculés par le serveur** à partir des tarifs de la classe. Ils ne peuvent pas être saisis à la main.
- Les reçus suivent le format `REC-AAAAMMJJ-0001`. Un même reçu peut regrouper le droit d'inscription et plusieurs mois.
- Modes de paiement : espèces, mobile money, chèque, virement. Une **référence** est obligatoire pour tout mode autre que les espèces.

---

## 7. Données et sauvegarde

> ⚠️ **La base `school_manager` contient les vraies données de l'école.**
>
> - Ne **jamais** lancer `php artisan migrate:fresh`, `migrate:refresh` ou `db:seed` : ces commandes effacent ou mélangent les données. Seul `ClasseSeeder` peut être relancé sans risque.
> - Pour une mise à jour du code, `php artisan migrate` suffit : il n'ajoute que les nouvelles tables ou colonnes.
> - **Sauvegardez régulièrement** : phpMyAdmin → base `school_manager` → onglet **Exporter** → **Exécuter**. Faites-le aussi avant toute mise à jour.
> - **Restaurer une sauvegarde** : phpMyAdmin → `school_manager` → onglet **Importer**, puis choisir le fichier `.sql`.

L'ancien fichier `backend/database/database.sqlite` n'est plus utilisé. C'est une ancienne copie, d'avant le passage à MySQL.

## 8. Tests

```bash
cd backend
php artisan test
```

Les tests utilisent une base SQLite **en mémoire**, réglée dans `phpunit.xml`. Ils ne touchent jamais à MySQL.

---

## 9. API (référence technique)

Toutes les routes sauf `/api/etablissement` et `/api/login` exigent d'être connecté (sinon `401`).

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/etablissement` | Nom de l'établissement (public) |
| POST | `/api/login` | Connexion (public) : renvoie un jeton à envoyer en `Authorization: Bearer …` |
| GET, POST | `/api/moi`, `/api/logout` | Compte connecté, déconnexion |
| PUT | `/api/compte` | Modifier le compte administrateur |
| GET | `/api/dashboard` | Statistiques de l'année active |
| GET, POST | `/api/annees-scolaires` | Liste et création des années scolaires |
| POST | `/api/annees-scolaires/{id}/activer` | Définir l'année active |
| GET, POST, PUT, DELETE | `/api/classes` | Classes et tarifs |
| GET | `/api/etudiants?q=` | Recherche d'élèves |
| GET, PUT | `/api/etudiants/{id}` | Fiche avec historique, modification |
| GET | `/api/inscriptions?annee_scolaire_id=&classe_id=&mois=AAAA-MM&statut=` | Inscrits avec le statut de l'écolage du mois (`paye`, `a_payer`, `en_retard`, `non_paye`) |
| POST | `/api/inscriptions` | Inscription d'un nouvel élève |
| POST | `/api/reinscriptions` | Réinscription d'un ancien élève |
| GET, DELETE | `/api/inscriptions/{id}` | Inscription avec sa situation financière, suppression |
| GET, POST | `/api/paiements` | Journal des reçus, encaissement |
| GET | `/api/recus/{numero}` | Détail d'un reçu |
| GET | `/api/recus/{numero}/pdf` | Reçu au format PDF (A5) |

### Organisation du code

```
backend/
  app/Models/                      AnneeScolaire, Classe, Etudiant, Inscription (situation financière), Paiement
  app/Http/Controllers/Api/        un contrôleur par ressource
  database/migrations/             structure des tables
  database/seeders/ClasseSeeder    grille des 12 classes et tarifs
  resources/views/recus/pdf.blade.php   modèle du reçu PDF
  tests/Feature/GestionEcoleTest.php
frontend/src/
  pages/                           Tableau, Eleves, FicheEleve, Inscription, Reinscription, Paiement, Journal, Parametres
  components/                      ApercuPdf, ChampsEleve, Situation, Recu, ui…
  api.js                           client axios et formatage (Ariary, mois, dates)
```
