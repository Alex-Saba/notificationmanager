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

## Synchronisation runtime

Le runtime des evenements est centralise dans la table `notification_events`.

Commande de synchronisation :

```bash
php artisan notifications:sync
```

Cette commande lit `config('events')` puis met a jour la table `notification_events`.

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
6. envoi via le channel
7. journalisation dans `communications`

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
    ],
    'sms' => [
        'driver' => Acl\Communications\Channels\NullChannel::class,
    ],
    'in_app' => [
        'driver' => Acl\Communications\Channels\NullChannel::class,
    ],
],
```

## Resolution de template

La priorite de resolution est :
1. override runtime via `options`
2. template en base par `event_key` et `tenant_id`
3. fallback config via `config('events')`

Implementation : `src/Services/NotificationTemplateResolver.php`

## Integration par evenement applicatif

Le package supporte aussi un pont depuis un event Laravel du projet principal.

Exemple d'evenement hote local : `app/Events/RequestCreated.php`

```php
Event::listen(RequestCreated::class, NotificationListener::class);
event(new RequestCreated($user, 'REQ-2026-001'));
```

Dans ce cas :
- l'event applicatif fournit la cle et le payload
- `src/Services/CommunicationService.php` delegue ensuite a `NotificationManagerInterface`

## UI optionnelle

Le depot contient aussi une UI Vue de demonstration pour :
- gerer les templates
- gerer les regles liees
- visualiser des notifications in-app

Routes UI par defaut :
- `/communications/templates`
- `/communications/templates/create`
- `/communications/templates/{id}/edit`
- `/communications/notifications`

Le prefixe et les middlewares sont configurables dans `config/communications.php`.

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
