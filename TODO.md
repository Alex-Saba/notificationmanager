# TODO - Package Communications

## État Actuel

- [x] Package Laravel chargeable par ServiceProvider
- [x] Configuration publiable
- [x] Migrations `notification_events`, `communication_templates`, `communication_rules`, `communications`
- [x] Catalogue runtime synchronisé depuis `config('events')`
- [x] Dispatch par `NotificationManagerInterface`
- [x] Validation dynamique du payload
- [x] Résolution de template par priorité `options -> DB -> config`
- [x] Rendu HTML via `TemplateRendererInterface`
- [x] Canal dérivé du troisième segment de `event_key`
- [x] Envoi mail via `MailChannel`
- [x] Queue Laravel optionnelle par canal
- [x] Journalisation dans `communications`
- [x] Événements de sortie `NotificationSent`, `NotificationFailed`, `CommunicationOrchestrated`
- [x] UI optionnelle de consultation templates
- [x] UI/API de démonstration pour notifications in-app

## Nettoyages Restants

- [ ] Décider si la table `communication_rules` reste dans la V1 ou devient une migration future
- [ ] Décider si les routes/UI de démonstration doivent faire partie du package distribué
- [ ] Nettoyer les dépendances inutiles de l'application locale de démonstration
- [ ] Vérifier les assets générés avant publication Composer

## Priorité 1 - Stabilisation V1

- [x] Documenter l'installation
- [x] Documenter la convention `<module>.<action>.<channel>`
- [x] Documenter `notifications:sync`
- [x] Documenter le flow queue
- [x] Retirer le code mort de l'ancien éditeur templates
- [ ] Ajouter un exemple d'intégration Maivou complet
- [ ] Ajouter une section release GitHub/Packagist

## Priorité 2 - Templates

- [x] Supporter un template inline depuis `config('events')`
- [x] Supporter un template actif en base par `event_key`
- [x] Supporter un override runtime via `options['template']`
- [x] Supporter les tags simples et la notation point sur tableaux
- [ ] Ajouter une commande ou un seeder officiel pour créer des templates
- [ ] Ajouter la gestion de langue/locale
- [ ] Ajouter une politique claire pour les templates par tenant

## Priorité 3 - Canaux

- [x] Driver `MailChannel`
- [x] Driver `NullChannel` pour canaux non implémentés
- [x] Queue configurable par canal
- [ ] Vrai canal `in_app`
- [ ] Vrai canal `sms`
- [ ] Pièces jointes email
- [ ] Documents générés et rattachés
- [ ] Fallback avancé entre canaux

## Priorité 4 - Notifications In-App

- [x] Liste API
- [x] Création de notification de démonstration
- [x] Filtre `unread`, `type`, `date`
- [x] Marquage lu / non lu
- [x] Suppression
- [x] Page Vue de démonstration
- [ ] Transformer la démo en canal runtime officiel
- [ ] Ajouter une politique d'autorisation pour l'application hôte

## Priorité 5 - Robustesse

- [x] Tests feature et unitaires du flow principal
- [x] Build Vite validé
- [ ] Retries configurés sur `SendCommunicationJob`
- [ ] Gestion explicite des échecs définitifs de job
- [ ] Idempotence fonctionnelle sur `idempotency_key`
- [ ] Observabilité plus détaillée des providers externes

## Cible Future

- [ ] Règles multi-canaux actives
- [ ] Préférences utilisateur par canal
- [ ] Envois différés
- [ ] Génération PDF
- [ ] Publication Packagist
- [ ] Documentation d'exploitation production
