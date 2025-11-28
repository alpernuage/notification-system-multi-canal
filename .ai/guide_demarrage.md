# Guide de Démarrage - Projet Symfony Notifications Multi-Canal

## 📚 Introduction

Ce guide vous explique **étape par étape** comment démarrer et vérifier le bon fonctionnement de votre projet Symfony de notifications multi-canal.

## 🎯 Pourquoi ce projet est idéal pour apprendre?

Vous allez découvrir **10+ composants avancés de Symfony** dans un contexte réel:
- **Workflow**: Machine à états pour gérer le cycle de vie des notifications
- **Messenger**: Files d'attente asynchrones avec retry automatique
- **Service Locator**: Pattern pour sélectionner dynamiquement le bon sender
- **Rate Limiter**: Protection contre le spam
- **Lock**: Éviter les traitements en double
- **Cache**: Optimisation des templates
- **Scheduler**: Tâches planifiées (cron)
- **EventDispatcher**: Logging et monitoring
- **HttpClient**: Appels API externes
- **Attributes PHP 8**: Configuration moderne

## 🚀 Étapes de Démarrage

### Étape 0: Vérifier l'environnement actuel

Votre projet a déjà:
- ✅ Symfony 7.4 (webapp)
- ✅ Doctrine ORM + Migrations
- ✅ Symfony Messenger
- ✅ Symfony Mailer
- ✅ Symfony HttpClient
- ✅ Docker Compose (PostgreSQL)

### Étape 1: Installer les dépendances manquantes

```bash
cd /home/alp/PhpstormProjects/Personal/multi-canal-notification-system

# Installer les composants Symfony manquants
composer require symfony/workflow
composer require symfony/rate-limiter
composer require symfony/lock
composer require symfony/scheduler
composer require symfony/cache
composer require predis/predis

# Dépendances de développement
composer require --dev doctrine/doctrine-fixtures-bundle
```

### Étape 2: Configurer Docker (Redis + Mailpit)

Modifier `compose.yaml` pour ajouter Redis et Mailpit:

```bash
# Démarrer les nouveaux services
docker-compose up -d

# Vérifier que tout fonctionne
docker-compose ps
```

