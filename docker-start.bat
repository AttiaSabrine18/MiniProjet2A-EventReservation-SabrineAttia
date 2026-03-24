@echo off
echo ========================================
echo Demarrage de Docker pour EventReservation
echo ========================================

REM Créer le dossier pour les clés JWT
if not exist config\jwt mkdir config\jwt

REM Démarrer les conteneurs
echo Demarrage des conteneurs...
docker-compose up -d

REM Attendre que la base de données soit prête
echo Attente de la base de donnees...
timeout /t 10 /nobreak > nul

REM Exécuter les migrations
echo Execution des migrations...
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

REM Vider le cache
echo Vidage du cache...
docker-compose exec php php bin/console cache:clear --env=prod

echo.
echo ========================================
echo Application demarree avec succes !
echo ========================================
echo Site : http://localhost
echo phpMyAdmin : http://localhost:8080
echo MailHog : http://localhost:8025
echo ========================================
pause