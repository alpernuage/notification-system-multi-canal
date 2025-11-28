# Système de Notifications Multi-Canal - Plan d'Implémentation

## Objectif

Créer une plateforme Symfony complète pour gérer l'envoi de notifications via plusieurs canaux (Email, SMS, Slack, Webhook) avec les fonctionnalités avancées suivantes:
- File d'attente intelligente avec Messenger
- Retry automatique et gestion des échecs
- Cache des templates
- Rate limiting par canal
- Workflow de validation (draft → approved → sent)
- Locks distribués pour éviter les doublons
- Scheduler pour maintenance automatique

Ce projet permet de découvrir et maîtriser 10+ composants avancés de Symfony dans un contexte pratique.

## État Actuel du Projet

✅ **Déjà installé**:
- Symfony 7.4 (webapp)
- Doctrine ORM + Migrations
- Symfony Messenger (doctrine-messenger)
- Symfony Mailer
- Symfony HttpClient
- Symfony Form + Validator
- Symfony Security
- Twig
- Docker Compose (PostgreSQL)

❌ **À installer**:
- Workflow Component
- Rate Limiter Component
- Lock Component
- Scheduler Component
- Cache Component (Redis)
- Redis (Docker)
- Mailpit (Docker)
- Predis (client Redis PHP)

## Proposed Changes

### Phase 0: Configuration Docker et Dépendances

#### [MODIFY] [compose.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/compose.yaml)

Ajouter les services:
- **Redis 7** pour cache, locks, et rate limiting
- **Mailpit** pour capturer et visualiser les emails en développement

```yaml
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      timeout: 5s
      retries: 5

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"  # Interface web
      - "1025:1025"  # SMTP
    environment:
      MP_SMTP_AUTH_ACCEPT_ANY: 1
      MP_SMTP_AUTH_ALLOW_INSECURE: 1

volumes:
  redis_data:
  database_data:
```

#### [MODIFY] [.env](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/.env)

Ajouter les variables:
```env
# Redis
REDIS_URL=redis://redis:6379

# Mailer (Mailpit)
MAILER_DSN=smtp://mailpit:1025

# Slack Webhook (optionnel)
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

#### [NEW] [Makefile](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/Makefile)

Commandes pratiques pour:
- `make setup`: Installation complète du projet
- `make start`: Démarrer les containers
- `make test`: Lancer les tests
- `make worker`: Démarrer le worker Messenger
- `make clean`: Nettoyer les notifications

#### Installation des dépendances manquantes

```bash
composer require symfony/workflow
composer require symfony/rate-limiter
composer require symfony/lock
composer require symfony/scheduler
composer require symfony/cache
composer require predis/predis
composer require --dev doctrine/doctrine-fixtures-bundle
```

---

### Phase 1: Foundation - Entité et Workflow

#### [NEW] [src/Entity/Notification.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Entity/Notification.php)

Entité principale avec:
- `id`, `channel` (email/sms/slack/webhook)
- `recipient`, `subject`, `message`
- `state` (marking pour le workflow)
- `createdAt`, `sentAt`, `failedAt`
- `retryCount`, `lastError`
- `metadata` (JSON pour données additionnelles)

#### [NEW] [config/packages/workflow.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/packages/workflow.yaml)

Configuration du workflow:
- **Places**: `draft`, `approved`, `sending`, `sent`, `failed`
- **Transitions**:
  - `approve`: draft → approved (avec guard pour vérifier le destinataire)
  - `send`: approved → sending
  - `mark_as_sent`: sending → sent
  - `mark_as_failed`: sending → failed
  - `retry`: failed → approved

#### [NEW] [src/Workflow/NotificationGuard.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Workflow/NotificationGuard.php)

Guards pour valider les transitions:
- Vérifier que le destinataire existe
- Vérifier que le message n'est pas vide
- Limiter le nombre de retries

#### [NEW] [src/EventSubscriber/NotificationWorkflowSubscriber.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/EventSubscriber/NotificationWorkflowSubscriber.php)

Logger les changements d'état du workflow

#### [NEW] [src/DataFixtures/NotificationFixtures.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/DataFixtures/NotificationFixtures.php)

Fixtures pour créer des notifications de test

---

### Phase 2: Messenger et Strategy Pattern

#### [MODIFY] [config/packages/messenger.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/packages/messenger.yaml)

Configuration Messenger:
- Transport `async` avec Doctrine (déjà configuré)
- Retry strategy exponentielle (3 retries, backoff 1s → 2s → 4s)
- Failed queue pour les messages en échec
- Routing des messages

#### [NEW] [src/Message/SendNotificationMessage.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Message/SendNotificationMessage.php)

Message simple contenant l'ID de la notification

#### [NEW] [src/MessageHandler/SendNotificationHandler.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/MessageHandler/SendNotificationHandler.php)

Handler qui:
1. Récupère la notification
2. Applique le workflow (transition `send`)
3. Utilise le `NotificationDispatcher` pour envoyer
4. Applique `mark_as_sent` ou `mark_as_failed`
5. Utilise un Lock pour éviter les doublons

#### [NEW] [src/Sender/NotificationSenderInterface.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Sender/NotificationSenderInterface.php)

Interface pour tous les senders:
- `supports(string $channel): bool`
- `send(Notification $notification): void`

#### [NEW] [src/Sender/EmailSender.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Sender/EmailSender.php)

Implémentation pour Email:
- Utilise Symfony Mailer (déjà installé)
- Applique le rate limiting
- Rend les templates avec cache

#### [NEW] [src/Sender/SlackSender.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Sender/SlackSender.php)

Implémentation pour Slack:
- Utilise HttpClient (déjà installé)
- Applique le rate limiting
- Formate le message pour Slack

#### [NEW] [src/Sender/SmsSender.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Sender/SmsSender.php)

Mock pour SMS (ou intégration Twilio si souhaité)

#### [NEW] [src/Sender/WebhookSender.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Sender/WebhookSender.php)

Envoi HTTP POST vers un webhook

#### [NEW] [src/Service/NotificationDispatcher.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Service/NotificationDispatcher.php)

Service qui utilise le Service Locator pour:
1. Trouver le bon sender selon le canal
2. Déléguer l'envoi
3. Gérer les erreurs

#### [MODIFY] [config/services.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/services.yaml)

Configuration des services:
- Tag `notification.sender` pour tous les senders
- Service Locator avec `#[AutowireLocator]`

