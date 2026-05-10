# Architecture - Package Communications

## Objectif

Définir le fonctionnement du package une fois appelé par le projet principal.
Ce document distingue l'état actuel de la cible fonctionnelle plus large.

Le package ne porte pas les événements métier du projet principal.
Le projet principal déclenche un event métier ou appelle explicitement le package avec une `event_key`.

## Flux global

Etat actuel :

```text
Projet principal
-> ApplicationEventConsumer
-> EventCatalogLookup
-> CommunicationService
-> NotificationManager
-> NotificationEvent runtime
-> NotificationTemplateResolver
-> TemplateRenderer
-> Channel driver
-> Queue optionnelle
-> Logs
-> Événements de sortie
```

Cible future avec règles multi-canaux :

```text
Projet principal
-> ApplicationEventConsumer
-> EventCatalogLookup
-> CommunicationService
-> Rules engine future
-> Template
-> TemplateRenderer
-> ChannelResolver
-> Channels
-> Fallback
-> Logs
```

## Étapes explicites

Le package doit distinguer clairement :

- `incoming_event` : l'événement entrant, son recipient et ses données
- `resolved_template` : le template retenu depuis les options, la base ou la config
- `produced_rendering` : le rendu HTML produit et le document éventuel
- `emitted_result` : le résultat émis, les deliveries, le fallback et le statut final

Structure de résultat normalisée :

- `event_id` : identifiant unique du traitement
- `context` : informations d'entrée et de résolution
- `payload` : rendu produit et résultat émis

Événements de sortie attendus :

- `notification.sent.mail`
- `notification.failed.mail`
- `notification.document.generated`
- `communication.orchestrated`

Séparation plus stricte entre orchestration et exposition :

- `CommunicationService` orchestre uniquement la résolution, le rendu, l'envoi et les logs
- l'événement `communication.orchestrated` matérialise la fin de cette orchestration
- une couche `CommunicationExposureConsumer` consomme cet événement pour préparer l'exposition côté application hôte
- l'application hôte reste responsable de l'affichage, du téléchargement ou de la publication finale

## Politique de réaction au résultat

Convention recommandée :

- le package émet les événements de sortie
- une couche `CommunicationResultConsumer` consomme ces événements
- la politique par défaut considère que la réaction finale appartient à l'application hôte

En pratique :

- le package produit, journalise et émet
- l'application hôte décide ensuite d'afficher, archiver, télécharger, notifier un back-office ou déclencher une action métier

## Convention de nommage des événements

Convention recommandée :

- les événements applicatifs utilisent le format `domaine.action`
- une variante de canal peut utiliser `domaine.action.canal`
- les événements de sortie du package utilisent `notification.<statut>.<canal>`

Exemples :

- `request.created`
- `request.created.mail`
- `notification.sent.mail`
- `notification.failed.mail`
- `notification.document.generated`

Règles de nommage :

- utiliser uniquement des minuscules
- séparer les segments par des points
- garder un verbe d'action stable en fin de clé métier
- réserver le préfixe `notification.` aux événements émis par le package
- réserver les préfixes métier comme `request.`, `invoice.`, `user.` aux événements entrants du projet principal

## Checklist opérationnelle

### 1. Réception de la demande

- [ ] Recevoir une `event_key`
- [ ] Recevoir le `recipient`
- [ ] Recevoir les `data` métier
- [ ] Recevoir le contexte optionnel : langue, priorité forcée, canal forcé, délai
- [ ] Résoudre l'événement applicatif via un catalogue explicite

Exemple :

```php
event(new InvoicePaid($user, [
    'invoice_number' => 'FAC-2026-001',
    'amount' => 1200,
]));
```

Puis :

```text
InvoicePaid
-> ApplicationEventConsumer
-> EventCatalogLookup
-> NotificationListener
-> CommunicationService::trigger(event)
-> CommunicationService::send(event_key, recipient, data)
```

### 2. Résolution runtime actuelle

- [x] Chercher l'événement actif dans `notification_events`
- [x] Valider le payload avec `payload_schema`
- [x] Parser le canal depuis le troisième segment de `event_key`
- [x] Résoudre le template actif par `event_key` et `tenant_id`
- [x] Utiliser `config('events')[event_key]['template']` comme fallback

Sortie attendue :

```text
event_key = invoice.paid.email
template_id = 12
channel = email
priority = 100
queue = notifications.email
```

### 3. Chargement du template

- [x] Charger le template associé à la `event_key`
- [ ] Charger la bonne variante de langue si nécessaire
- [x] Vérifier que le template est actif
- [x] Vérifier que les champs requis existent : sujet et contenu

### 4. Rendu du contenu

- [ ] Injecter les variables métier dans le template
- [ ] Générer le sujet final
- [ ] Générer le corps final
- [ ] Générer les meta données nécessaires selon le canal
- [ ] Vérifier les variables manquantes

Exemple :

```text
Bonjour Dupont, votre facture FAC-2026-001 a bien été payée.
```

### 5. Résolution des canaux

- [x] Lire le canal depuis la clé `<module>.<action>.<channel>`
- [x] Vérifier que le driver du canal est configuré
- [x] Exclure un canal si les prérequis sont absents
  Exemple : pas d'email si aucune adresse email
