# ACL NotificationManager

Package Laravel pour envoyer, journaliser et consulter des notifications a partir d'une `event_key`.

Il permet de gerer :

- un catalogue runtime des evenements en base ;
- la validation dynamique du payload ;
- la resolution des templates `options -> base de donnees -> configuration` ;
- le rendu HTML ;
- l'envoi mail avec queue optionnelle ;
- les notifications in-app ;
- la journalisation dans la table `communications`.

## Utilisation

### 1. Installer le module :

```bash
composer require acl/notification-manager
```

### 2. Publier la configuration et les migrations :

```bash
php artisan vendor:publish --tag=communications-config
php artisan vendor:publish --tag=communications-migrations
php artisan migrate
```

### 3. Configurer le module (fichiers publies dans `config/`) :

- `config/communications.php` : definir les canaux, la queue, l'UI optionnelle et le catalogue des events Laravel.
- `config/events.php` : definir les `event_key`, les schemas de payload, les sujets et les templates par defaut.

Format attendu pour les `event_key` :

```text
<module>.<action>.<channel>
```

Exemples :

- `request.created.email`
- `billing.payment-reminder.email`
- `billing.reminder.in_app`

### 4. Declarer les evenements disponibles :

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
];
```

### 5. Synchroniser le catalogue en base :

```bash
php artisan notifications:sync
```

### 6. Configurer le mail :

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

### 7. Envoyer une notification :

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

### 8. Utiliser la queue en production :

Les canaux `email` et `mail` sont configures en queue par defaut.

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

### 9. Integrer un Event Laravel :

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

Declencher l'event :

```php
event(new RequestCreated('REQ-2026-001', 'Alex', 'alex@example.test', 42));
```

### 10. Activer l'UI optionnelle :

L'UI est desactivee par defaut.

```env
COMMUNICATIONS_UI_ENABLED=true
COMMUNICATIONS_UI_PREFIX=communications
COMMUNICATIONS_UI_VIEW=welcome
```

Routes pages :

- `GET /communications/templates`
- `GET /communications/notifications`

Routes API utilisees par l'UI :

- `GET /communications/api/templates`
- `GET /communications/api/templates/{id}`
- `GET|POST /communications/api/notifications`
- `GET|DELETE /communications/api/notifications/{id}`
- `PATCH /communications/api/notifications/{id}/read`
- `PATCH /communications/api/notifications/{id}/unread`

### 11. Tester rapidement le module :

```bash
php artisan communications:test-send --email=alex@example.test --name=Alex --request=REQ-2026-001
php artisan communications:simulate-payment-reminder payment-reminder --email=alex@example.test --name=Alex --due=2026-03-31
```

## Resolution De Template

Priorite de resolution :

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
        'subject' => 'Sujet personnalise',
        'queue' => false,
    ],
);
```