---

### Phase 3: Rate Limiter et Lock

#### [NEW] [config/packages/rate_limiter.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/packages/rate_limiter.yaml)

Configuration des limiters:
- `email_sender`: 100 emails/heure (token bucket)
- `sms_sender`: 10 SMS/minute (fixed window)
- `slack_sender`: 50 messages/heure
- Storage: Redis

#### [MODIFY] [src/Sender/EmailSender.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Sender/EmailSender.php)

Ajout du rate limiting:
- Injection du `RateLimiterFactory`
- Consommation de tokens avant envoi
- Exception si limite atteinte

#### [NEW] [config/packages/lock.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/packages/lock.yaml)

Configuration des locks distribués avec Redis

#### [MODIFY] [src/MessageHandler/SendNotificationHandler.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/MessageHandler/SendNotificationHandler.php)

Ajout du lock:
- Créer un lock unique par notification
- Acquérir avant traitement
- Libérer dans un `finally`

---

### Phase 4: Cache et Events

#### [NEW] [config/packages/cache.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/packages/cache.yaml)

Configuration du cache:
- Pool `cache.app` avec Redis
- TTL par défaut: 1 heure
- Support des tags

#### [NEW] [src/Service/NotificationTemplateRenderer.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Service/NotificationTemplateRenderer.php)

Service pour rendre les templates:
- Cache avec tags (`template.{name}`, `notifications`)
- Invalidation par template ou globale
- Utilise Twig (déjà installé)

#### [NEW] [templates/notifications/email_welcome.html.twig](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/templates/notifications/email_welcome.html.twig)

Template d'exemple pour email de bienvenue

#### [NEW] [src/Event/NotificationSentEvent.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Event/NotificationSentEvent.php)

Event dispatché après envoi réussi

#### [NEW] [src/Event/NotificationFailedEvent.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Event/NotificationFailedEvent.php)

Event dispatché après échec

#### [NEW] [src/EventSubscriber/NotificationEventLogger.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/EventSubscriber/NotificationEventLogger.php)

Subscriber pour logger tous les events de notification

---

### Phase 5: Scheduler et Interface

#### [NEW] [config/packages/scheduler.yaml](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/config/packages/scheduler.yaml)

Configuration du scheduler

#### [NEW] [src/Message/CleanupOldNotificationsMessage.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Message/CleanupOldNotificationsMessage.php)

Message planifié (cron: `0 3 * * *`)

#### [NEW] [src/MessageHandler/CleanupOldNotificationsHandler.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/MessageHandler/CleanupOldNotificationsHandler.php)

Handler pour supprimer les notifications > 30 jours

#### [NEW] [src/Command/NotificationSendCommand.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Command/NotificationSendCommand.php)

Commande CLI pour créer et envoyer une notification manuellement

#### [NEW] [src/Command/NotificationStatsCommand.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Command/NotificationStatsCommand.php)

Commande CLI pour afficher les statistiques

#### [NEW] [src/Controller/Api/NotificationController.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Controller/Api/NotificationController.php)

API REST pour:
- `POST /api/notifications`: Créer une notification
- `GET /api/notifications`: Lister les notifications
- `POST /api/notifications/{id}/approve`: Approuver
- `GET /api/notifications/stats`: Statistiques

#### [NEW] [src/Controller/DashboardController.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/src/Controller/DashboardController.php)

Dashboard simple HTML avec:
- Compteurs (total, sent, failed, pending)
- Liste des dernières notifications
- Graphique simple des envois par canal

#### [NEW] [templates/dashboard/index.html.twig](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/templates/dashboard/index.html.twig)

