# NotificationManager

`acl/notification-manager` fournit un package Laravel de notifications, aligne sur une architecture `NotificationManager`.

Le module permet de :
- declencher une notification via une cle unique
- valider dynamiquement le payload
- router vers un channel a partir de la cle
- resoudre un template par priorite `options -> DB -> config`
- travailler sur un catalogue runtime centralise en base
- rester agnostique des modules metier

## Structure du depot

Le coeur distribuable du package est porte par :
- `src/`
- `config/communications.php`
- `database/migrations/communications`
- `routes/communications-ui.php`

Le depot contient aussi une application Laravel locale de demonstration pour developper et tester le package.
Cette application n'est pas destinee a etre publiee comme partie du package Composer.

## Objectif

Le projet principal :
- declare ses evenements dans ses modules via `config/events.php`
- fusionne ces declarations dans `config('events')`
- declenche les notifications par cle

Le package :
- synchronise ces declarations vers la base
- utilise la table `notification_events` comme source runtime
- valide le payload
- resout le template
- envoie via le channel correspondant

## Convention de cle

Format obligatoire :

```text
<module>.<action>.<channel>
```

Exemples :
- `request.created.email`
- `request.created.in_app`
- `request.approved.email`
- `billing.payment-reminder.email`

## Installation

```bash
composer require acl/notification-manager
php artisan vendor:publish --tag=communications-config
php artisan vendor:publish --tag=communications-migrations
php artisan migrate
```

Le provider principal du package est `src/CommunicationServiceProvider.php`.

## Procedure d'integration dans le projet principal

Ordre recommande :
1. installer le package avec Composer
2. publier `config/communications.php`
3. publier les migrations du package
4. executer `php artisan migrate`
5. declarer les `event_key` metier dans `config/events.php`
6. synchroniser le catalogue runtime avec `php artisan notifications:sync`
7. envoyer un premier message de test via `NotificationManagerInterface`

Prerequis cote projet hote :
- Laravel 12
- PHP 8.2 minimum
- une configuration mail exploitable si le channel `email` est utilise
- un fichier `config/events.php` ou un mecanisme equivalent qui alimente `config('events')`

## Configuration minimale du projet hote

Exemple minimal de `config/events.php` :

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

Dans cet exemple, la cle `template` correspond a un fallback de demarrage.
Elle permet au package de rendre un contenu minimal meme si aucun template n'a encore ete cree en base.
Si un template actif existe dans la table `communication_templates` pour la meme `event_key`, il devient prioritaire sur ce fallback de configuration.

Exemple minimal de configuration mail dans le projet hote :

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

## Declaration des evenements

Les evenements ne sont pas declares dans le package comme source metier finale.
Ils sont declares dans le projet principal, puis lus par le package via `config('events')`.

Exemple de fichier hote `config/events.php` :

```php
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

Le package suppose que les `events.php` des modules du projet principal sont fusionnes automatiquement par l'application hote.

La cle `template` dans `config/events.php` sert surtout de fallback de configuration pour un premier envoi ou pour un fonctionnement sans template base.

## Synchronisation runtime

Le runtime des evenements est centralise dans la table `notification_events`.

Commande de synchronisation :

```bash
php artisan notifications:sync
```

Cette commande lit `config('events')` puis met a jour la table `notification_events`.

## Premier envoi manuel

Une fois les evenements synchronises, un premier envoi peut etre teste directement :

```php
use Acl\Communications\Contracts\NotificationManagerInterface;

app(NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    [
        'request_number' => 'REQ-2026-001',
        'requester_name' => 'Alex',
        'user_email' => 'alex@example.test',
    ],
);
```

Sequence minimale de verification :

```bash
php artisan notifications:sync
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
);
```

## Structure runtime

### Table `notification_events`

Champs principaux :
- `id`
- `key`
- `label`
- `payload_schema`
- `is_active`

### Table `communication_templates`

Le package utilise la table des templates pour la resolution runtime :
- `event_key`
- `tenant_id`
- `subject`
- `content`
- `active`

### Table `communications`

Elle journalise les envois :
- `notification_event_id`
- `event_key`
- `channel`
- `status`
- `payload`
- `rendered_content`
- `recipient_*`

## Interface principale

Le contrat cible est `Acl\Communications\Contracts\NotificationManagerInterface`.

```php
use Acl\Communications\Contracts\NotificationManagerInterface;

