# TodoApp — Laravel (avec Resend)

Ce dépôt contient une application Laravel minimale de gestion de tâches (Todo) avec rappels par e-mail.

Le projet utilise :
- PHP ^8.2
- Laravel ^12
- Laravel Sanctum (API tokens)
- Resend (via `resend/resend-laravel`) pour l'envoi d'e-mails
- Vite + Tailwind pour les assets frontend

## Points clés
- Commandes utiles dans `app/Console/Commands` :
  - `tasks:send-reminders` — parcourt les tâches dont la `target_date` est proche et envoie des notifications.
  - `test:resend` — commande de test pour recréer une tâche de test ou utiliser une tâche existante et envoyer un e-mail de test.

## Routes API & Web

### Authentification & Utilisateur

| Méthode | Chemin                                 | Description                                      | Middleware         |
|---------|----------------------------------------|--------------------------------------------------|--------------------|
| POST    | /api/register                          | Inscription (retourne un token)                  | -                  |
| POST    | /api/login                             | Connexion (retourne un token)                    | -                  |
| POST    | /api/logout                            | Déconnexion (invalide le token)                  | auth:sanctum       |

### Vérification Email
| Méthode | Chemin                                 | Description                                      | Middleware         |
|---------|----------------------------------------|--------------------------------------------------|--------------------|
| GET     | /api/email/verify/{id}/{hash}          | Vérifie l'email via lien signé                   | signed             |
| POST    | /api/email/verification-notification   | Renvoyer l'email de vérification                 | auth:sanctum, throttle |

### Mot de passe oublié / réinitialisation
| Méthode | Chemin                                 | Description                                      | Middleware         |
|---------|----------------------------------------|--------------------------------------------------|--------------------|
| POST    | /api/forgot-password                   | Demande de lien de réinitialisation              | -                  |
| POST    | /api/reset-password                    | Réinitialise le mot de passe                     | -                  |

### Authentification sociale (OAuth)
| Méthode | Chemin                                 | Description                                      | Middleware         |
|---------|----------------------------------------|--------------------------------------------------|--------------------|
| GET     | /api/auth/{provider}                   | Redirige vers le provider OAuth (google, github) | -                  |
| GET     | /api/auth/{provider}/callback          | Callback OAuth                                   | -                  |

Note: la connexion standard nécessite que l'adresse e-mail de l'utilisateur soit vérifiée. Si l'utilisateur n'a pas vérifié son e-mail, la tentative de connexion renverra une erreur et il faudra utiliser :

- POST /api/email/verification-notification (auth required) pour renvoyer le lien de vérification.

### Tâches (protégé, email vérifié)
| Méthode | Chemin                                 | Description                                      | Middleware         |
|---------|----------------------------------------|--------------------------------------------------|--------------------|
| GET     | /api/tasks                             | Liste paginée des tâches de l'utilisateur        | auth:sanctum, verified |
| POST    | /api/tasks                             | Crée une nouvelle tâche                          | auth:sanctum, verified |
| PUT     | /api/tasks/{task}                      | Bascule l'état (done/pending)                    | auth:sanctum, verified |
| DELETE  | /api/tasks/{task}                      | Supprime une tâche                               | auth:sanctum, verified |

- Les tâches sont représentées par le modèle `App\Models\Task` (champs principaux : `task`, `state`, `target_date`, `reminder_sent`).
- Les rappels sont envoyés via la notification `App\Notifications\TaskReminder`.
- Commandes utiles dans `app/Console/Commands` :
  - `tasks:send-reminders` — parcourt les tâches dont la `target_date` est proche et envoie des notifications.
  - `test:resend` — commande de test pour recréer une tâche de test ou utiliser une tâche existante et envoyer un e-mail de test.

## Installation (développement)

1. Clonez le dépôt et placez-vous dans le dossier :

```bash
git clone <repo-url> todoApp
cd todoApp
```

2. Installez les dépendances PHP et JS :

```bash
composer install --prefer-dist --no-interaction
npm install
```

