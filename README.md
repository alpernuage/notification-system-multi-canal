# Système de Notification Multi-Canal

Un système de notification robuste et extensible construit avec Symfony 7.4, démontrant l'utilisation de composants avancés de Symfony.

## 🎯 Fonctionnalités

- **Multi-Canal** : Email, Slack, SMS, Webhook
- **Traitement Asynchrone** : Symfony Messenger avec Doctrine transport
- **Rate Limiting** : Protection anti-spam (100 emails/heure par destinataire)
- **Distributed Locking** : Prévention des doublons avec Flock
- **Workflow** : Machine à états pour gérer le cycle de vie des notifications
- **Scheduler** : Retry automatique des notifications échouées
- **API REST** : Endpoints sécurisés pour la gestion des notifications
- **Dashboard Admin** : Interface web pour visualiser les statistiques

## 🚀 Démarrage Rapide

### Prérequis

- Docker & Docker Compose
- PHP 8.2+
- Composer
- Make

### Installation

```bash
# Cloner le projet
git clone git@github.com:alpernuage/notification-system-multi-canal.git
cd multi-canal-notification-system

# Installation complète (première fois)
make setup

# Lancer le worker (dans un terminal séparé)
make worker

# Lancer le serveur web
make serve
```

### Accès

- **Dashboard** : http://localhost:8000/dashboard (admin / admin)
- **API** : http://localhost:8000/api/notifications (api_user / api_pass)
- **Mailpit** : http://localhost:8025

## 📚 Documentation

- [Guide de Démarrage](./GUIDE_DEMARRAGE.md) - Installation et utilisation
- [Manuel d'Utilisation Complet](./.ai/MANUEL_UTILISATION_COMPLET.md) - Scénarios de test détaillés
- [Guide Technique Détaillé](./.ai/guide_demarrage.md) - Explication approfondie de chaque composant
- [Plan d'Implémentation](./.ai/implementation_plan.md) - Architecture et décisions techniques

## 🧪 Tests

```bash
# Envoyer une notification de test
make send-test

# Tester le rate limiter
php bin/console test:rate-limiter

# Tester le lock distribué
php bin/console test:lock

# Voir les statistiques
make stats
```

## 🛠️ Stack Technique

- **Framework** : Symfony 7.4
- **Base de données** : PostgreSQL 16
- **Cache & Rate Limiter** : Redis 7
- **Email Testing** : Mailpit
- **Composants Symfony** :
  - Messenger (async processing)
  - Workflow (state machine)
  - Rate Limiter
  - Lock
  - Scheduler
  - Cache
  - Security

## 📦 Architecture

```
src/
├── Command/          # Commandes CLI
├── Controller/       # API & Dashboard
├── Entity/           # Notification entity
├── Event/            # Custom events
├── EventSubscriber/  # Event listeners
├── Message/          # Messenger messages
├── MessageHandler/   # Message handlers
├── Repository/       # Doctrine repositories
├── Scheduler/        # Scheduled tasks
└── Sender/           # Notification senders (Strategy pattern)
```

## 🔧 Commandes Utiles

```bash
make help           # Afficher toutes les commandes disponibles
make setup          # Installation complète
make worker         # Lancer le worker Messenger
make serve          # Lancer le serveur web
make stats          # Statistiques des notifications
make db-reset       # Réinitialiser la base de données
make clean          # Nettoyer le cache et les logs
```

