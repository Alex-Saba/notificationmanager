# ACL NotificationManager

Package Laravel pour envoyer, journaliser et consulter des notifications a partir d'une `event_key`.

## Installation

```bash
composer require acl/notification-manager
php artisan vendor:publish --tag=communications-config
php artisan vendor:publish --tag=communications-migrations
php artisan migrate
```

## Integration Cote Projet Principal

### 1. Declarer les notifications

Creer ou completer `config/events.php` :

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
        'template' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistree.</p>',
        'subject' => 'Nouvelle demande',
    ],

    'request.created.in_app' => [
        'label' => 'Request created in-app',
        'payload' => [
            'request_number' => 'required|string',
            'requester_name' => 'required|string',
            'user_id' => 'required',
        ],
        'template' => 'Demande {{ $request_number }} creee.',
        'subject' => 'Demande creee',
    ],
];
```

Format attendu :

```text
<module>.<action>.<channel>
```

Exemples : `request.created.email`, `billing.payment-reminder.email`, `request.created.in_app`.

### 2. Synchroniser en base

```bash
php artisan notifications:sync
```

Cette commande alimente `notification_events`, `communication_templates` et `communication_rules`.

### 3. Configurer le mail et la queue

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

En production :

```bash
php artisan queue:work --queue=notifications.email,notifications.mail
```

## Envoi Direct

```php
app(Acl\Communications\Contracts\NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    [
        'request_number' => 'REQ-2026-001',
        'requester_name' => 'Alex',
        'user_email' => 'alex@example.test',
    ],
);
```

Forcer un envoi synchrone :

```php
app(Acl\Communications\Contracts\NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    $payload,
    ['queue' => false],
);
```

## Envoi Via Event Laravel

Deux integrations sont possibles.

### Option A : event avec interface

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

Declencher :

```php
event(new App\Events\RequestCreated('REQ-2026-001', 'Alex', 'alex@example.test', 42));
```

### Option B : event mappe dans la config

Si l'event du projet principal ne peut pas implementer l'interface, completer `config/communications.php` :

```php
'events' => [
    'catalog' => [
        App\Events\RequestCreated::class => [
            'event_key' => 'request.created.email',
            'data_map' => [
                'request_number' => 'event.requestNumber',
                'requester_name' => 'event.requesterName',
                'user_email' => 'event.userEmail',
            ],
            'recipient_map' => [
                'address' => 'event.userEmail',
                'type' => 'event.recipientType',
                'id' => 'event.userId',
                'name' => 'event.requesterName',
            ],
        ],
    ],
],
```

Pour envoyer plusieurs notifications depuis le meme event :

```php
'events' => [
    'catalog' => [
        App\Events\RequestCreated::class => [
            'notifications' => [
                [
                    'event_key' => 'request.created.email',
                    'data_map' => [
                        'request_number' => 'event.requestNumber',
                        'requester_name' => 'event.requesterName',
                        'user_email' => 'event.userEmail',
                    ],
                    'recipient_map' => [
                        'address' => 'event.userEmail',
                        'type' => 'event.recipientType',
                        'id' => 'event.userId',
                        'name' => 'event.requesterName',
                    ],
                ],
                [
                    'event_key' => 'request.created.in_app',
                    'data_map' => [
                        'request_number' => 'event.requestNumber',
                        'requester_name' => 'event.requesterName',
                        'user_id' => 'event.userId',
                    ],
                    'recipient_map' => [
                        'type' => 'event.recipientType',
                        'id' => 'event.userId',
                        'name' => 'event.requesterName',
                    ],
                ],
            ],
        ],
    ],
],
```

## Contrat Destinataire

`communicationRecipient()` ou `recipient_map` doit fournir :

- `address` : email pour `email` ou `mail` ;
- `type` : type de destinataire, par exemple `user` ;
- `id` : identifiant metier, requis pour le in-app ;
- `name` : nom lisible du destinataire.

## UI Optionnelle

Dans `.env` :

```env
COMMUNICATIONS_UI_ENABLED=true
COMMUNICATIONS_UI_PREFIX=communications
COMMUNICATIONS_UI_VIEW=welcome
```

Routes pages :

- `GET /communications/templates`
- `GET /communications/notifications`

Routes API :

- `GET /communications/api/templates`
- `GET /communications/api/templates/{id}`
- `GET|POST /communications/api/notifications`
- `GET|DELETE /communications/api/notifications/{id}`
- `PATCH /communications/api/notifications/{id}/read`
- `PATCH /communications/api/notifications/{id}/unread`

## Resolution De Template

Priorite :

1. `options['template']`
2. table `communication_templates` par `event_key` et `tenant_id`
3. fallback `config('events')[$eventKey]['template']`

Override runtime :

```php
app(Acl\Communications\Contracts\NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    $payload,
    [
        'template' => '<p>Bonjour {{ $requester_name }}</p>',
        'subject' => 'Sujet personnalise',
        'queue' => false,
    ],
);
```

## Commandes Utiles

```bash
php artisan notifications:sync
php artisan communications:test-send --email=alex@example.test --name=Alex --request=REQ-2026-001
php artisan communications:simulate-payment-reminder payment-reminder --email=alex@example.test --name=Alex --due=2026-03-31
```
