#  Application de Réservation d'Événements

##  Description
Application web complète de gestion de réservations d'événements avec :
-  Authentification sécurisée (JWT + Passkeys / WebAuthn)
-  Confirmation par email via MailHog
-  Containerisation Docker
-  Base de données MySQL / MariaDB

---

##  Technologies utilisées

| Technologie | Version | Utilisation |
|-------------|---------|-------------|
| Symfony | 7.0 | Framework principal |
| PHP | 8.2 | Langage backend |
| MySQL / MariaDB | 10.11 | Base de données |
| Docker | Latest | Containerisation |
| JWT (LexikJWTAuthenticationBundle) | — | Tokens d'authentification |
| Passkeys / WebAuthn | — | Authentification biométrique |
| MailHog | Latest | Capture d'emails (développement) |
| Bootstrap 5 | Bootswatch Flatly | Interface utilisateur |

---
##  Installation et démarrage

### 1. Prérequis

| Logiciel | Version minimale |
|----------|------------------|
| Docker Desktop | 4.0+ |
| Git | 2.30+ |
| Navigateur | Chrome 108+, Firefox 113+, Safari 16+ (pour Passkeys) |

### 2. Cloner le projet

```bash
git clone https://github.com/AttiaSabrine18/MiniProjet2A-EventReservation-SabrineAttia
cd MiniProjet2A-EventReservation-SabrineAttia
```

### 3. Démarrer Docker

```bash
docker-compose up -d
```

### 4. Attendre que les conteneurs soient prêts

```bash
sleep 10
```

### 5. Initialiser la base de données

#### 5.1 Créer les tables

```bash
docker exec -it eventreservation_php php bin/console doctrine:schema:create
```

#### 5.2 Exécuter les migrations

```bash
docker exec -it eventreservation_php php bin/console doctrine:migrations:migrate --no-interaction
```

#### 5.3 Insérer les données de test

>  **Important** : Copier d'abord le fichier SQL dans le conteneur PHP, puis l'insérer dans la base de données.

**Étape A — Copier le fichier SQL dans le conteneur PHP :**

```bash
docker cp fill_database.sql eventreservation_php:/tmp/fill_database.sql
```

**Étape B — Insérer les données dans la base de données :**

```bash
docker exec -i eventreservation_db mysql -u appuser -papppass -D event_reservation_db < fill_database.sql
```

#### 5.4 Vérifier que les données sont bien insérées

```bash
docker exec -it eventreservation_php php bin/console doctrine:query:sql "SELECT email, is_verified FROM user"
```

Résultat attendu :

```
+------------------------------+-------------+
| email                        | is_verified |
+------------------------------+-------------+
| admin@admin.com              | 1           |
| user@test.com                | 1           |
| attiasabrine450@gmail.com    | 1           |
+------------------------------+-------------+
```

---

##  Identifiants de test

| Type | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | admin@admin.com | admin123 |
| Utilisateur standard | user@test.com | user123 |
| Utilisateur Passkey | attiasabrine450@gmail.com | passkey123 |

---

##  Accès aux services

| Service | URL | Description |
|---------|-----|-------------|
| Application | http://localhost | Site principal |
| phpMyAdmin | http://localhost:8080 | Gestion de la base de données |
| MailHog (Web UI) | http://localhost:8025 | Visualisation des emails |
| MailHog (SMTP) | mailer:1025 | Serveur SMTP interne |

**Identifiants phpMyAdmin :**
- Serveur : `db`
- Utilisateur : `appuser`
- Mot de passe : `apppass`

---

##  Fonctionnalités

###  Utilisateur
- Inscription avec email / mot de passe
- Connexion avec email / mot de passe
- Inscription avec Passkey (biométrie)
- Connexion avec Passkey (biométrie)
- Consultation de la liste des événements
- Consultation du détail d'un événement
- Réservation d'événements
- Confirmation par email après inscription

###  Administrateur
- Accès au tableau de bord admin
- CRUD complet sur les événements (Créer, Lire, Modifier, Supprimer)
- Consultation des réservations par événement

###  Sécurité
- Authentification JWT
- Refresh Token (30 jours)
- Passkeys / WebAuthn (résistance au phishing)
- Vérification email avec token expirable (24h)
- Mots de passe hachés (bcrypt)

---

##  Configuration MailHog

MailHog est un outil de test d'emails qui capture tous les emails envoyés par l'application **sans les envoyer réellement**.

###  Accès

| Interface | URL | Description |
|-----------|-----|-------------|
| Web UI | http://localhost:8025 | Visualiser les emails reçus |
| SMTP | mailer:1025 | Serveur SMTP interne |

###  Fonctionnement

1. L'application envoie via `MAILER_DSN=smtp://mailer:1025`
2. MailHog intercepte l'email sans le transmettre réellement
3. L'email est consultable sur http://localhost:8025

