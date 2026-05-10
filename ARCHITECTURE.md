# Architecture - Package Communications

## Objectif

Definir le fonctionnement du package une fois appele par le projet principal.
Ce document distingue l'etat actuel de la cible fonctionnelle plus large.

Le package ne porte pas les evenements metier du projet principal.
Le projet principal declenche un event metier ou appelle explicitement le package avec une `event_key`.

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
-> Events de sortie
```

Cible future avec regles multi-canaux :

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

## Etapes explicites

Le package doit distinguer clairement :

- `incoming_event` : l'evenement entrant, son recipient et ses donnees
- `resolved_template` : le template retenu depuis les options, la base ou la config
- `produced_rendering` : le rendu HTML produit et le document eventuel
- `emitted_result` : le resultat emis, les deliveries, le fallback et le statut final

Structure de resultat normalisee :

- `event_id` : identifiant unique du traitement
- `context` : informations d'entree et de resolution
- `payload` : rendu produit et resultat emis

Evenements de sortie attendus :

- `notification.sent.mail`
- `notification.failed.mail`
- `notification.document.generated`
- `communication.orchestrated`

Separation plus stricte entre orchestration et exposition :

- `CommunicationService` orchestre uniquement la resolution, le rendu, l'envoi et les logs
- l'evenement `communication.orchestrated` materialise la fin de cette orchestration
- une couche `CommunicationExposureConsumer` consomme cet evenement pour preparer l'exposition cote application hote
- l'application hote reste responsable de l'affichage, du telechargement ou de la publication finale

## Politique de reaction au resultat

Convention recommandee :

- le package emet les evenements de sortie
- une couche `CommunicationResultConsumer` consomme ces evenements
- la politique par defaut considere que la reaction finale appartient a l'application hote

En pratique :

- le package produit, journalise et emet
- l'application hote decide ensuite d'afficher, archiver, telecharger, notifier un back-office ou declencher une action metier

## Convention de nommage des evenements

Convention recommandee :

- les evenements applicatifs utilisent le format `domaine.action`
- une variante de canal peut utiliser `domaine.action.canal`
- les evenements de sortie du package utilisent `notification.<statut>.<canal>`

Exemples :

- `request.created`
- `request.created.mail`
- `notification.sent.mail`
- `notification.failed.mail`
- `notification.document.generated`

Regles de nommage :

- utiliser uniquement des minuscules
- separer les segments par des points
- garder un verbe d'action stable en fin de cle metier
- reserver le prefixe `notification.` aux evenements emis par le package
- reserver les prefixes metier comme `request.`, `invoice.`, `user.` aux evenements entrants du projet principal

## Checklist operationnelle

### 1. Reception de la demande

- [ ] Recevoir une `event_key`
- [ ] Recevoir le `recipient`
- [ ] Recevoir les `data` metier
- [ ] Recevoir le contexte optionnel : langue, priorite forcee, canal force, delai
- [ ] Resoudre l'evenement applicatif via un catalogue explicite

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

### 2. Resolution runtime actuelle

- [x] Chercher l'evenement actif dans `notification_events`
- [x] Valider le payload avec `payload_schema`
- [x] Parser le canal depuis le troisieme segment de `event_key`
- [x] Resoudre le template actif par `event_key` et `tenant_id`
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

- [x] Charger le template associe a la `event_key`
- [ ] Charger la bonne variante de langue si necessaire
- [x] Verifier que le template est actif
- [x] Verifier que les champs requis existent : sujet et contenu

### 4. Rendu du contenu

- [ ] Injecter les variables metier dans le template
- [ ] Generer le sujet final
- [ ] Generer le corps final
- [ ] Generer les meta donnees necessaires selon le canal
- [ ] Verifier les variables manquantes

Exemple :

```text
Bonjour Dupont, votre facture FAC-2026-001 a bien ete payee.
```

### 5. Resolution des canaux

- [x] Lire le canal depuis la cle `<module>.<action>.<channel>`
- [x] Verifier que le driver du canal est configure
- [x] Exclure un canal si les prerequis sont absents
  Exemple : pas d'email si aucune adresse email
- [ ] Tenir compte des preferences utilisateur
- [ ] Tenir compte de la priorite
- [ ] Tenir compte du delai ou de l'envoi differe

Sortie attendue :

```text
channel = mail
```

### 6. Execution des canaux

- [ ] Appeler `DatabaseNotificationChannel` pour les notifications in-app
- [ ] Appeler `MailChannel` pour les emails
- [ ] Appeler `SmsChannel` pour les SMS
- [ ] Appeler `DocumentGenerator` pour les documents
- [ ] Gerer l'ordre d'execution si necessaire
- [x] Passer par des jobs si le canal doit etre asynchrone

### 7. Gestion des documents

- [ ] Generer un document si le canal `document` est actif
- [ ] Sauvegarder le document sur le stockage configure
- [ ] Retourner la reference du document genere
- [ ] Permettre de joindre le document a un email si besoin

### 8. Fallback

- [ ] Detecter l'echec d'un canal principal
- [ ] Appliquer une strategie de fallback entre canaux
- [ ] Rejouer l'envoi sur un autre canal si necessaire
- [ ] Journaliser le fallback applique

Exemple :

```text
sms echec -> fallback email
email echec -> fallback database
```

### 9. Logs et historique

- [ ] Enregistrer chaque tentative d'envoi
- [ ] Enregistrer le canal utilise
- [ ] Enregistrer le statut : `pending`, `sent`, `failed`, `read`
- [ ] Enregistrer les erreurs fournisseur
- [ ] Enregistrer les identifiants externes si disponibles
- [ ] Enregistrer les documents generes

### 10. Retour final

- [ ] Retourner un resultat unifie
- [ ] Exposer les canaux executes
- [ ] Exposer les succes
- [ ] Exposer les echecs
- [ ] Exposer les fallbacks appliques

## Responsabilites

### Projet principal

- Porte les evenements metier
- Declenche un event Laravel ou appelle le package
- Fournit `event_key`, destinataire et donnees
- Expose toujours le resultat final a l'utilisateur
- Decide comment afficher, rattacher, telecharger, archiver ou distribuer le rendu produit par le package

### Package

- Resout l'evenement runtime
- Charge le template actif ou le fallback config
- Rend le contenu
- Choisit le canal depuis la cle d'evenement
- Execute les envois
- Gere queue, resultat et logs
- Ne porte pas l'exposition finale a l'utilisateur

## Exposition a l'utilisateur final

Principe recommande :

- Le package produit un rendu, un resultat de canal ou un document
- Le package peut stocker techniquement ses sorties
- Le projet principal reste toujours responsable de l'exposition finale a l'utilisateur

Cela permet de garder :

- un package reutilisable
- une UX geree par le projet principal
- un meilleur controle sur la securite, le stockage et les permissions

## Tableau des canaux

| Canal | Qui rend le contenu | Qui expose a l'utilisateur final | Qui stocke |
|---|---|---|---|
| `mail` | Le package via `TemplateRenderer` | Le projet principal | Le package, le projet principal ou le provider email selon l'implementation |
| `document` | Le package via `TemplateRenderer` + `DocumentGenerator` | Le projet principal | Le package ou le stockage configure par le projet principal |
| `database` / `in-app` | Le package via `TemplateRenderer` | Le projet principal | Le package ou la base de donnees du projet principal |

Regle de separation recommandee :

- Le package peut stocker les sorties techniques
- Le projet principal garde la responsabilite de l'acces, de l'affichage et de l'usage fonctionnel

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

Table conservee pour les evolutions de regles multi-canaux, mais non utilisee par le flux runtime principal actuel.

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

## V1 recommandee

- [ ] Trigger par `event_key`
- [x] Catalogue runtime `notification_events`
- [x] Template actif par `event_key`
- [x] Canal derive de la cle d'evenement
- [x] 1 historique des envois
- [x] 1 exemple d'integration avec un event du projet principal
- [ ] Vrai canal in-app
- [ ] Regles multi-canaux et fallback avance
