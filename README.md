# NotificationManager

`acl/notification-manager` fournit un package Laravel de notifications, aligné sur une architecture `NotificationManager`.

Le module permet de :
- déclencher une notification via une clé unique
- valider dynamiquement le payload
- router vers un channel à partir de la clé
- résoudre un template par priorité `options -> DB -> config`
- travailler sur un catalogue runtime centralisé en base
- rester agnostique des modules métier

## Structure du dépôt

Le cœur distribuable du package est porté par :
- `src/`
- `config/communications.php`
- `database/migrations/communications`
- `routes/communications-ui.php`

Le dépôt contient aussi une application Laravel locale de démonstration pour développer et tester le package.
Cette application n'est pas destinée à être publiée comme partie du package Composer.

## Objectif

Le projet principal :
- déclare ses événements dans ses modules via `config/events.php`
- fusionne ces déclarations dans `config('events')`
- déclenche les notifications par clé

Le package :
- synchronise ces déclarations vers la base
- utilise la table `notification_events` comme source runtime
- valide le payload
- résout le template
- envoie via le channel correspondant

## Convention de clé

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

## Procédure d'intégration dans le projet principal

Ordre recommandé :
1. installer le package avec Composer
2. publier `config/communications.php`
3. publier les migrations du package
4. exécuter `php artisan migrate`
5. déclarer les `event_key` métier dans `config/events.php`
6. synchroniser le catalogue runtime avec `php artisan notifications:sync`
7. envoyer un premier message de test via `NotificationManagerInterface`

Prérequis côté projet hôte :
- Laravel 12
- PHP 8.2 minimum
- une configuration mail exploitable si le channel `email` est utilisé
- un fichier `config/events.php` ou un mécanisme équivalent qui alimente `config('events')`

## Configuration minimale du projet hôte

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
        'template' => '<p>Bonjour {{ $requester_name }}, votre demande {{ $request_number }} est enregistrée.</p>',
        'subject' => 'Nouvelle demande',
    ],
];
```

Dans cet exemple, la clé `template` correspond à un fallback de démarrage.
Elle permet au package de rendre un contenu minimal même si aucun template n'a encore été créé en base.
Si un template actif existe dans la table `communication_templates` pour la même `event_key`, il devient prioritaire sur ce fallback de configuration.

Exemple minimal de configuration mail dans le projet hôte :

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

## Déclaration des événements

Les événements ne sont pas déclares dans le package comme source métier finale.
Ils sont déclares dans le projet principal, puis lus par le package via `config('events')`.

Exemple de fichier hôte `config/events.php` :

```php
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
];
```

Le package suppose que les `events.php` des modules du projet principal sont fusionnés automatiquement par l'application hôte.

La clé `template` dans `config/events.php` sert surtout de fallback de configuration pour un premier envoi ou pour un fonctionnement sans template base.

## Synchronisation runtime

Le runtime des événements est centralisé dans la table `notification_events`.

Commande de synchronisation :

```bash
php artisan notifications:sync
```

Cette commande lit `config('events')` puis met à jour la table `notification_events`.

## Premier envoi manuel

Une fois les événements synchronisés, un premier envoi peut être testé directement :

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

Séquence minimale de vérification :

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

Le package utilise la table des templates pour la résolution runtime :
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

## Flow d'exécution

`dispatch()` fait :
1. lecture de l'événement dans `notification_events`
2. validation du payload via `payload_schema`
3. parsing de la clé
4. résolution du template
5. rendu final
6. journalisation dans `communications`
7. publication dans une queue si le channel est configuré avec `queue => true`
8. exécution du driver par `SendCommunicationJob` ou envoi direct si la queue est désactivée

Quand un canal est mis en queue, le package ne contacte pas le fournisseur email/SMS pendant l'appel initial.
Il prépare le message, crée une ligne `communications` avec le statut `queued`, puis publie un job Laravel.
Le worker du projet hôte consomme ensuite la queue et appelle le driver réel.

## Validation du payload

Le payload est validé dynamiquement à partir du schéma stocké dans `notification_events.payload_schema`.

Exemple :

```php
[
    'request_number' => 'required|string',
    'user_email' => 'required|email',
]
```

## Routing channel

Le channel est dérivé du troisième segment de la clé.

Exemple :
- `request.created.email` -> channel `email`
- `billing.reminder.in_app` -> channel `in_app`

Le manager route ensuite vers le driver déclare dans `config/communications.php`.

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

Pour traiter les messages asynchrones, le projet hôte lance un worker Laravel sur la queue configurée :

```bash
php artisan queue:work --queue=notifications.email
```

Un appel peut forcer le comportément synchrone ou asynchrone avec l'option `queue` :

```php
app(NotificationManagerInterface::class)->dispatch($eventKey, $payload, [
    'queue' => false,
]);
```

## Résolution de template

La priorité de résolution est :
1. override runtime via `options`
2. template actif en base par `event_key` et `tenant_id`
3. fallback config via `config('events')`

Implementation : `src/Services/NotificationTemplateResolver.php`

En pratique :
- si un template existe en base, c'est lui qui est rendu
- sinon, le package peut utiliser `config/events.php['template']` comme contenu de secours

## Intégration par événement applicatif

Le package supporté aussi un pont depuis un event Laravel du projet principal.

Exemple d'événement hôte local : `app/Événements/RequestCreated.php`

Exemple minimal d'événement hôte :

```php
<?php

