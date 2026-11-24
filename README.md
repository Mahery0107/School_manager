# Gestion École — Collège FIANARAN-TSOA Ambohimiandra

Application de gestion scolaire :

- **inscription** des nouveaux élèves ;
- **réinscription** des anciens élèves ;
- **paiement** des frais de l'année (assurance, cotisation FRAM, t-shirt…) et des écolages mensuels ;
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
| **Fiche élève** | Identité, parents (père et mère), historique des inscriptions, situation financière, modification, **retrait de l'école** (renvoyé, parti) et réintégration |
| **Inscription** | Nouvel élève : le matricule est attribué automatiquement, puis l'élève est inscrit pour l'année choisie |
| **Réinscription** | Ancien élève : la classe supérieure est proposée. La 3ème est la classe finale |
| **Paiement** | Frais de l'année et/ou plusieurs mois d'écolage en un seul reçu |
| **Fournitures scolaires** | Liste des fournitures par classe et par année, imprimable en PDF (une classe ou toutes les classes, une page par classe) |
| **Journal** | Liste des reçus, avec réimpression et **annulation** (reçus annulés barrés) |
| **Paramètres** | Années scolaires (dont l'année active), classes et tarifs, **compte administrateur** (nom, identifiant, mot de passe), **sauvegardes** (liste, sauvegarde immédiate, téléchargement), **fournitures scolaires** par année et par classe (ajout, modification, ordre, copie de la liste de l'an dernier) |

**Bouton « Imprimer »** : le reçu s'ouvre d'abord en **PDF** dans une fenêtre d'aperçu. Vous pouvez ensuite l'**imprimer** ou le **télécharger**.

### Accès sécurisé

- Toute l'application est protégée par le **compte administrateur** : sans connexion, aucune donnée n'est accessible, même en appelant l'API directement.
- La session dure **12 heures** (`SANCTUM_EXPIRATION`, en minutes), puis il faut se reconnecter. Le bouton **Déconnexion** se trouve en bas du menu.
- Après 5 essais de connexion en une minute, les tentatives sont bloquées pendant une minute.
- Pour modifier le compte, le mot de passe actuel est obligatoire. Changer le mot de passe déconnecte les autres postes.
- **Mot de passe oublié** : dans un terminal, `cd backend` puis `php artisan tinker --execute="App\Models\User::where('identifiant','admin')->update(['password'=>bcrypt('nouveau-mdp')])"` (adapter l'identifiant si vous l'avez changé).

## 6. Règles de gestion

### Tarifs

**Écolage mensuel** (par élève) :

| Classes | Écolage / mois |
|---|---|
| MPS, MMS, MGS, CP1, CP2, CE, CM1, CM2 | 25 000 Ar |
| 6ème, 5ème | 28 000 Ar |
| 4ème, 3ème | 30 000 Ar |

**Frais de l'année** (en plus de l'écolage) :

| Frais | Montant | Dû par |
|---|---|---|
| Droit d'entrée | 5 000 Ar | Nouveaux élèves du préscolaire (MPS, MMS, MGS, CP1) |
| Frais généraux - Assurance | 60 000 Ar | Chaque élève |
| Cotisation FRAM & S.E | 30 000 Ar | **Une fois par famille**, même avec plusieurs enfants |
| T-shirt + cravate | 15 000 Ar | Nouveaux élèves ; facultatif pour les autres (à la demande, pour changer) |
| Examen blanc | 3 000 Ar | Élèves de CM2 et de 3ème |

- Les tarifs et les frais se modifient dans **Paramètres**. On peut y ajouter, modifier, désactiver ou supprimer un frais. Pour chaque frais, on choisit les classes concernées, si les nouveaux élèves seuls le doivent, s'il est payé par élève ou par famille, et s'il est facultatif pour les autres.
- **Famille** : deux élèves sont frères et sœurs si le **téléphone du père ou de la mère** est le même (espaces ignorés). La cotisation FRAM est due sur le premier enfant inscrit. Sur la fiche des frères et sœurs, elle apparaît « à payer avec … » puis « réglée par la famille ».
- **Ancien droit d'inscription** : un élève qui a déjà payé l'ancien droit (220 000 Ar) pour l'année ne doit pas les nouveaux frais de cette année. Seul le t-shirt reste proposé, en facultatif.
- Un élément déjà payé ou commencé garde son montant. Les éléments pas encore payés suivent les nouveaux tarifs.

### Année scolaire et écolage

- L'année scolaire va de **septembre à juin**, soit 10 mois d'écolage.
- L'écolage d'un mois se paie **au plus tard le 10** de ce mois. Passé cette date, le mois est marqué **En retard**.
- Les frais de l'année sont dus **une fois par année scolaire**.
- **Paiement en plusieurs fois** : chaque frais et chaque mois peuvent être réglés petit à petit, par versements successifs, jusqu'au montant exact. Un élément entamé est marqué **Partiel** avec son reste à payer. Un mois partiellement payé après le 10 reste **En retard**.
- Un versement ne peut jamais dépasser le reste à payer de l'élément. Une fois soldé, l'élément n'accepte plus de paiement.
- Le montant dû d'un élément est figé au premier versement : un changement de tarif ne touche que les éléments pas encore entamés.

### Élèves et inscriptions

- Les matricules suivent le format `ETU-AAAA-0001`.
- Un élève n'a qu'**une seule inscription par année scolaire**.
- Une réinscription n'est possible que si l'élève a déjà été inscrit une année précédente.

### Élève renvoyé ou qui a quitté l'école

