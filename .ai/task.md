# Système de Notifications Multi-Canal - Symfony

## Phase 0: Setup Initial
- [x] Vérifier la structure Docker existante (PostgreSQL ✅)
- [x] Ajouter Redis et Mailpit au compose.yaml
- [x] Installer les dépendances Symfony manquantes
- [x] Vérifier l'environnement de développement

## Phase 1: Foundation (Jour 1)
- [x] Créer l'entité `Notification` avec les champs de base
- [x] Configurer les migrations Doctrine
- [x] Implémenter le Workflow basique (draft → approved → sent)
- [x] Créer des fixtures pour tester
- [x] Vérifier le workflow avec une commande de test

## Phase 2: Messenger + Strategy Pattern (Jour 2)
- [x] Configurer Messenger avec transport Doctrine (déjà partiellement fait)
- [x] Créer le message `SendNotificationMessage`
- [x] Implémenter le handler `SendNotificationHandler`
- [x] Créer l'interface `NotificationSenderInterface`
- [x] Implémenter `EmailSender` avec Symfony Mailer
- [x] Implémenter `SlackSender` avec HttpClient
- [x] Créer le `NotificationDispatcher` avec Service Locator
- [x] Tester l'envoi asynchrone

## Phase 3: Rate Limiter + Lock (Jour 3)
- [x] Configurer le Rate Limiter avec Redis
- [x] Ajouter le rate limiting dans `EmailSender`
- [x] Ajouter le rate limiting dans `SlackSender`
- [x] Implémenter le Lock dans `SendNotificationHandler`
- [x] Tester les limites de taux
- [x] Vérifier la protection contre les doublons

## Phase 4: Scheduler + Events (Jour 4)
- [x] Configurer le Scheduler pour les retries
- [x] Créer le message `RetryFailedNotificationsMessage`
- [x] Implémenter le handler pour relancer les notifications échouées
- [x] Configurer le transport `scheduler`
- [x] Vérifier le rejeu des notifications échouées
- [x] Implémenter les events custom (`NotificationSentEvent`, etc.)
- [x] Créer les Event Subscribers pour logging

## Phase 5: API & Dashboard (Jour 5)
- [x] Créer `NotificationController` pour l'API
- [x] Créer `DashboardController` pour l'interface admin
- [x] Créer les templates Twig pour le dashboard
- [x] Ajouter une page de statistiques
- [x] Sécuriser l'accès (Basic Auth pour démo)
- [x] Documenter le projet (README, guides)

## Phase 6: Tests et Validation
- [x] Vérifier tous les composants
- [x] Tester le flux complet
- [x] Valider la documentation
- [x] Tester le retry automatique
- [x] Tester le rate limiting
- [x] Valider tous les composants ensemble

## Composants Symfony à Maîtriser

- ✅ Doctrine ORM (déjà installé)
- ✅ Symfony Messenger (déjà installé)
- ✅ Symfony Mailer (déjà installé)
- ✅ Symfony HttpClient (déjà installé)
- [ ] Workflow Component
- [ ] Rate Limiter Component
- [ ] Lock Component
- [ ] Scheduler Component
- [ ] Cache Component
- [ ] Service Locator Pattern
- [ ] Event Dispatcher (déjà disponible)