Vous devriez voir:
- ✅ PostgreSQL 16
- ✅ Redis 7 (nouveau)
- ✅ Mailpit (nouveau - interface: http://localhost:8025)

### Étape 3: Configurer les variables d'environnement

Ajouter dans `.env`:
```env
# Redis
REDIS_URL=redis://redis:6379

# Mailer (Mailpit)
MAILER_DSN=smtp://mailpit:1025

# Slack Webhook (optionnel)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### Étape 4: Créer l'entité Notification

```bash
# Créer l'entité avec Maker
php bin/console make:entity Notification

# Générer et exécuter la migration
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Charger les fixtures (données de test)
php bin/console doctrine:fixtures:load
```

## ✅ Comment Vérifier Chaque Étape?

### Phase 1: Workflow (Jour 1)

**Ce que vous testez**: La machine à états pour gérer les transitions de notifications

```bash
# 1. Créer une notification en mode draft
php bin/console notification:send \
  --channel=email \
  --recipient=test@example.com \
  --message="Test workflow" \
  --draft

# 2. Vérifier l'état dans la base
php bin/console notification:list
# Devrait afficher: ID=1, State=draft

# 3. Approuver la notification
php bin/console notification:approve 1

# 4. Vérifier le changement d'état
php bin/console notification:list
# Devrait afficher: ID=1, State=approved

# 5. Vérifier les logs du workflow
tail -f var/log/dev.log | grep workflow
```

**✅ Succès si**: La notification passe de `draft` → `approved` et les logs montrent les transitions

**❌ Erreur commune**: Guard qui bloque la transition → vérifier que le destinataire est valide

---

### Phase 2: Messenger + Strategy Pattern (Jour 2)

**Ce que vous testez**: L'envoi asynchrone avec sélection automatique du bon sender

**Note**: Le projet utilise **Doctrine** comme transport Messenger (les messages sont stockés dans la table `messenger_messages` de PostgreSQL).

```bash
# 1. Démarrer le worker Messenger (dans un terminal séparé)
php bin/console messenger:consume async -vv

# 2. Créer et approuver une notification email
php bin/console notification:send \
  --channel=email \
  --recipient=test@example.com \
  --message="Test async"

# 3. Observer le worker traiter le message
# Vous devriez voir:
# - Message reçu depuis la base de données: SendNotificationMessage
# - Transition: approved → sending
# - EmailSender sélectionné
# - Email envoyé
# - Transition: sending → sent

# 4. Vérifier l'email dans Mailpit
open http://localhost:8025

# 5. Tester avec Slack
php bin/console notification:send \
  --channel=slack \
  --recipient=https://hooks.slack.com/... \
  --message="Test Slack"

# Observer que SlackSender est sélectionné
```

**✅ Succès si**: 
- Le worker traite le message
- Le bon sender est sélectionné (Email vs Slack)
- L'email apparaît dans Mailpit
- La notification passe à l'état `sent`

**❌ Erreur commune**: 
- Worker ne démarre pas → vérifier la config Messenger
- Mauvais sender sélectionné → vérifier le Service Locator

---

### Phase 3: Rate Limiter + Lock (Jour 3)

**Ce que vous testez**: Les limites de taux et la protection contre les doublons

**Note**: Le **Rate Limiter** utilise Redis, mais le **Lock** utilise Flock (fichiers dans `var/lock/`).

**Important** : Le rate limiter est configuré **par destinataire** (100 emails/heure par adresse).

```bash
# 1. Tester le rate limiting avec la commande dédiée
php bin/console test:rate-limiter

# Cette commande envoie 105 tentatives au MÊME destinataire
# Résultat attendu : échec au 101ème

# ❌ ERREUR COURANTE : Cette boucle NE TESTE PAS le rate limiter
# for i in {1..101}; do
#   php bin/console notification:send --channel=email --recipient=test$i@example.com --message="Test"
# done
# Pourquoi ? Chaque test1@, test2@, test3@ est un destinataire différent
# donc chacun a son propre compteur de 100/heure

# 2. Vérifier dans Redis (pour le Rate Limiter uniquement)
docker-compose exec redis redis-cli
> KEYS rate_limiter:*
> GET rate_limiter:email_sender:rate-limit-test@example.com

# 4. Tester le lock (éviter doublons)
# Démarrer 2 workers
php bin/console messenger:consume async &
php bin/console messenger:consume async &

# Créer une notification
php bin/console notification:send --channel=email --recipient=lock@test.com

# Vérifier qu'elle n'est traitée qu'une fois
php bin/console notification:list
# Devrait montrer 1 seul envoi, pas 2

# Vérifier les fichiers de lock
ls -la var/lock/
```

**✅ Succès si**: 
- Le 101ème email est rejeté
- Les clés Redis existent
- Pas de doublons même avec 2 workers

**❌ Erreur commune**: 
- Rate limiter ne fonctionne pas → vérifier la config Redis
- Doublons → vérifier l'implémentation du Lock

---

### Phase 4: Cache + Events (Jour 4)

**Ce que vous testez**: Le cache des templates et le logging des events

```bash
# 1. Activer le mode debug pour voir les cache hits
export APP_DEBUG=1

# 2. Envoyer 2 notifications avec le même template
php bin/console notification:send \
  --channel=email \
  --template=welcome \
  --recipient=user1@example.com

php bin/console notification:send \
  --channel=email \
  --template=welcome \
  --recipient=user2@example.com

# 3. Vérifier les logs de cache
tail -f var/log/dev.log | grep cache
# Devrait montrer:
# - 1er envoi: cache MISS
# - 2ème envoi: cache HIT

# 4. Vérifier dans Redis
docker-compose exec redis redis-cli
> KEYS cache:*
> GET cache:template.welcome.*

# 5. Tester l'invalidation
php bin/console cache:invalidate-tags notifications

# 6. Vérifier les events
tail -f var/log/dev.log | grep NotificationSentEvent
# Devrait logger chaque envoi réussi
```

**✅ Succès si**: 
- 2ème envoi est plus rapide (cache hit)
- Les clés Redis existent
- Les events sont loggés

**❌ Erreur commune**: 
- Pas de cache hit → vérifier la clé de cache
- Events non loggés → vérifier le subscriber

---

### Phase 5: Scheduler (Jour 5)

**Ce que vous testez**: Les tâches planifiées (nettoyage automatique)

```bash
# 1. Créer des vieilles notifications (pour tester)
php bin/console notification:create-old --days=35

# 2. Lancer le scheduler
php bin/console messenger:consume scheduler_default -vv

# 3. Modifier le cron pour tester immédiatement
# Dans CleanupOldNotificationsMessage.php:
# ->cron('* * * * *') // Toutes les minutes au lieu de 3h

# 4. Observer le nettoyage
# Devrait afficher: "Deleted X old notifications"

# 5. Vérifier dans la base
php bin/console notification:list
# Les notifications > 30 jours devraient être supprimées
```

**✅ Succès si**: 
- Le scheduler s'exécute
- Les vieilles notifications sont supprimées
- Les logs montrent le nombre supprimé

**❌ Erreur commune**: 
- Scheduler ne s'exécute pas → vérifier la config
- Rien n'est supprimé → vérifier la requête de suppression

---

### Phase 6: Interface et API (Jour 5)

**Ce que vous testez**: L'API REST et le dashboard

```bash
# 1. Démarrer le serveur Symfony
symfony server:start
# ou
php -S localhost:8000 -t public/

# 2. Tester l'API REST
# Créer une notification
curl -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "email",
    "recipient": "api@example.com",
    "subject": "Test API",
    "message": "Message de test"
  }'

# Devrait retourner: {"id": 1, "state": "draft"}

# 3. Lister les notifications
curl http://localhost:8000/api/notifications

# 4. Approuver
curl -X POST http://localhost:8000/api/notifications/1/approve

# 5. Statistiques
curl http://localhost:8000/api/notifications/stats