- Sur la fiche de l'élève, cliquez sur **Retirer de l'école**, puis indiquez le motif (renvoyé, a quitté l'école, transféré, autre), la date de départ et une remarque si besoin.
- **Rien n'est effacé.** L'élève disparaît de la liste des présents et de l'effectif. Il reste consultable avec le filtre **Renvoyés / partis** de la liste des élèves.
- Les écolages **après le mois de départ** ne sont plus dus. Ce qui restait à payer jusqu'à son départ reste dû et peut encore être encaissé.
- **Réintégrer** annule le retrait, en cas d'erreur ou si l'élève revient.
- Une inscription faite **par erreur et sans aucun paiement** peut être supprimée définitivement. La fiche de l'élève est supprimée aussi s'il n'a pas d'autre inscription.

### Fiche élève (parents)

- Pour chaque parent : nom, profession et téléphone.
- Il faut renseigner au moins un parent et au moins un numéro de téléphone.

### Paiements et reçus

- Les montants dus sont **calculés par le serveur** à partir des tarifs de la classe. À la caisse, on saisit seulement le montant versé (laisser vide = tout le reste).
- **Annuler un reçu** (erreur de saisie) : ouvrir le reçu, puis **Annuler ce reçu** et donner un motif obligatoire. Le reçu reste dans le journal, marqué ANNULÉ avec la date, l'auteur et le motif. Il ne compte plus, et les éléments payés redeviennent à payer. Pour **corriger**, on annule puis on encaisse à nouveau le bon montant.
- Les numéros de reçu sont **garantis uniques**, même si deux postes encaissent à la même seconde : un compteur par jour est verrouillé pendant l'encaissement.
- Chaque reçu indique **qui a encaissé**. Les dates et les heures sont celles de Madagascar (`APP_TIMEZONE`, UTC+3 par défaut).
- Sur le reçu, chaque versement partiel indique ce qui a déjà été versé et le reste à payer.
- Les reçus suivent le format `REC-AAAAMMJJ-0001`. Un même reçu peut regrouper plusieurs frais et plusieurs mois.
- Modes de paiement : espèces, mobile money, chèque, virement. Une **référence** est obligatoire pour tout mode autre que les espèces.

---

## 7. Données et sauvegarde

> ⚠️ **La base `school_manager` contient les vraies données de l'école.**
>
> - Ne **jamais** lancer `php artisan migrate:fresh`, `migrate:refresh` ou `db:seed` : ces commandes effacent ou mélangent les données. Seul `ClasseSeeder` peut être relancé sans risque.
> - Pour une mise à jour du code, `php artisan migrate` suffit : il n'ajoute que les nouvelles tables ou colonnes.
> - **Sauvegarde automatique chaque soir** à 23 h 30 (`php artisan ecole:sauvegarde`), dans `backend/storage/app/sauvegardes/`, conservée 30 jours. Elle nécessite le planificateur : un cron sur le serveur (voir [DEPLOIEMENT.md](DEPLOIEMENT.md)). Sauvegarde immédiate et téléchargement : **Paramètres → Sauvegardes**. Téléchargez-en une chaque semaine et gardez-la hors du serveur.
> - **Restaurer une sauvegarde** : phpMyAdmin → `school_manager` → onglet **Importer**, puis choisir le fichier `.sql`.

L'ancien fichier `backend/database/database.sqlite` n'est plus utilisé. C'est une ancienne copie, d'avant le passage à MySQL.

## Mise en ligne (VPS)

Voir **[DEPLOIEMENT.md](DEPLOIEMENT.md)** : installation sur Ubuntu 24.04 avec Nginx, PHP-FPM, MySQL et HTTPS, cron de sauvegarde, mise à jour en une commande (`deploy/mise-a-jour.sh`).

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
| GET, POST, PUT, DELETE | `/api/classes` | Classes et écolage mensuel |
| GET, POST, PUT, DELETE | `/api/fournitures?annee_scolaire_id=&classe_id=` | Fournitures scolaires d'une classe pour une année |
| POST | `/api/fournitures/copier`, `/api/fournitures/ordre` | Copier une liste (autre classe / autre année), changer l'ordre |
| GET | `/api/fournitures/pdf?annee_scolaire_id=&classe_id=` | Liste imprimable (A4) |
| GET, POST, PUT, DELETE | `/api/frais` | Frais de l'année (montant, classes, nouveaux / tous, par élève / par famille, facultatif) |
| GET | `/api/etudiants?q=` | Recherche d'élèves |
| GET, PUT | `/api/etudiants/{id}` | Fiche avec historique, modification |
| GET | `/api/inscriptions?annee_scolaire_id=&classe_id=&mois=AAAA-MM&statut=` | Inscrits avec le statut de l'écolage du mois (`paye`, `a_payer`, `en_retard`, `non_paye`) |
| POST | `/api/inscriptions` | Inscription d'un nouvel élève |
| POST | `/api/reinscriptions` | Réinscription d'un ancien élève |
| GET, DELETE | `/api/inscriptions/{id}` | Inscription avec sa situation financière, suppression (seulement si aucun paiement) |
| POST, DELETE | `/api/inscriptions/{id}/sortie` | Retirer l'élève de l'école (`motif_sortie`, `date_sortie`, `remarque_sortie`), ou le réintégrer |
| GET, POST | `/api/paiements` | Journal des reçus, encaissement |
| GET | `/api/recus/{numero}` | Détail d'un reçu |
| GET | `/api/recus/{numero}/pdf` | Reçu au format PDF (A5) |
| POST | `/api/recus/{numero}/annuler` | Annuler un reçu (`motif` obligatoire) |
| GET, POST | `/api/sauvegardes` | Liste des sauvegardes, sauvegarde immédiate |
| GET | `/api/sauvegardes/{fichier}` | Télécharger une sauvegarde |

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