###  Vérification

```bash
# Vérifier que MailHog est en cours d'exécution
docker ps | findstr mailer

# Résultat attendu :
# eventreservation_mailer   mailhog/mailhog   Up
```

###  Service dans docker-compose.yml

```yaml
  mailer:
    image: mailhog/mailhog
    container_name: eventreservation_mailer
    ports:
      - "1025:1025"   # SMTP
      - "8025:8025"   # Interface web
    networks:
      - app_network
```

---

##  Tests des fonctionnalités

### Test 1 : Connexion administrateur

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@admin.com","password":"admin123"}'
```

 Résultat attendu : Réponse JSON avec token JWT

### Test 2 : Connexion utilisateur standard

```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@test.com","password":"user123"}'
```

### Test 3 : Inscription Passkey

1. Ouvrir http://localhost/register
2. Saisir un email
3. Cliquer sur **« S'inscrire avec Passkey »**
4. Valider avec empreinte digitale / FaceID / PIN

### Test 4 : Connexion Passkey

1. Ouvrir http://localhost/login
2. Cliquer sur **« Se connecter avec Passkey »**
3. Valider avec empreinte digitale / FaceID / PIN

### Test 5 : Vérification email

1. S'inscrire avec un nouvel email sur http://localhost/register
2. Ouvrir MailHog : http://localhost:8025
3. Cliquer sur le lien de confirmation
4. Se connecter normalement

---

##  Commandes Docker utiles

```bash
# Voir l'état des conteneurs
docker ps

# Voir les logs PHP
docker logs eventreservation_php -f

# Voir les logs Nginx
docker logs eventreservation_nginx -f

# Voir les logs MySQL
docker logs eventreservation_db -f

# Voir les logs MailHog
docker logs eventreservation_mailer -f

# Entrer dans le conteneur PHP
docker exec -it eventreservation_php bash

# Vider le cache Symfony
docker exec -it eventreservation_php php bin/console cache:clear

# Exécuter une requête SQL
docker exec -it eventreservation_php php bin/console doctrine:query:sql "SELECT * FROM user"

# Arrêter les conteneurs
docker-compose down

# Redémarrer les conteneurs
docker-compose up -d
```

---

##  Dépannage

###  Erreur : « could not find driver »

```bash
docker exec -it eventreservation_php docker-php-ext-install pdo_mysql
docker exec -it eventreservation_php sh -c 'echo "extension=pdo_mysql.so" > /usr/local/etc/php/conf.d/docker-php-ext-pdo_mysql.ini'
docker restart eventreservation_php
```

###  Erreur : « Table 'event_reservation_db.user' doesn't exist »

```bash
docker exec -it eventreservation_php php bin/console doctrine:schema:create
docker exec -it eventreservation_php php bin/console doctrine:migrations:migrate --no-interaction
docker cp fill_database.sql eventreservation_php:/tmp/fill_database.sql
docker exec -i eventreservation_db mysql -u appuser -papppass -D event_reservation_db < fill_database.sql
```

###  Erreur : « Environment variable not found: JWT_SECRET_KEY »

```bash
docker exec -it eventreservation_php mkdir -p config/jwt
docker exec -it eventreservation_php openssl genpkey \
  -out config/jwt/private.pem -aes256 -algorithm rsa \
  -pkeyopt rsa_keygen_bits:4096 -pass pass:ChangeMe123!
docker exec -it eventreservation_php openssl pkey \
  -in config/jwt/private.pem -out config/jwt/public.pem \
  -pubout -passin pass:ChangeMe123!
```

###  Erreur : « Invalid credentials » après vérification email

```bash
# Activer manuellement l'utilisateur
docker exec -it eventreservation_php php bin/console doctrine:query:sql \
  "UPDATE user SET is_verified = 1 WHERE email = 'votre_email'"
```

###  Les emails n'apparaissent pas dans MailHog

```bash
# Vérifier que le conteneur MailHog tourne
docker ps | findstr mailer

# Redémarrer MailHog si nécessaire
docker restart eventreservation_mailer

# Vérifier la configuration MAILER_DSN
docker exec -it eventreservation_php cat .env | findstr MAILER
# Résultat attendu : MAILER_DSN=smtp://mailer:1025

# Voir les logs de MailHog
docker logs eventreservation_mailer
```

###  La base de données ne se charge pas (fill_database.sql)

```bash
# Étape 1 : Copier le fichier SQL dans le conteneur PHP
docker cp fill_database.sql eventreservation_php:/tmp/fill_database.sql

# Étape 2 : Insérer les données via le conteneur MySQL
docker exec -i eventreservation_db mysql -u appuser -papppass -D event_reservation_db < fill_database.sql

# Étape 3 : Vérifier les données
docker exec -it eventreservation_php php bin/console doctrine:query:sql "SELECT email, is_verified FROM user"
```

