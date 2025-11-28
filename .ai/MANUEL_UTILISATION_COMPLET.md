# Manuel d'Utilisation Complet - Système de Notification Multi-Canal

Ce document détaille les procédures pour utiliser, tester et vérifier le système de notification.

## 1. Démarrage du Système

Avant toute action, assurez-vous que tous les composants tournent :

1.  **Services Docker** (Base de données, Redis, Mailpit) :
    ```bash
    make up
    ```
2.  **Workers Messenger** (Pour traiter les notifications asynchrones) :
    ```bash
    make worker
    ```
    *Gardez ce terminal ouvert pour voir les logs de traitement en temps réel.*

3.  **Serveur Web** (Pour l'API et le Dashboard) :
    ```bash
    make start
    ```

---

## 2. Scénarios d'Utilisation

### Scénario A : Envoi d'une Notification par Email (Flux Complet)

Ce scénario teste toute la chaîne : CLI -> Base de données -> Worker -> Mailpit.

1.  **Action** : Lancez la commande de test dans un terminal :
    ```bash
    make send-test
    ```
    *Ou manuellement : `php bin/console notification:send --channel=email --recipient=user@example.com --message="Hello"`*

2.  **Ce qui se passe (Backend)** :
    *   La commande crée une notification en base (état `draft`).
    *   Elle la valide automatiquement (état `approved`).
    *   Elle dépose un message `SendNotificationMessage` dans la table **messenger_messages** (Doctrine transport).
    *   Le terminal affiche : `[INFO] Notification dispatched to async queue`.

3.  **Traitement (Worker)** :
    *   Le worker (lancé à l'étape 1) récupère le message dans la base de données.
    *   Il verrouille la notification (Lock via fichier) pour éviter les doublons.
    *   Il passe la notification à l'état `sending`.
    *   Il envoie l'email via le serveur SMTP (Mailpit).
    *   Il passe la notification à l'état `sent`.
    *   Il émet un événement `NotificationSentEvent`.

4.  **Vérification** :
    *   **Mailpit** : Ouvrez [http://localhost:8025](http://localhost:8025). Vous devriez voir l'email reçu.
    *   **Dashboard** : Ouvrez [http://localhost:8000/dashboard](http://localhost:8000/dashboard) (Login: `admin`/`admin`). La notification doit apparaître avec le statut **SENT** (Vert).

### Scénario B : Test du Rate Limiter

Ce scénario vérifie que l'on ne peut pas spammer un destinataire (Limite : 100/heure **par destinataire**).

**Important** : Le rate limiter est configuré **par destinataire**. Cela signifie que chaque adresse email a son propre compteur de 100 emails/heure.

1.  **Action** : Lancez la commande de test de charge :
    ```bash
    php bin/console test:rate-limiter
    ```

2.  **Résultat attendu** :
    *   La commande envoie 105 tentatives au **même destinataire** (`rate-limit-test@example.com`).
    *   Les 100 premiers passent.
    *   Le 101ème échoue avec : `Limit reached at attempt #101. Retry after 3600 seconds`.

**❌ Erreur courante** : 
```bash
# Ceci NE TESTE PAS le rate limiter correctement :
for i in {1..101}; do
  php bin/console notification:send --channel=email --recipient=test$i@example.com --message="Test $i"
done
```
Pourquoi ? Parce que chaque `test1@`, `test2@`, `test3@` est un destinataire **différent**, donc chacun a son propre compteur de 100/heure.


### Scénario C : Test du Retry Automatique (Scheduler)

Ce scénario vérifie que le système réessaie d'envoyer les notifications échouées.

1.  **Préparation** : Simulez une panne (ex: coupez Mailpit ou configurez un mauvais SMTP temporairement) ou créez une notification qui va échouer.
    *   *Pour ce test, nous allons utiliser une commande qui force un échec ou simplement observer le comportement standard si le réseau coupe.*

2.  **Action** : Une notification échoue (état `failed`).

3.  **Réparation** : Le problème est résolu (Mailpit redémarré).

4.  **Automatisme** :
    *   Le **Scheduler** tourne en tâche de fond (toutes les 5 minutes).
    *   Il détecte la notification `failed`.
    *   Il la repasse en `approved` et relance le processus.

5.  **Vérification** : La notification finit par passer à l'état `sent` sans intervention humaine.

---

## 3. Utilisation de l'API

L'API permet aux systèmes externes d'envoyer des notifications.

*   **URL** : `http://localhost:8000/api/notifications`
*   **Auth** : Basic (`api_user` / `api_pass`)

**Exemple de création (cURL)** :
```bash
curl -X POST http://localhost:8000/api/notifications \
  -u api_user:api_pass \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "email",
    "recipient": "client@example.com",
    "subject": "Facture disponible",
    "message": "Votre facture #123 est prête."
  }'
```

**Réponse attendue** :
```json
{
  "id": 42,
  "status": "created",
  "state": "draft"
}
```
*(Note: L'API déclenche aussi l'auto-approbation si configuré, donc l'état peut passer rapidement à `approved` puis `sent`)*.

---

## 4. Commandes Utiles

| Commande | Description |
| :--- | :--- |
| `make worker` | Lance le consommateur de messages asynchrones |
| `make stats` | Affiche les statistiques des notifications par état/canal |
| `make db-reset` | Réinitialise la base de données (Attention: supprime tout) |
| `make fixtures` | Charge des fausses données pour tester le dashboard |
