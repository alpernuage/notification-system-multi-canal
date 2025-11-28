# Règles du Projet (AI Guidelines)

Ce fichier contient les règles que l'assistant doit respecter pour ce projet.

## 1. Cohérence Linguistique
- **Respecter la langue** : Toujours respecter la langue du fichier existant ou de la demande de l'utilisateur.
- **Français par défaut** : Si le projet/fichier est en français, tout ajout ou commentaire doit être en français.

## 2. Vérification de l'Environnement
- **Vérifier avant d'installer** : Avant d'installer une nouvelle dépendance système ou PHP (ex: extensions, librairies), vérifier d'abord si elle est disponible dans l'environnement actuel (via `php -m`, `docker ps`, etc.).
- **Pas de suppositions** : Ne jamais supposer que l'environnement est standard ou possède certaines extensions sans vérification.

## 3. Stabilité de la Configuration
- **Ne pas casser l'existant** : Si une configuration fonctionne (ex: Doctrine transport), ne pas la changer pour une autre (ex: Redis) sans une demande explicite ou une vérification préalable de la compatibilité.
- **Principe de précaution** : Privilégier la stabilité à l'optimisation prématurée si cela implique des changements d'infrastructure risqués.

## 4. Vérification (Rappel)
- **Toujours vérifier** : Tester chaque modification (bugfix ou feature) avec les commandes appropriées (`make test`, `make send-test`) avant de considérer la tâche comme finie.

## 5. Cohérence Documentation/Code
- **Mettre à jour la documentation** : Après chaque fix, modification ou changement de configuration, mettre à jour TOUS les fichiers de documentation concernés (`.ai/MANUEL_UTILISATION_COMPLET.md`, `.ai/guide_demarrage.md`, `README.md`, etc.).
- **Vérifier la cohérence** : S'assurer que la documentation reflète exactement le fonctionnement actuel du code (pas d'informations obsolètes).