3. Créez un fichier `.env` (copiez `.env.example`) et générez la clé d'application :

```bash
cp .env.example .env
php artisan key:generate
```

4. Créez la base SQLite (le projet crée `database/database.sqlite` automatiquement lors du `post-create-project-cmd`, mais vous pouvez le faire manuellement) :

```bash
touch database/database.sqlite
```

5. Exécutez les migrations :

```bash
php artisan migrate
```

6. Lancez les assets (développement) :

```bash
npm run dev
```

7. Lancez le serveur local Laravel :

```bash
php artisan serve
```

## Configuration des e-mails (Resend)

Cette application utilise Laravel Mail / Notifications. Par défaut vous pouvez configurer un transport sûr pour les tests locaux :

```env
MAIL_MAILER=log
QUEUE_CONNECTION=sync
MAIL_FROM_ADDRESS=no-reply@example.test
MAIL_FROM_NAME="Todo App"
```

Si vous voulez utiliser Resend en production :

1. Installez et configurez `resend/resend-laravel` (déjà présent dans `composer.json`).
2. Ajoutez votre clé API Resend dans `.env` (selon la configuration du package) :

```env
RESEND_API_KEY=your_resend_api_key_here
MAIL_MAILER=resend
```

Consultez la doc du package Resend pour le paramétrage exact du mailer si vous avez personnalisé le nom du mailer.

## Commandes Artisan utiles

- Lister les commandes disponibles :

```bash
php artisan list
```

- Envoyer un rappel (commande automatique qui vérifie la base) :

```bash
php artisan tasks:send-reminders
```

- Tester l'envoi Resend / Notification :

```bash
# Créer une tâche de test pour l'utilisateur 1 (par défaut)
php artisan test:resend --create-task

# Envoyer à un user existant et une tâche existante
php artisan test:resend --user=2 --task=123
```

Options `test:resend` :
- `--user=ID` : identifiant de l'utilisateur (par défaut 1)
- `--task=ID` : identifiant d'une tâche existante
- `--create-task` : crée une tâche de test et l'utilise pour l'envoi

## Exécution planifiée (cron)

Pour envoyer automatiquement les rappels, ajoutez une entrée cron qui exécute le scheduler de Laravel chaque minute :

```cron
* * * * * cd /chemin/vers/todoApp && php artisan schedule:run >> /dev/null 2>&1
```

Dans `app/Console/Kernel.php` vous devriez ensuite ajouter (ou vérifier) la ligne qui planifie `tasks:send-reminders`, par exemple :

```php
$schedule->command('tasks:send-reminders')->everyMinute();
```

Si vous préférez un intervalle moins agressif, adaptez en conséquence.

## Queue

La notification `TaskReminder` implémente `ShouldQueue`. En production, lancez un worker de queue :

```bash
php artisan queue:work --tries=3
```

Pour tester sans workers en local, utilisez `QUEUE_CONNECTION=sync`.

## Base de données / modèles

- `users` : table standard (voir migrations).
- `tasks` : champs principaux
  - `id`, `task` (string), `state` ('pending'|'done'), `target_date` (timestamp|null), `reminder_sent` (boolean), timestamps, soft deletes.
- `notifications` : table utilisée par Laravel Notifications (migrations fournies).

## Tests

Le projet utilise PHPUnit. Lancez les tests :

```bash
php artisan test
```

## Dépannage rapide

- Pas d'e-mail envoyé : vérifiez `MAIL_MAILER` et vos logs (`storage/logs/laravel.log`) si vous utilisez `log`.
- Notifications en file d'attente non traitées : démarrez le worker (`php artisan queue:work`).
- Erreur `Utilisateur non trouvé` ou `Tâche non trouvée` : vérifiez les IDs et la connexion DB.
- Problèmes avec Resend : assurez-vous d'avoir la bonne clé API et le mailer correct configuré dans `.env`.

## Contribution

PRs et issues bienvenus. Respectez les conventions PSR-12 et lancez `composer test` / `php artisan test` avant d'ouvrir une PR.

## Licence

MIT