# 6. Tester le dashboard
open http://localhost:8000/dashboard
```

**✅ Succès si**: 
- L'API retourne des JSON valides
- Le dashboard affiche les statistiques
- Les compteurs sont corrects

---

## 🧪 Tests Automatisés

```bash
# Lancer tous les tests
php bin/phpunit

# Tests unitaires seulement
php bin/phpunit tests/Unit

# Tests d'intégration seulement
php bin/phpunit tests/Integration

# Test spécifique
php bin/phpunit tests/Unit/Sender/EmailSenderTest.php

# Avec coverage
php bin/phpunit --coverage-html var/coverage
open var/coverage/index.html
```

## 🐛 Debugging

### Voir les logs en temps réel
```bash
tail -f var/log/dev.log
```

### Voir les messages dans la queue
```bash
php bin/console messenger:stats
```

### Voir les messages en échec
```bash
php bin/console messenger:failed:show
```

### Retry les messages en échec
```bash
php bin/console messenger:failed:retry
```

### Vider la queue
```bash
php bin/console messenger:stop-workers
docker-compose exec database psql -U app -d app -c "DELETE FROM messenger_messages;"
```

## 📊 Commandes Utiles (à créer)

```bash
# Statistiques des notifications
php bin/console notification:stats

# Nettoyer les vieilles notifications manuellement
php bin/console notification:cleanup

# Tester tous les composants
php bin/console test:all

# Tester un composant spécifique
php bin/console test:workflow
php bin/console test:messenger
php bin/console test:rate-limiter
php bin/console test:cache
php bin/console test:lock
```

## 🎓 Ordre d'Apprentissage Recommandé

1. **Jour 1**: Comprendre le Workflow
   - Créer des notifications manuellement
   - Tester toutes les transitions
   - Observer les guards et events

2. **Jour 2**: Maîtriser Messenger
   - Démarrer le worker
   - Observer le traitement asynchrone
   - Provoquer des erreurs pour voir le retry

3. **Jour 3**: Découvrir Rate Limiter et Lock
   - Atteindre les limites volontairement
   - Observer le comportement dans Redis
   - Tester avec plusieurs workers

4. **Jour 4**: Explorer Cache et Events
   - Mesurer les gains de performance
   - Invalider le cache
   - Créer des subscribers custom

5. **Jour 5**: Finaliser avec Scheduler et Interface
   - Planifier des tâches
   - Utiliser l'API
   - Créer un mini dashboard

## ❓ FAQ

**Q: Dois-je avoir une interface web?**
R: Non, vous pouvez tout faire via CLI et API REST. Une interface web est optionnelle.

**Q: Comment savoir si un composant fonctionne?**
R: Chaque phase a une section "Comment vérifier" avec des commandes précises.

**Q: Que faire si un test échoue?**
R: Consultez les logs (`var/log/dev.log`), vérifiez la configuration, et utilisez les commandes de debugging.

**Q: Puis-je utiliser de vrais services (Twilio, Slack)?**
R: Oui, mais pour l'apprentissage, des mocks suffisent. Vous pourrez intégrer les vrais services plus tard.

**Q: Combien de temps ça prend?**
R: 3-5 jours en suivant le planning. Vous pouvez aller plus vite si vous connaissez déjà certains composants.

**Q: Où sont les fichiers Docker?**
R: Le projet utilise déjà `compose.yaml`. Il faut juste ajouter Redis et Mailpit dedans.

## 📚 Ressources

- [Documentation Symfony Workflow](https://symfony.com/doc/current/workflow.html)
- [Documentation Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [Documentation Symfony Rate Limiter](https://symfony.com/doc/current/rate_limiter.html)
- [Documentation Symfony Lock](https://symfony.com/doc/current/components/lock.html)
- [Documentation Symfony Cache](https://symfony.com/doc/current/cache.html)
- [Documentation Symfony Scheduler](https://symfony.com/doc/current/scheduler.html)

## 🎯 Prochaines Étapes

Une fois le projet terminé, vous pouvez:
- Ajouter d'autres canaux (WhatsApp, Push notifications)
- Créer une vraie interface web avec EasyAdmin
- Ajouter de l'authentification
- Déployer en production
- Créer des rapports avancés

## 🔧 Makefile Recommandé

Créez un `Makefile` à la racine du projet pour simplifier les commandes:

```makefile
.PHONY: setup start stop worker test clean

setup:
	composer install
	docker-compose up -d
	php bin/console doctrine:database:create --if-not-exists
	php bin/console doctrine:migrations:migrate -n
	php bin/console doctrine:fixtures:load -n

start:
	docker-compose up -d
	symfony server:start -d

stop:
	docker-compose down
	symfony server:stop

worker:
	php bin/console messenger:consume async -vv

test:
	php bin/phpunit

clean:
	php bin/console doctrine:query:sql "DELETE FROM notification"
	php bin/console messenger:stop-workers
```

Utilisation:
```bash
make setup   # Installation complète
make start   # Démarrer l'environnement
make worker  # Lancer le worker
make test    # Lancer les tests
make clean   # Nettoyer les données
```
