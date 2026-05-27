<img width="1366" height="768" alt="Capture d’écran du 2026-05-27 05-34-22" src="https://github.com/user-attachments/assets/4e2de46b-218c-477c-9890-6a405c53a061" />

<img width="1366" height="768" alt="Capture d’écran du 2026-05-27 05-47-27" src="https://github.com/user-attachments/assets/3b32f29e-d763-41dd-8c49-8bc49e2cb52c" />

# STRATOSPHERE — Guide d'installation & d'utilisation

Système de contrôle à distance d'appareils Android via un tableau de bord web.

---

## Sommaire

1. [Architecture](#architecture)
2. [Prérequis](#prérequis)
3. [Installation rapide](#installation-rapide)
4. [Installation manuelle](#installation-manuelle)
5. [Application Android](#application-android)
6. [Utilisation du tableau de bord](#utilisation-du-tableau-de-bord)
7. [Commandes disponibles](#commandes-disponibles)
8. [Sécurité](#sécurité)
9. [Dépannage](#dépannage)

---

## Architecture

```
┌─────────────────────┐          ┌──────────────────────┐
│   Tableau de bord   │  HTTPS   │    Serveur PHP/MySQL  │
│   (navigateur web)  │◄────────►│    Apache + MariaDB   │
└─────────────────────┘          └──────────┬───────────┘
                                             │ polling HTTP
                                   ┌─────────▼──────────┐
                                   │  Appareils Android  │
                                   │  (app Stratosphere) │
                                   └────────────────────┘
```

- **Backend** : PHP 8.0+ / MariaDB / Apache
- **Frontend** : HTML/CSS/JS + Leaflet.js (carte)
- **Application** : Android 5.0+ (Java)

---

## Prérequis

### Serveur
| Logiciel | Version minimale |
|----------|-----------------|
| PHP | 8.0 |
| MariaDB / MySQL | 10.3 / 5.7 |
| Apache | 2.4 |
| Extensions PHP | `pdo_mysql`, `mbstring`, `json` |

### Poste de développement (app Android)
| Outil | Version |
|-------|---------|
| Android Studio | Hedgehog (2023.1) ou plus récent |
| JDK | 11+ |
| Android SDK | API 32 (Android 12) |
| Gradle | 7.3+ |

### Appareils cibles
- Android 5.0 (API 21) minimum
- Android 12 recommandé

---

## Installation rapide

Trois méthodes sont disponibles, de la plus simple à la plus manuelle.

---

### Option A — Bootstrap complet ⭐ (recommandé, zéro prérequis)

**Un seul script installe tout** : Apache, PHP, MariaDB, déploie les fichiers et ouvre automatiquement le navigateur sur l'assistant d'installation.

```bash
# Cloner le dépôt ou copier le dossier 2026/ sur votre serveur
git clone https://github.com/equationstratos/stratosphere.git
cd stratosphere/2026/

# Lancer le bootstrap (nécessite sudo)
sudo bash bootstrap.sh
```

Le script vous demande **uniquement** :
1. Un **mot de passe MySQL** pour le compte applicatif
2. Un **mot de passe admin** pour le tableau de bord

Il génère automatiquement un token d'installation sécurisé, installe tous les paquets, configure Apache et MariaDB, puis ouvre votre navigateur sur :

```
http://localhost/stratosphere/setup.php?token=<token_auto_généré>
```

> **Après l'installation**, supprimez les scripts d'install :
> ```bash
> sudo rm /var/www/html/stratosphere/bootstrap.sh
> sudo rm /var/www/html/stratosphere/setup.php
> ```

---

### Option B — Assistant navigateur seul (setup.php)

Si Apache, PHP et MariaDB sont déjà installés, utilisez directement le wizard web.

```bash
# 1. Déployer les fichiers
sudo cp -r 2026/* /var/www/html/stratosphere/
sudo chown -R www-data:www-data /var/www/html/stratosphere/

# 2. Injecter un token de sécurité dans Apache (vous le choisissez librement)
echo "export INSTALL_TOKEN=monTokenSecret" | sudo tee -a /etc/apache2/envvars
sudo systemctl restart apache2
```

Ouvrir dans le navigateur :
```
http://votre-serveur/stratosphere/setup.php?token=monTokenSecret
```

Le wizard en 4 étapes configure la base de données, crée le fichier `.env` et s'auto-supprime à la fin.

---

### Option C — Script Bash interactif (terminal)

```bash
cd /var/www/html/stratosphere/
bash install.sh
```

Le script vous demandera :

| Paramètre | Défaut | Description |
|-----------|--------|-------------|
| Hôte MySQL | `localhost` | Adresse du serveur MySQL |
| Port MySQL | `3306` | Port MySQL |
| Utilisateur MySQL | `stratos_app` | Compte MySQL dédié |
| Mot de passe MySQL | *(généré)* | Saisi masqué, généré si vide |
| Nom de la base | `stratosphere_<aléatoire>` | Nom unique généré automatiquement |
| URL du serveur | `http://localhost/stratosphere` | Laisser en local par défaut |
| Mot de passe admin | *(saisi)* | Compte `admin` du tableau de bord |

À la fin, `install.sh` crée le fichier `.env` et importe le schéma SQL automatiquement.

---

### Option D — Installeur PHP (CLI ou navigateur)

**En ligne de commande :**

```bash
cd /var/www/html/stratosphere/
php php/install-interactive.php
```

**Via navigateur** avec token Apache (voir Option B pour configurer le token) :
```
http://votre-serveur/stratosphere/php/install-interactive.php?token=monTokenSecret
```

> **Après l'installation :** Supprimer les scripts d'installation (voir section [Sécurité](#sécurité)).

---

## Installation manuelle

Si vous préférez configurer manuellement sans utiliser les installeurs :

### 1. Dépendances système

```bash
sudo apt install -y apache2 php8.1 php8.1-mysql php8.1-mbstring \
                    php8.1-xml libapache2-mod-php8.1 \
                    mariadb-server mariadb-client
sudo systemctl enable --now apache2 mariadb
sudo a2enmod rewrite headers && sudo systemctl restart apache2
```

### 2. Déployer les fichiers

```bash
sudo cp -r 2026/* /var/www/html/stratosphere/
sudo chown -R www-data:www-data /var/www/html/stratosphere/
sudo chmod -R 755 /var/www/html/stratosphere/
```

> Vérifiez que votre VirtualHost a bien `AllowOverride All` pour que les `.htaccess` fonctionnent.

### 3. Créer le fichier `.env`

```bash
cd /var/www/html/stratosphere/
cp .env.example .env
nano .env
```

Variables disponibles :

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=stratosphere_monprojet
DB_USER=stratos_app
DB_PASS=mot_de_passe_securise

SESSION_LIFETIME=3600
LOGIN_MAX_ATTEMPTS=5
LOGIN_LOCKOUT_SECONDS=900

# 'development' pour afficher les erreurs PHP
APP_ENV=production
```

> **Important :** Ne committez jamais `.env`. Il est dans `.gitignore`.

### 4. Créer l'utilisateur MySQL et importer le schéma

```bash
sudo mysql_secure_installation

sudo mysql -u root -p <<'SQL'
CREATE DATABASE IF NOT EXISTS stratosphere_monprojet
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stratos_app'@'localhost' IDENTIFIED BY 'mot_de_passe_securise';
GRANT ALL PRIVILEGES ON stratosphere_monprojet.* TO 'stratos_app'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u stratos_app -p stratosphere_monprojet < /var/www/html/stratosphere/sql/schema.sql
```

Le schéma crée :
- `Accounts` — comptes administrateurs (défaut : **admin / changeme**)
- `Devices` — appareils enregistrés
- `CommandLog` — historique des commandes (audit)
- `LoginAttempts` — protection anti-bruteforce

### Schéma des tables principales

```
Devices
├── Id             INT AUTO_INCREMENT
├── BrandName      VARCHAR(50)
├── ModelName      VARCHAR(50)
├── ModelOs        VARCHAR(30)
├── BatteryLevel   TINYINT (0–100)
├── ConnectType    VARCHAR(30)   (WiFi, LTE, …)
├── Latitude       DECIMAL(10,7)
├── Longitude      DECIMAL(10,7)
├── Command        VARCHAR(50)   ← commande en attente
├── LastSeen       TIMESTAMP     ← mis à jour à chaque poll
└── CreatedAt      TIMESTAMP

CommandLog
├── Id        INT AUTO_INCREMENT
├── DeviceId  FK → Devices.Id
├── Command   VARCHAR(50)
├── UserId    FK → Accounts.Id
└── SentAt    TIMESTAMP
```

---

## Application Android

### 1. Ouvrir le projet

```
Android Studio → File → Open → StratosphereAPP/
```

Laisser Gradle synchroniser les dépendances.

### 2. Configurer l'URL du serveur

Ouvrir le fichier :

```
app/src/main/java/com/example/stratosphere/MainActivity.java
```

Remplacer l'URL hardcodée par votre domaine/IP aux **deux endroits** suivants :

```java
// Ligne ~145 — polling des commandes
String url = "https://VOTRE_DOMAINE/php/read.php";

// Ligne ~436 — enregistrement de l'appareil
String url = "https://VOTRE_DOMAINE/php/create.php";
```

De même dans `LocalisationService.java` si vous utilisez le tracking GPS.

### 3. Compiler et installer

```bash
# Via Gradle en ligne de commande
cd StratosphereAPP/
./gradlew assembleDebug

# L'APK est généré ici :
# app/build/outputs/apk/debug/app-debug.apk
```

Ou directement depuis Android Studio : **Run → Run 'app'**.

### Permissions requises par l'app

L'app demandera à l'utilisateur d'accorder les permissions suivantes au premier lancement :

| Permission | Utilisée pour |
|-----------|--------------|
| `CAMERA` | Photos et stream vidéo |
| `RECORD_AUDIO` | Stream microphone |
| `ACCESS_FINE_LOCATION` | Géolocalisation GPS |
| `VIBRATE` | Commande vibration |
| `READ/WRITE_EXTERNAL_STORAGE` | Sauvegarde vidéos/photos |
| `INTERNET` | Communication avec le serveur |

---

## Utilisation du tableau de bord

### Accès

```
https://votre-serveur/stratosphere/
```

### Connexion

- Saisir le **nom d'utilisateur** et le **mot de passe**
- Après 5 tentatives échouées depuis la même IP, le compte est verrouillé **15 minutes**

### Interface principale

```
┌─────────────────┬───────────────────────┬──────────────────┐
│ Appareils       │ Commandes             │ Informations     │
│ connectés       │                       │                  │
│ ─────────────── │ [FLASH] [VIBRATE]     │ Sélectionnés: 2  │
│ ☑ ID 1 Samsung  │ [STROBO] [RING]       │ Dernière cmd:    │
│ ☑ ID 2 Xiaomi   │ [📷 BACK] [🤳 FRONT] │ FLASH → 1, 2    │
│ ☐ ID 3 Huawei   │ [🎙 MIC]  [🔴 LIVE] │                  │
│                 │ [📸 PIC]  [🎬 REC]   │ Historique…      │
└─────────────────┴───────────────────────┴──────────────────┘
                     Carte Leaflet / OpenStreetMap
```

### Workflow typique

1. **Sélectionner** un ou plusieurs appareils (cases à cocher) ou cliquer **"Select all"**
2. **Cliquer** sur une commande → elle s'envoie instantanément à tous les appareils sélectionnés
3. Un **toast** vert/rouge confirme le succès ou l'échec
4. Les commandes à bascule (Flash, Vibrate, Stream…) s'activent et se désactivent en recliquant
5. La liste des appareils se **rafraîchit automatiquement** toutes les 30 secondes

### Indicateurs de statut des appareils

| Indicateur | Signification |
|-----------|--------------|
| 🟢 Point vert | Appareil actif (vu il y a moins de 5 min) |
| 🔴 Point rouge | Appareil inactif ou hors ligne |

### Stream vidéo

1. Sélectionner un appareil
2. Cliquer **"📷 BACK CAM"** ou **"🤳 FRONT CAM"**
3. Une zone de saisie apparaît → entrer l'URL du flux (`http://IP_APPAREIL:PORT`)
4. Cliquer **"Connect"** — l'URL est mémorisée pour la session

---

## Commandes disponibles

### Commandes à bascule (ON/OFF)

| Bouton | Commande ON | Commande OFF |
|--------|------------|-------------|
| FLASH | `FLASH` | `NOFLASH` |
| VIBRATE | `VIBRATE` | `STOPVIBRATE` |
| STROBO | `STROBO` | `NOSTROBO` |
| RING | `RING` | `STOPRING` |
| BACK CAM (stream) | `STREAMBACK` | `STOPSTREAM` |
| FRONT CAM (stream) | `STREAMFRONT` | `STOPSTREAM` |
| MIC (stream audio) | `MICRO` | `STOPMICRO` |
| LIVE | `LIVE` | `STOPLIVE` |

### Commandes one-shot

| Bouton | Commande |
|--------|---------|
| PIC FRONT | `PICTUREFRONT` |
| PIC BACK | `PICTUREBACK` |
| REC FRONT | `RECORDVIDEOFRONT` |
| REC BACK | `RECORDVIDEOBACK` |
| LOCALISE | `LOCALISATION` |
| TEXT2SPEECH | `TEXT2SPEACH` |
| MORSE | `MORSE` |
| PLAY AUDIO | `PLAYAUDIO` |

---

## Sécurité

### Changer le mot de passe admin

```bash
php -r "echo password_hash('NOUVEAU_MOT_DE_PASSE', PASSWORD_BCRYPT) . PHP_EOL;"
```

```sql
UPDATE Accounts
SET Password = '$2y$12$...'    -- coller le hash généré ci-dessus
WHERE Username = 'admin';
```

### Activer HTTPS (fortement recommandé)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d votre-domaine.com
```

Puis décommenter dans `.htaccess` :

```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Supprimer les scripts d'installation après déploiement

```bash
# Option A (bootstrap)
sudo rm /var/www/html/stratosphere/bootstrap.sh
sudo rm /var/www/html/stratosphere/setup.php

# Options C/D (install.sh / PHP)
rm /var/www/html/stratosphere/install.sh
rm /var/www/html/stratosphere/php/install.php
rm /var/www/html/stratosphere/php/install-interactive.php
```

Aussi retirer le token de la config Apache :
```bash
sudo nano /etc/apache2/envvars   # supprimer les lignes INSTALL_TOKEN et INSTALL_ADMIN_PASS
sudo systemctl restart apache2
```

### Consulter l'audit des commandes

```sql
SELECT cl.SentAt, a.Username, d.BrandName, d.ModelName, cl.Command
FROM CommandLog cl
JOIN Accounts a  ON a.Id  = cl.UserId
JOIN Devices  d  ON d.Id  = cl.DeviceId
ORDER BY cl.SentAt DESC
LIMIT 100;
```

---

## Dépannage

### Page blanche / erreur 500

1. Vérifier les logs Apache :
   ```bash
   sudo tail -f /var/log/apache2/error.log
   ```
2. Passer `APP_ENV=development` dans `.env` temporairement pour afficher les erreurs PHP
3. Vérifier que les extensions PHP sont bien activées :
   ```bash
   php -m | grep -E "pdo_mysql|mbstring|json"
   ```

### "Service unavailable" à la connexion

La base de données est inaccessible. Vérifier :
```bash
sudo systemctl status mariadb
# Si arrêtée :
sudo systemctl start mariadb
```
Vérifier aussi les identifiants dans `.env`.

### L'appareil Android n'apparaît pas dans la liste

- L'app doit avoir accordé toutes les permissions au démarrage
- Vérifier que l'URL du serveur dans `MainActivity.java` est correcte et joignable depuis l'appareil
- Vérifier que le serveur répond bien à `https://votre-domaine/php/create.php` (doit retourner un entier)

### Erreur CSRF sur les commandes

Vider le cache du navigateur et se reconnecter. Le token CSRF est lié à la session.

### Login bloqué (trop de tentatives)

```sql
DELETE FROM LoginAttempts WHERE ip = '1.2.3.4';
```

---

## Structure des fichiers

```
2026/
├── bootstrap.sh              ← ⭐ Bootstrap complet (installe Apache/PHP/MariaDB + ouvre navigateur)
├── setup.php                 ← ⭐ Wizard navigateur (installation sans terminal)
├── install.sh                ← Installeur Bash interactif
├── index.html                ← Page de connexion
├── .env.example              ← Template de configuration (copier en .env)
├── .env                      ← Config locale (créée par install.sh, jamais commitée)
├── .htaccess                 ← En-têtes de sécurité HTTP
├── .gitignore
│
├── php/
│   ├── install-interactive.php ← ⭐ Installeur PHP (CLI ou navigateur web)
│   ├── install.php             ← Installeur non-interactif (lit le .env existant)
│   ├── config.php              ← Configuration centrale (lecture .env)
│   ├── db.php                  ← Connexion PDO singleton
│   ├── auth_middleware.php     ← Auth, CSRF, helpers partagés
│   ├── authenticate.php        ← Gestion du login
│   ├── logout.php              ← Déconnexion
│   ├── index.php               ← Tableau de bord principal
│   ├── Localisation.php        ← Page carte
│   ├── create.php              ← Enregistrement d'un appareil (API Android)
│   ├── read.php                ← Polling commandes (API Android)
│   ├── update_command.php      ← Envoi de commande (dashboard → BDD)
│   ├── updatelocation.php      ← Mise à jour GPS (API Android)
│   ├── dataMarkers.php         ← Données carte (JSON)
│   ├── devices_list.php        ← Liste appareils (JSON, auto-refresh)
│   ├── developersTable.php     ← Requête de listing des appareils
│   ├── dbconnect.php           ← Compatibilité mysqli (legacy)
│   └── .htaccess               ← Bloquer accès direct aux fichiers internes
│
├── sql/
│   └── schema.sql            ← Schéma complet (importé automatiquement par les installeurs)
│
├── css/                      ← Feuilles de style
├── js/                       ← Scripts JavaScript
├── images/                   ← Icônes et assets
├── themes/                   ← Thèmes de couleurs alternatifs
│
└── StratosphereAPP/          ← Projet Android Studio
    └── app/src/main/java/com/example/stratosphere/
        ├── MainActivity.java       ← Point d'entrée, boucle de polling
        ├── FlashService.java       ← LED flash
        ├── VibrateService.java     ← Vibration
        ├── LocalisationService.java← GPS
        ├── PictureFrontService.java
        ├── PictureBackService.java
        ├── StreamFrontService.java
        ├── StreamBackService.java
        └── …                      ← Autres services
```
# StratoSphere
