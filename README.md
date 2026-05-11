# NotificationManager

Package Laravel pour envoyer des notifications à partir d'une `event_key`.

Il fait aujourd'hui :
- catalogue runtime des événements en base
- validation dynamique du payload
- résolution de template `options -> DB -> config`
- rendu HTML
- envoi mail avec queue optionnelle
- journalisation dans `communications`

## Démarrage Rapide

### 1. Installer

```bash
composer require acl/notification-manager
php artisan vendor:publish --tag=communications-config
php artisan vendor:publish --tag=communications-migrations
php artisan migrate
```

### 2. Déclarer les event keys

Créez ou complétez `config/events.php` dans l'application hôte :

```php
<?php

return [
    'request.created.email' => [
        'label' => 'Request created email',
        'payload' => [
            'request_number' => 'required|string',
            'requester_name' => 'required|string',
            'user_email' => 'required|email',
        ],
        'template' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistrée.</p>',
        'subject' => 'Nouvelle demande',
    ],

    'billing.payment-reminder.email' => [
        'label' => 'Payment reminder email',
        'payload' => [
            'name' => 'required|string',
            'due_date' => 'required|date',
            'user_email' => 'required|email',
        ],
        'template' => '<p>Bonjour {{ $name }}, votre facture arrive à échéance le {{ $due_date }}.</p>',
        'subject' => 'Rappel de paiement',
    ],
];
```

Format obligatoire :

```text
<module>.<action>.<channel>
```

Exemples : `request.created.email`, `billing.payment-reminder.email`, `request.created.in_app`.

### 3. Synchroniser en base

```bash
php artisan notifications:sync
```

### 4. Configurer le mail

Dans `.env` :

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 5. Envoyer un test

```bash
php artisan tinker
```

Puis dans Tinker :

```php
app(Acl\Communications\Contracts\NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    [
        'request_number' => 'REQ-2026-001',
        'requester_name' => 'Alex',
        'user_email' => 'alex@example.test',
    ],
    [
        'queue' => false,
    ],
);
```

### 6. Lancer la queue en production

Les canaux `email` et `mail` sont configurés en queue par défaut.

```bash
php artisan queue:work --queue=notifications.email,notifications.mail
```

Pour forcer un envoi synchrone lors d'un test :

```php
app(Acl\Communications\Contracts\NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    $payload,
    ['queue' => false],
);
```

## Intégration Via Event Laravel

Créer un event applicatif :

```php
<?php

namespace App\Events;

use Acl\Communications\Contracts\CommunicationEventInterface;

class RequestCreated implements CommunicationEventInterface
{
    public function __construct(
        public string $requestNumber,
        public string $requesterName,
        public string $userEmail,
        public ?int $userId = null,
    ) {}

    public function communicationEventKey(): string
    {
        return 'request.created.email';
    }

    public function communicationRecipient(): array|string
    {
        return [
            'address' => $this->userEmail,
            'type' => 'user',
            'id' => $this->userId !== null ? (string) $this->userId : null,
            'name' => $this->requesterName,
        ];
    }

    public function communicationData(): array
    {
        return [
            'request_number' => $this->requestNumber,
            'requester_name' => $this->requesterName,
            'user_email' => $this->userEmail,
            'user_id' => $this->userId !== null ? (string) $this->userId : null,
        ];
    }
}
```

Brancher le listener :

```php
use Acl\Communications\Listeners\NotificationListener;
use App\Events\RequestCreated;
use Illuminate\Support\Facades\Event;

Event::listen(RequestCreated::class, NotificationListener::class);
```

Déclencher :

```php
event(new RequestCreated('REQ-2026-001', 'Alex', 'alex@example.test', 42));
```

## UI Optionnelle

L'UI est désactivée par défaut.

```env
COMMUNICATIONS_UI_ENABLED=true
COMMUNICATIONS_UI_PREFIX=communications
COMMUNICATIONS_UI_VIEW=welcome
```

Routes :

```text
GET /communications/templates
GET /communications/notifications
```

API utilisée par l'UI :

```text
GET    /communications/api/templates
GET    /communications/api/templates/{id}
GET    /communications/api/notifications
POST   /communications/api/notifications
GET    /communications/api/notifications/{id}
PATCH  /communications/api/notifications/{id}/read
PATCH  /communications/api/notifications/{id}/unread
DELETE /communications/api/notifications/{id}
```

L'écran templates est en lecture seule. La création de templates se fait par seed, migration, import ou code applicatif hôte.

## Résolution De Template

Priorité :

1. `options['template']`
2. table `communication_templates` par `event_key` et `tenant_id`
3. fallback `config('events')[$eventKey]['template']`

Exemple d'override runtime :

```php
app(Acl\Communications\Contracts\NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    $payload,
    [
        'template' => '<p>Bonjour {{ $requester_name }}</p>',
        'subject' => 'Sujet personnalisé',
        'queue' => false,
    ],
);
```

## Tables

- `notification_events` : catalogue runtime, payload schema, activation
- `communication_templates` : templates actifs par `event_key`
- `communications` : historique des envois, statuts, payload, rendu
- `communication_rules` : conservée pour les évolutions multi-canaux futures

## Commandes Utiles

```bash
php artisan notifications:sync
php artisan communications:test-send
php artisan communications:simulate-payment-reminder payment-reminder --email=alex@example.test --name="Alex" --due=2026-03-31
php artisan test
npm run build
```

## Fichiers Importants

- `src/Contracts/NotificationManagerInterface.php`
- `src/Services/NotificationManager.php`
- `src/Services/NotificationTemplateResolver.php`
- `src/Services/CommunicationService.php`
- `src/Console/Commands/SyncNotificationEventsCommand.php`
- `src/CommunicationServiceProvider.php`
- `config/communications.php`