- [ ] Tenir compte des préférences utilisateur
- [ ] Tenir compte de la priorité
- [ ] Tenir compte du délai ou de l'envoi différé

Sortie attendue :

```text
channel = mail
```

### 6. Exécution des canaux

- [ ] Appeler `DatabaseNotificationChannel` pour les notifications in-app
- [ ] Appeler `MailChannel` pour les emails
- [ ] Appeler `SmsChannel` pour les SMS
- [ ] Appeler `DocumentGenerator` pour les documents
- [ ] Gérer l'ordre d'exécution si nécessaire
- [x] Passer par des jobs si le canal doit être asynchrone

### 7. Gestion des documents

- [ ] Générer un document si le canal `document` est actif
- [ ] Sauvegarder le document sur le stockage configuré
- [ ] Retourner la référence du document généré
- [ ] Permettre de joindre le document à un email si besoin

### 8. Fallback

- [ ] Détecter l'échec d'un canal principal
- [ ] Appliquer une stratégie de fallback entre canaux
- [ ] Rejouer l'envoi sur un autre canal si nécessaire
- [ ] Journaliser le fallback appliqué

Exemple :

```text
sms échec -> fallback email
email échec -> fallback database
```

### 9. Logs et historique

- [ ] Enregistrer chaque tentative d'envoi
- [ ] Enregistrer le canal utilisé
- [ ] Enregistrer le statut : `pending`, `sent`, `failed`, `read`
- [ ] Enregistrer les erreurs fournisseur
- [ ] Enregistrer les identifiants externes si disponibles
- [ ] Enregistrer les documents générés

### 10. Retour final

- [ ] Retourner un résultat unifié
- [ ] Exposer les canaux executes
- [ ] Exposer les succès
- [ ] Exposer les échecs
- [ ] Exposer les fallbacks appliqués

## Responsabilités

### Projet principal

- Porte les événements métier
- Declénche un event Laravel ou appelle le package
- Fournit `event_key`, destinataire et données
- Expose toujours le résultat final à l'utilisateur
- Décide comment afficher, rattacher, télécharger, archiver ou distribuer le rendu produit par le package

### Package

- Résout l'événement runtime
- Charge le template actif ou le fallback config
- Rend le contenu
- Choisit le canal depuis la clé d'événement
- Exécute les envois
- Gere queue, résultat et logs
- Ne porte pas l'exposition finale à l'utilisateur

## Exposition à l'utilisateur final

Principe recommande :

- Le package produit un rendu, un résultat de canal ou un document
- Le package peut stocker techniquement ses sorties
- Le projet principal reste toujours responsable de l'exposition finale à l'utilisateur

Cela permet de garder :

- un package réutilisable
- une UX gérée par le projet principal
- un meilleur contrôle sur la sécurité, le stockage et les permissions

## Tableau des canaux

| Canal | Qui rend le contenu | Qui expose à l'utilisateur final | Qui stocke |
|---|---|---|---|
| `mail` | Le package via `TemplateRenderer` | Le projet principal | Le package, le projet principal ou le provider email selon l'implementation |
| `document` | Le package via `TemplateRenderer` + `DocumentGenerator` | Le projet principal | Le package ou le stockage configuré par le projet principal |
| `database` / `in-app` | Le package via `TemplateRenderer` | Le projet principal | Le package ou la base de données du projet principal |

Règle de séparation recommandée :

- Le package peut stocker les sorties techniques
- Le projet principal garde la responsabilité de l'accès, de l'affichage et de l'usage fonctionnel

## Tables cibles

### `communication_templates`

- [ ] `id`
- [ ] `code`
- [ ] `name`
- [ ] `subject`
- [ ] `body`
- [ ] `language`
- [ ] `type`
- [ ] `active`

### `communication_rules`

Table conservée pour les évolutions de règles multi-canaux, mais non utilisée par le flux runtime principal actuel.

- [ ] `id`
- [ ] `event_key`
- [ ] `template_id`
- [ ] `channels`
- [ ] `priority`
- [ ] `fallback`
- [ ] `delay`
- [ ] `active`

### `communication_logs`

- [ ] `id`
- [ ] `event_key`
- [ ] `template_id`
- [ ] `channel`
- [ ] `status`
- [ ] `provider`
- [ ] `error_message`
- [ ] `external_id`
- [ ] `recipient_type`
- [ ] `recipient_id`

## Interfaces cibles

```php
interface CommunicationServiceInterface
{
    public function trigger(CommunicationEventInterface $event): array;

    public function send(string $eventKey, mixed $recipient, array $data = []): array;
}

interface TemplateRendererInterface
{
    public function render(mixed $template, array $data = []): array;
}

interface ChannelDriverInterface
{
    public function send(mixed $recipient, array $payload): mixed;
}
```

## V1 recommandée

- [ ] Trigger par `event_key`
- [x] Catalogue runtime `notification_events`
- [x] Template actif par `event_key`
- [x] Canal dérivé de la clé d'événement
- [x] 1 historique des envois
- [x] 1 exemple d'intégration avec un event du projet principal
- [ ] Vrai canal in-app
- [ ] Règles multi-canaux et fallback avancé