namespace App\Événements;

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

Enregistrement du listener dans le projet hôte :

```php
use Acl\Communications\Listeners\NotificationListener;
use App\Événements\RequestCreated;
use Illuminate\Support\Facades\Event;

Event::listen(RequestCreated::class, NotificationListener::class);
event(new RequestCreated('REQ-2026-001', 'Alex', 'alex@example.test', 42));
```

Dans ce cas :
- l'event applicatif fournit la clé et le payload
- `src/Services/CommunicationService.php` délègue ensuite à `NotificationManagerInterface`

## Checklist d'installation rapide

```bash
composer require acl/notification-manager
php artisan vendor:publish --tag=communications-config
php artisan vendor:publish --tag=communications-migrations
php artisan migrate
php artisan notifications:sync
```

Puis vérifier :
- la table `notification_events` contient les clés attendues
- la configuration mail du projet hôte est correcte
- un appel à `NotificationManagerInterface::dispatch()` crée une ligne dans `communications`
- le channel attendu est bien configuré dans `config/communications.php`

## UI optionnelle

Le dépôt contient aussi une UI Vue de démonstration pour :
- consulter les templates
- visualiser des notifications in-app

Dans un projet principal, cette UI est désactivée par défaut pour éviter les collisions de routes et de vues.
Il faut l'activer explicitement si elle est voulue.

Routes UI par défaut :
- `/communications/templates`
- `/communications/notifications`

Routes API UI par défaut :
- `GET /communications/api/templates`
- `GET /communications/api/templates/{id}`
- `GET /communications/api/notifications`
- `POST /communications/api/notifications`
- `GET /communications/api/notifications/{id}`
- `PATCH /communications/api/notifications/{id}/read`
- `PATCH /communications/api/notifications/{id}/unread`
- `DELETE /communications/api/notifications/{id}`

L'UI templates est volontairement en lecture seule dans l'état actuel du package.
La création ou modification de templates doit être faite par seed, migration, import ou code applicatif hôte.

Le préfixe et les middlewares sont configurables dans `config/communications.php`.

Activation explicite dans le projet hôte :

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

Préparer des données de démonstration et déclencher un envoi :

```bash
php artisan communications:test-send
php artisan communications:simulate-payment-reminder payment-reminder --email=alex@example.test --name="Alex" --due=2026-03-31
```

## Tests

```bash
php artisan test
npm run build
```

Dans ce dépôt, ces commandes valident l'application locale de démonstration qui sert de banc d'intégration pour le package.

## Fichiers importants

- `src/Contracts/NotificationManagerInterface.php`
- `src/Services/NotificationManager.php`
- `src/Services/NotificationTemplateResolver.php`
- `src/Models/NotificationEvent.php`
- `src/Console/Commands/SyncNotificationÉvénementsCommand.php`
- `src/Services/CommunicationService.php`
- `src/CommunicationServiceProvider.php`
- `config/communications.php`
