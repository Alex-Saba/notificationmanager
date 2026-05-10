# TODO - Package Communications

## Etat Actuel

- [x] Package Laravel chargeable par ServiceProvider
- [x] Configuration publiable
- [x] Migrations `notification_events`, `communication_templates`, `communication_rules`, `communications`
- [x] Catalogue runtime synchronise depuis `config('events')`
- [x] Dispatch par `NotificationManagerInterface`
- [x] Validation dynamique du payload
- [x] Resolution de template par priorite `options -> DB -> config`
- [x] Rendu HTML via `TemplateRendererInterface`
- [x] Canal derive du troisieme segment de `event_key`
- [x] Envoi mail via `MailChannel`
- [x] Queue Laravel optionnelle par canal
- [x] Journalisation dans `communications`
- [x] Events de sortie `NotificationSent`, `NotificationFailed`, `CommunicationOrchestrated`
- [x] UI optionnelle de consultation templates
- [x] UI/API de demonstration pour notifications in-app

## Nettoyages Restants

- [ ] Decider si la table `communication_rules` reste dans la V1 ou devient une migration future
- [ ] Decider si les routes/UI de demonstration doivent faire partie du package distribue
- [ ] Nettoyer les dependances inutiles de l'application locale de demonstration
- [ ] Verifier les assets generes avant publication Composer

## Priorite 1 - Stabilisation V1

- [x] Documenter l'installation
- [x] Documenter la convention `<module>.<action>.<channel>`
- [x] Documenter `notifications:sync`
- [x] Documenter le flow queue
- [x] Retirer le code mort de l'ancien editeur templates
- [ ] Ajouter un exemple d'integration Maivou complet
- [ ] Ajouter une section release GitHub/Packagist

## Priorite 2 - Templates

- [x] Supporter un template inline depuis `config('events')`
- [x] Supporter un template actif en base par `event_key`
- [x] Supporter un override runtime via `options['template']`
- [x] Supporter les tags simples et la notation point sur tableaux
- [ ] Ajouter une commande ou un seeder officiel pour creer des templates
- [ ] Ajouter la gestion de langue/locale
- [ ] Ajouter une politique claire pour les templates par tenant

## Priorite 3 - Canaux

- [x] Driver `MailChannel`
- [x] Driver `NullChannel` pour canaux non implementes
- [x] Queue configurable par canal
- [ ] Vrai canal `in_app`
- [ ] Vrai canal `sms`
- [ ] Pieces jointes email
- [ ] Documents generes et rattaches
- [ ] Fallback avance entre canaux

## Priorite 4 - Notifications In-App

- [x] Liste API
- [x] Creation de notification de demonstration
- [x] Filtre `unread`, `type`, `date`
- [x] Marquage lu / non lu
- [x] Suppression
- [x] Page Vue de demonstration
- [ ] Transformer la demo en canal runtime officiel
- [ ] Ajouter une politique d'autorisation pour l'application hote

## Priorite 5 - Robustesse

- [x] Tests feature et unitaires du flow principal
- [x] Build Vite valide
- [ ] Retries configures sur `SendCommunicationJob`
- [ ] Gestion explicite des echecs definitifs de job
- [ ] Idempotence fonctionnelle sur `idempotency_key`
- [ ] Observabilite plus detaillee des providers externes

## Cible Future

- [ ] Regles multi-canaux actives
- [ ] Preferences utilisateur par canal
- [ ] Envois differes
- [ ] Generation PDF
- [ ] Publication Packagist
- [ ] Documentation d'exploitation production
