# TODO - Package Communications

## Vision

Transformer le module actuel en package Laravel reutilisable capable de gerer :
- notifications in-app
- emails
- SMS
- generation de documents

## Cible fonctionnelle

- [ ] Le projet principal porte les evenements metier
- [ ] Le package ne scanne pas les events du projet principal
- [ ] Le package recoit une `event_key` explicite depuis le projet principal
- [ ] Chaque template est cree avec une regle liee
- [ ] La regle determine `event_key`, canaux, priorite, fallback, delai, activation
- [ ] Le `CommunicationService` orchestre template, canaux, envoi et logs

## Priorite 1 - Fondations du package

- [x] Renommer clairement le package autour d'une notion de `communications` ou `messaging`
- [x] Stabiliser l'arborescence `src/Contracts`, `src/Services`, `src/Channels`, `src/Documents`, `src/Templates`
- [x] Definir un `ServiceProvider` propre
- [x] Ajouter un fichier de configuration publiable
- [x] Ajouter une documentation d'installation dans le package
- [x] Verifier que le package peut etre charge dans une app Laravel externe

## Priorite 2 - Contrats de service

- [x] Creer `CommunicationServiceInterface`
- [x] Creer `ChannelDriverInterface`
- [x] Creer `DocumentGeneratorInterface`
- [x] Creer `TemplateRendererInterface`
- [x] Creer `RuleResolverInterface`
- [x] Creer `TemplateRepositoryInterface`
- [x] Binder les interfaces vers les implementations dans le container Laravel
- [ ] Ajouter des tests unitaires sur les contrats

## Priorite 3 - Modele de donnees

- [x] Ajouter une table `communication_templates`
- [x] Ajouter une table `communication_rules`
- [x] Lier chaque regle a un template via `template_id`
- [x] Stocker dans la regle : `event_key`, `channels`, `priority`, `fallback`, `delay`, `active`
- [x] Ajouter une table `communications` ou `message_logs`
- [x] Ajouter une table `generated_documents`
- [x] Ajouter les statuts d'envoi : `pending`, `sent`, `failed`, `read`
- [x] Ajouter les colonnes de canal : `database`, `mail`, `sms`, `document`
- [x] Ajouter une strategie de tracking et d'audit

## Priorite 4 - Templates + regles liees

- [x] Creer un formulaire ou workflow de creation de template
- [x] A la creation du template, creer aussi la regle liee
- [x] Permettre l'edition de la regle sans modifier le contenu du template
- [x] Autoriser plusieurs templates par langue
- [x] Definir une convention de `event_key` stable
- [x] Gerer l'activation / desactivation d'une regle
- [ ] Ajouter des tests feature sur le workflow `template + regle`

## Priorite 5 - CommunicationService et orchestration

- [x] Faire de `CommunicationService` le point d'entree unique du package
- [x] Recevoir `eventKey`, `recipient`, `data`
- [x] Resoudre la regle a partir de `eventKey`
- [x] Charger le template associe a la regle
- [x] Rendre le contenu final via `TemplateRenderer`
- [x] Resoudre les canaux actifs via la regle
- [x] Deleguer l'execution aux drivers de canaux
- [x] Journaliser succes, echec, fallback et statuts

## Priorite 6 - Notifications in-app

- [ ] Finaliser les endpoints CRUD minimaux
- [ ] Ajouter le filtre `unread`, `type`, `date`
- [ ] Ajouter le marquage lu / non lu
- [x] Ajouter une page de demo plus riche dans l'application hote
- [ ] Ajouter des tests feature complets

## Priorite 7 - Emails

- [ ] Creer un driver `MailChannel`
- [ ] Supporter les templates Blade ou HTML
- [ ] Gerer les pieces jointes
- [ ] Permettre l'envoi immediat ou en file d'attente
- [ ] Ajouter un systeme de fallback si l'envoi echoue

## Priorite 8 - SMS

- [ ] Creer un driver `SmsChannel`
- [ ] Prevoir une abstraction provider type Twilio / Vonage
- [ ] Ajouter la validation du numero
- [ ] Gerer les erreurs fournisseur
- [ ] Ajouter des tests avec faux provider

## Priorite 9 - Generation de documents

- [x] Creer un sous-module `Documents`
- [x] Ajouter `DocumentGeneratorInterface`
- [ ] Supporter la generation PDF depuis une vue Blade
- [ ] Sauvegarder les documents generes en local ou sur S3
- [ ] Permettre de joindre un document a un email
- [ ] Ajouter le telechargement securise des documents

## Priorite 10 - Integration avec le projet principal

- [ ] Definir un systeme de variables dynamiques `{{ client_name }}`, `{{ invoice_number }}`
- [x] Ajouter un moteur de rendu de templates
- [x] Ajouter un event catalog lookup explicite
- [x] Documenter une convention de nommage des evenements
- [x] Definir comment le projet principal transmet l'event au package
- [x] Documenter le pattern recommande : `event Laravel` -> `listener` -> `CommunicationService`
- [ ] Ajouter un exemple d'event metier `InvoicePaid`
- [x] Ajouter un exemple de listener qui appelle le package avec `event_key`
- [ ] Gerer les preferences utilisateur par canal
- [ ] Ajouter la planification differee des envois

## Priorite 11 - Jobs, queue et resilience

- [x] Passer les envois lourds dans des jobs Laravel
- [ ] Ajouter retries et gestion d'echec
- [x] Journaliser chaque tentative
- [x] Ajouter fallback entre canaux
- [ ] Ajouter des evenements `CommunicationSent`, `CommunicationFailed`

## Priorite 12 - Publication du package

- [ ] Nettoyer les dependances inutiles a l'application de demo
- [ ] Isoler le package pour une publication Git ou Packagist
- [ ] Rediger un `README` complet
- [ ] Ajouter des exemples d'integration dans une app hote
- [ ] Ajouter une check-list de release

## Commandes / livrables utiles

- [ ] Ecrire un plan d'architecture du package
- [ ] Ecrire un schema de donnees `templates / rules / logs / documents`
- [ ] Lister les interfaces et classes a creer
- [ ] Dessiner les tables SQL du package
- [ ] Ajouter des tests d'integration dans une application Laravel fictive
- [ ] Preparer une V1 minimale : `template + rule + database + mail + document PDF`

## Definition d'une V1 utile

- [x] 1 service principal d'orchestration
- [x] 1 table `communication_templates`
- [x] 1 table `communication_rules`
- [x] 1 workflow de creation `template + regle`
- [ ] 2 canaux minimum : `database` et `mail`
- [ ] 1 generation de document PDF
- [x] 1 systeme de templates
- [x] 1 historique des envois
- [ ] 1 documentation d'installation