Template pour le dashboard

---

### Phase 6: Tests et Documentation

#### [NEW] [tests/Unit/Sender/EmailSenderTest.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/tests/Unit/Sender/EmailSenderTest.php)

Tests unitaires pour `EmailSender`

#### [NEW] [tests/Integration/Workflow/NotificationWorkflowTest.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/tests/Integration/Workflow/NotificationWorkflowTest.php)

Tests d'intégration pour le workflow

#### [NEW] [tests/Integration/Messenger/SendNotificationTest.php](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/tests/Integration/Messenger/SendNotificationTest.php)

Tests pour le flow complet Messenger

#### [NEW] [README.md](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/README.md)

Documentation complète:
- Architecture du projet
- Installation et setup
- Utilisation des commandes
- Explication de chaque composant
- Guides de test

#### [NEW] [docs/COMPONENTS.md](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/docs/COMPONENTS.md)

Guide détaillé de chaque composant Symfony utilisé

#### [NEW] [docs/TESTING.md](file:///home/alp/PhpstormProjects/Personal/multi-canal-notification-system/docs/TESTING.md)

Guide pour tester chaque fonctionnalité

---

## Verification Plan

### ⚠️ Règle de Vérification Obligatoire
- **Toujours vérifier les correctifs** : Lors de la correction d'un bug ou de l'implémentation d'une fonctionnalité, vous DEVEZ vérifier que cela fonctionne en exécutant les tests pertinents (ex: `make send-test`, `make test`, ou commandes CLI spécifiques) AVANT de déclarer la tâche terminée.
- **Ne pas supposer** : Ne supposez pas qu'un changement de configuration fonctionne sans le tester.
- **Vérifier les dépendances** : Avant d'ajouter une dépendance (comme `symfony/redis-messenger`), vérifiez si l'environnement la supporte (ex: extensions PHP).



### Automated Tests

```bash
# Tests unitaires et d'intégration
php bin/phpunit

# PHPStan (si installé)
vendor/bin/phpstan analyse

# PHP-CS-Fixer (si installé)
vendor/bin/php-cs-fixer fix --dry-run
```

### Manual Verification

#### 1. Vérifier le Workflow
```bash
# Créer une notification en draft
php bin/console notification:send --channel=email --recipient=test@example.com --message="Test" --draft

# Approuver la notification
php bin/console notification:approve 1

# Vérifier dans Mailpit: http://localhost:8025
```

#### 2. Vérifier Messenger
```bash
# Démarrer le worker
php bin/console messenger:consume async -vv

# Créer une notification (elle sera traitée async)
php bin/console notification:send --channel=email --recipient=test@example.com --message="Async test"

# Observer les logs du worker
```

#### 3. Vérifier le Rate Limiting
```bash
# Envoyer 101 emails rapidement (le 101ème devrait échouer)
for i in {1..101}; do
  php bin/console notification:send --channel=email --recipient=test$i@example.com --message="Test $i"
done

# Vérifier les erreurs de rate limit dans les logs
```

#### 4. Vérifier le Cache
```bash
# Envoyer 2 notifications avec le même template
php bin/console notification:send --channel=email --template=welcome --recipient=user1@example.com
php bin/console notification:send --channel=email --template=welcome --recipient=user2@example.com

# La 2ème devrait être plus rapide (cache hit)
# Vérifier dans les logs
```

#### 5. Vérifier le Lock
```bash
# Démarrer 2 workers en parallèle
php bin/console messenger:consume async &
php bin/console messenger:consume async &

# Créer une notification
php bin/console notification:send --channel=email --recipient=test@example.com

# Vérifier qu'elle n'est traitée qu'une seule fois
```

#### 6. Vérifier le Scheduler
```bash
# Lancer le scheduler
php bin/console messenger:consume scheduler_default

# Attendre 3h du matin OU modifier le cron pour tester
# Vérifier que les vieilles notifications sont supprimées
```

#### 7. Vérifier le Dashboard
```bash
# Ouvrir le dashboard
open http://localhost:8000/dashboard

# Vérifier les statistiques
# Créer quelques notifications et rafraîchir
```

#### 8. Vérifier l'API REST
```bash
# Créer une notification via API
curl -X POST http://localhost:8000/api/notifications \
  -H "Content-Type: application/json" \
  -d '{"channel":"email","recipient":"api@example.com","message":"Test API"}'

# Lister les notifications
curl http://localhost:8000/api/notifications

# Approuver
curl -X POST http://localhost:8000/api/notifications/1/approve

# Stats
curl http://localhost:8000/api/notifications/stats
```

### Validation des Composants

Pour chaque composant, je créerai une commande de test dédiée:

- `php bin/console test:workflow` - Tester toutes les transitions
- `php bin/console test:messenger` - Tester async + retry
- `php bin/console test:rate-limiter` - Tester les limites
- `php bin/console test:cache` - Tester cache + invalidation
- `php bin/console test:lock` - Tester les locks distribués
- `php bin/console test:all` - Lancer tous les tests de composants
