# Mise en ligne sur un serveur VPS

Ce guide installe l'application sur un VPS **Ubuntu 24.04**, sur un seul domaine en HTTPS. Exemple : `https://ecole.exemple.mg`.

- **Nginx** sert l'interface React (fichiers du dossier `frontend/dist`).
- Les adresses `/api/...` sont envoyées à Laravel via **PHP-FPM**.
- **MySQL** reste interne au serveur : il n'est jamais exposé sur Internet.
- Un **cron** lance la sauvegarde automatique chaque soir.

Les fichiers prêts à l'emploi sont dans [`deploy/`](deploy/) :

| Fichier | Rôle |
|---|---|
| `nginx-gestion-ecole.conf` | Configuration Nginx |
| `env.production.exemple` | Modèle du fichier `backend/.env` du serveur |
| `mise-a-jour.sh` | Mise à jour en une commande, après chaque `git push` |

> Dans ce guide, remplacez `ecole.exemple.mg` par votre domaine. `ecole` est l'utilisateur Linux et MySQL de l'application.

---

## 0. Avant de commencer

- **Le VPS** : Ubuntu 24.04, au moins 1 Go de RAM (2 Go conseillés pour la construction de l'interface) et un accès SSH.
- **Le nom de domaine** : chez votre registraire, créez un enregistrement **A** de `ecole.exemple.mg` vers l'adresse IP du VPS.
- **Le code** est sur GitHub : `https://github.com/Mahery0107/School_manager`.
  - Si le dépôt est **privé**, créez un *token* GitHub en lecture seule (Settings → Developer settings → Fine-grained tokens) pour le `git clone`.

## 1. Préparer le serveur

Connectez-vous en SSH avec un compte qui peut utiliser `sudo`.

```bash
sudo apt update && sudo apt upgrade -y

# Serveur web + pare-feu : SSH et web uniquement (MySQL reste fermé à l'extérieur)
sudo apt install -y nginx
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable

# PHP 8.3 et extensions utilisées par Laravel / les reçus PDF
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-gd php8.3-zip php8.3-curl php8.3-bcmath php8.3-intl unzip git acl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (pour construire l'interface)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

## 2. Créer la base de données

Générez un mot de passe long et aléatoire, par exemple avec `openssl rand -base64 24`, puis :

```bash
sudo mysql
```
```sql
CREATE DATABASE school_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ecole'@'localhost' IDENTIFIED BY 'LE_MOT_DE_PASSE_GENERE';
GRANT ALL PRIVILEGES ON school_manager.* TO 'ecole'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Installer le code

```bash
sudo mkdir -p /var/www/gestion-ecole
sudo chown $USER:www-data /var/www/gestion-ecole
git clone https://github.com/Mahery0107/School_manager.git /var/www/gestion-ecole
cd /var/www/gestion-ecole

# Laravel (PHP-FPM, utilisateur www-data) doit pouvoir écrire dans storage/ et bootstrap/cache/
sudo setfacl -R  -m u:www-data:rwX -m u:$USER:rwX backend/storage backend/bootstrap/cache
sudo setfacl -dR -m u:www-data:rwX -m u:$USER:rwX backend/storage backend/bootstrap/cache
```

## 4. Configurer Laravel

```bash
cd /var/www/gestion-ecole/backend
cp ../deploy/env.production.exemple .env
nano .env        # APP_URL=https://ecole.exemple.mg et DB_PASSWORD=le mot de passe de l'étape 2
chmod 640 .env

composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate
```

## 5. Mettre les données dans la base

**Cas A : reprendre les données actuelles (élèves, paiements…)**

1. **Sur l'ordinateur actuel**, faites une sauvegarde. Dans l'application : **Paramètres → Sauvegardes → Sauvegarder maintenant**, puis **Télécharger**. Vous obtenez un fichier `auto-school_manager-AAAAMMJJ-HHMMSS.sql.gz`.
2. **Envoyez ce fichier sur le serveur.** Depuis l'ordinateur, dans un terminal :
   ```bash
   scp auto-school_manager-*.sql.gz utilisateur@IP_DU_VPS:/tmp/
   ```
3. **Sur le serveur**, importez-le :
   ```bash
   gunzip < /tmp/auto-school_manager-*.sql.gz | mysql -u ecole -p school_manager
   cd /var/www/gestion-ecole/backend
   php artisan migrate --force      # n'ajoute que ce qui manque
   rm /tmp/auto-school_manager-*.sql.gz
   ```
   Le compte administrateur et son mot de passe actuel sont repris avec les données.

**Cas B : partir d'une base vide**

```bash
cd /var/www/gestion-ecole/backend
php artisan migrate --force                          # crée les tables + le compte admin / admin123
php artisan db:seed --class=ClasseSeeder --force     # les 12 classes et leurs tarifs
```

> ⚠️ Ne lancez **jamais** `migrate:fresh`, `migrate:refresh` ni `db:seed` sans `--class=ClasseSeeder` sur le serveur : ces commandes effacent les données.

Ensuite, activez les caches de production :

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 6. Construire l'interface

```bash
cd /var/www/gestion-ecole/frontend
npm ci
npm run build        # crée frontend/dist, servi par Nginx
```

## 7. Nginx et HTTPS

```bash
sudo cp /var/www/gestion-ecole/deploy/nginx-gestion-ecole.conf /etc/nginx/sites-available/gestion-ecole
sudo nano /etc/nginx/sites-available/gestion-ecole      # remplacer ecole.exemple.mg par votre domaine
sudo ln -s /etc/nginx/sites-available/gestion-ecole /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

# Certificat HTTPS gratuit (Let's Encrypt), renouvelé automatiquement
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ecole.exemple.mg --redirect
```

Ouvrez `https://ecole.exemple.mg` : l'écran de connexion doit s'afficher.

## 8. Sauvegarde automatique chaque soir

Le planificateur de Laravel lance `ecole:sauvegarde` à 23 h 30, heure de Madagascar (`SAUVEGARDE_HEURE`). Il ne fonctionne que si ce cron l'appelle chaque minute :

```bash
sudo crontab -u www-data -e
```
Ajoutez la ligne :
```
* * * * * cd /var/www/gestion-ecole/backend && php artisan schedule:run >> /dev/null 2>&1
```

Pour vérifier :
- lancez `cd /var/www/gestion-ecole/backend && sudo -u www-data php artisan ecole:sauvegarde` : le message « Sauvegarde créée » doit s'afficher ;
- le lendemain, la sauvegarde de la nuit doit apparaître dans **Paramètres → Sauvegardes**.

> 💡 **Les sauvegardes sont sur le même disque que la base.** Si le VPS est perdu, elles le sont aussi. Téléchargez donc une sauvegarde **au moins chaque semaine** (Paramètres → Sauvegardes → Télécharger) et gardez-la ailleurs : Google Drive, clé USB… Activez aussi, si possible, les *snapshots* automatiques proposés par votre hébergeur.

## 9. Première connexion : sécurité

1. Connectez-vous, puis allez dans **Paramètres → Compte administrateur**.
2. **Changez l'identifiant et le mot de passe**, surtout si c'est encore `admin` / `admin123`. Ces identifiants sont écrits dans le dépôt GitHub, qui est public.

Vérifications :
- [ ] `APP_DEBUG=false` dans `backend/.env`.
- [ ] Le site ne répond qu'en `https://` (certbot `--redirect`).
- [ ] `https://ecole.exemple.mg/.env` renvoie une erreur 403 ou 404.
- [ ] MySQL n'est pas accessible de l'extérieur : `sudo ufw status` ne doit pas montrer le port 3306.
- [ ] Le cron de sauvegarde est installé (`sudo crontab -u www-data -l`).

---

## Mettre à jour l'application

Après chaque modification envoyée sur GitHub (`git push`), lancez sur le serveur :

```bash
cd /var/www/gestion-ecole
bash deploy/mise-a-jour.sh
```

Le script fait, dans l'ordre :
1. une sauvegarde de la base ;
2. `git pull` ;
3. les dépendances PHP ;
4. les migrations (qui n'ajoutent que les nouveautés) ;
5. les caches Laravel ;
6. la reconstruction de l'interface.

> Si vous modifiez `backend/.env` sur le serveur, lancez ensuite `php artisan config:cache` pour que la modification soit prise en compte.

## Restaurer une sauvegarde

```bash
# 1. Sauvegarder l'état actuel, par précaution
cd /var/www/gestion-ecole/backend && php artisan ecole:sauvegarde
# 2. Restaurer le fichier choisi (dans storage/app/sauvegardes/ ou envoyé par scp)
gunzip < storage/app/sauvegardes/auto-school_manager-AAAAMMJJ-HHMMSS.sql.gz | mysql -u ecole -p school_manager
```

## En cas de problème

| Symptôme | Où regarder |
|---|---|
| Page blanche ou erreur 500 | `backend/storage/logs/laravel-AAAA-MM-JJ.log` |
| Erreur 502 Bad Gateway | `sudo systemctl status php8.3-fpm` et `sudo tail /var/log/nginx/error.log` |
| « Serveur injoignable » à la connexion | Le bloc `location ^~ /api/` de la configuration Nginx, puis `sudo nginx -t` |
| Pas de sauvegarde automatique | `sudo crontab -u www-data -l`, puis `php artisan schedule:list` |