app(NotificationManagerInterface::class)->dispatch(
    'request.created.email',
    [
        'request_number' => 'REQ-2026-001',
        'requester_name' => 'Alex',
        'user_email' => 'alex@example.test',
    ],
);
```

## Flow d'execution

`dispatch()` fait :
1. lecture de l'evenement dans `notification_events`
2. validation du payload via `payload_schema`
3. parsing de la cle
4. resolution du template
5. rendu final
6. journalisation dans `communications`
7. publication dans une queue si le channel est configure avec `queue => true`
8. execution du driver par `SendCommunicationJob` ou envoi direct si la queue est desactivee

Quand un canal est mis en queue, le package ne contacte pas le fournisseur email/SMS pendant l'appel initial.
Il prepare le message, cree une ligne `communications` avec le statut `queued`, puis publie un job Laravel.
Le worker du projet hote consomme ensuite la queue et appelle le driver reel.

## Validation du payload

Le payload est valide dynamiquement a partir du schema stocke dans `notification_events.payload_schema`.

Exemple :

```php
[
    'request_number' => 'required|string',
    'user_email' => 'required|email',
]
```

## Routing channel

Le channel est derive du troisieme segment de la cle.

Exemple :
- `request.created.email` -> channel `email`
- `billing.reminder.in_app` -> channel `in_app`

Le manager route ensuite vers le driver declare dans `config/communications.php`.

Configuration actuelle :

```php
'channels' => [
    'email' => [
        'driver' => Acl\Communications\Channels\MailChannel::class,
        'queue' => true,
        'queue_name' => env('COMMUNICATIONS_EMAIL_QUEUE', 'notifications.email'),
    ],
    'sms' => [
        'driver' => Acl\Communications\Channels\NullChannel::class,
        'queue' => false,
        'queue_name' => env('COMMUNICATIONS_SMS_QUEUE', 'notifications.sms'),
    ],
    'in_app' => [
        'driver' => Acl\Communications\Channels\NullChannel::class,
        'queue' => false,
        'queue_name' => env('COMMUNICATIONS_IN_APP_QUEUE', 'notifications.in_app'),
    ],
],
```

Pour traiter les messages asynchrones, le projet hote lance un worker Laravel sur la queue configuree :

```bash
php artisan queue:work --queue=notifications.email
```

Un appel peut forcer le comportement synchrone ou asynchrone avec l'option `queue` :

```php
app(NotificationManagerInterface::class)->dispatch($eventKey, $payload, [
    'queue' => false,
]);
```

## Resolution de template

La priorite de resolution est :
1. override runtime via `options`
2. template actif en base par `event_key` et `tenant_id`
3. fallback config via `config('events')`

Implementation : `src/Services/NotificationTemplateResolver.php`

En pratique :
- si un template existe en base, c'est lui qui est rendu
- sinon, le package peut utiliser `config/events.php['template']` comme contenu de secours

## Integration par evenement applicatif

Le package supporte aussi un pont depuis un event Laravel du projet principal.

Exemple d'evenement hote local : `app/Events/RequestCreated.php`

Exemple minimal d'evenement hote :

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
    ) {
    }

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

Enregistrement du listener dans le projet hote :

```php
use Acl\Communications\Listeners\NotificationListener;
use App\Events\RequestCreated;
use Illuminate\Support\Facades\Event;

Event::listen(RequestCreated::class, NotificationListener::class);
event(new RequestCreated('REQ-2026-001', 'Alex', 'alex@example.test', 42));
```

Dans ce cas :
- l'event applicatif fournit la cle et le payload
- `src/Services/CommunicationService.php` delegue ensuite a `NotificationManagerInterface`

## Checklist d'installation rapide

```bash
composer require acl/notification-manager
php artisan vendor:publish --tag=communications-config
php artisan vendor:publish --tag=communications-migrations
php artisan migrate
php artisan notifications:sync
```

Puis verifier :
- la table `notification_events` contient les cles attendues
- la configuration mail du projet hote est correcte
- un appel a `NotificationManagerInterface::dispatch()` cree une ligne dans `communications`
- le channel attendu est bien configure dans `config/communications.php`

## UI optionnelle

Le depot contient aussi une UI Vue de demonstration pour :
- consulter les templates
- visualiser des notifications in-app

Dans un projet principal, cette UI est desactivee par defaut pour eviter les collisions de routes et de vues.
Il faut l'activer explicitement si elle est voulue.

Routes UI par defaut :
- `/communications/templates`
- `/communications/notifications`

Routes API UI par defaut :
- `GET /communications/api/templates`
- `GET /communications/api/templates/{id}`
- `GET /communications/api/notifications`
- `POST /communications/api/notifications`
- `GET /communications/api/notifications/{id}`
- `PATCH /communications/api/notifications/{id}/read`
- `PATCH /communications/api/notifications/{id}/unread`
- `DELETE /communications/api/notifications/{id}`

L'UI templates est volontairement en lecture seule dans l'etat actuel du package.
La creation ou modification de templates doit etre faite par seed, migration, import ou code applicatif hote.

Le prefixe et les middlewares sont configurables dans `config/communications.php`.

Activation explicite dans le projet hote :

```env
COMMUNICATIONS_UI_ENABLED=true
COMMUNICATIONS_UI_PREFIX=communications
COMMUNICATIONS_UI_VIEW=welcome
```

## Commandes utiles

Synchroniser le catalogue runtime :

```bash
php artisan notifications:sync
```

Preparer des donnees de demonstration et declencher un envoi :

```bash
php artisan communications:test-send
php artisan communications:simulate-payment-reminder payment-reminder --email=alex@example.test --name="Alex" --due=2026-03-31
```

## Tests

```bash
php artisan test
npm run build
```

Dans ce depot, ces commandes valident l'application locale de demonstration qui sert de banc d'integration pour le package.

## Fichiers importants

- `src/Contracts/NotificationManagerInterface.php`
- `src/Services/NotificationManager.php`
- `src/Services/NotificationTemplateResolver.php`
- `src/Models/NotificationEvent.php`
- `src/Console/Commands/SyncNotificationEventsCommand.php`
- `src/Services/CommunicationService.php`
- `src/CommunicationServiceProvider.php`
- `config/communications.php`
