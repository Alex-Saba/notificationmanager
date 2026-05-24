# Diagrammes - ACL Communications

Ce document décrit le flux actuel du package `acl/notification-manager`.

## 1. Vue D'ensemble

```mermaid
flowchart TD
    A["Projet principal"] --> B["Événement applicatif ou dispatch direct"]
    B --> C["CommunicationService / NotificationManager"]
    C --> D["notification_events"]
    C --> E["NotificationTemplateResolver"]
    E --> F["Options runtime"]
    E --> G["communication_templates"]
    E --> H["config events fallback"]
    C --> I["TemplateRenderer"]
    I --> J["Communication log"]
    J --> K{"Canal en queue ?"}
    K -->|oui| L["SendCommunicationJob"]
    K -->|non| M["CommunicationDeliveryService"]
    L --> M
    M --> N["Channel driver"]
    N --> O["NotificationSent / NotificationFailed"]
    C --> P["CommunicationOrchestrated"]
```

## 2. Séquence De Traitement

```mermaid
sequenceDiagram
    participant Host as Projet principal
    participant Manager as NotificationManager
    participant Événements as notification_events
    participant Templates as NotificationTemplateResolver
    participant Renderer as TemplateRenderer
    participant Log as communications
    participant Queue as Queue Laravel
    participant Delivery as CommunicationDeliveryService
    participant Channel as Channel driver

    Host->>Manager: dispatch(event_key, payload, options)
    Manager->>Événements: find active event_key
    Événements-->>Manager: payload_schema
    Manager->>Manager: validate payload + parse channel
    Manager->>Templates: resolve(event_key, options, tenant_id)
    Templates-->>Manager: template content + subject
    Manager->>Renderer: render(template, payload)
    Renderer-->>Manager: rendered HTML
    Manager->>Log: create communication(status=pending)
    alt channel queued
        Manager->>Log: status=queued
        Manager->>Queue: dispatch SendCommunicationJob
        Queue->>Delivery: handle job
    else synchronous
        Manager->>Delivery: send(communication, channel, payload)
    end
    Delivery->>Channel: send(channel, payload)
    Channel-->>Delivery: status
    Delivery->>Log: update status, attempts, timestamps
```

## 3. Pipeline Interne

```mermaid
flowchart TD
    A["event_key + payload"] --> B["Validation runtime"]
    B --> C["Résolution template"]
    C --> D["Rendu HTML"]
    D --> E["Création communication"]
    E --> F["Queue ou envoi direct"]
    F --> G["Driver canal"]
    G --> H["Mise à jour status"]
    H --> I["Événements de sortie"]
```

## 4. Canal Mail

```mermaid
flowchart TD
    A["CommunicationDeliveryService"] --> B["MailChannel"]
    B --> C["CommunicationMail"]
    C --> D["Laravel Mail"]
    D --> E["Provider email"]
    B --> F["response status"]
    F --> G["communication.status = sent/failed"]
    G --> H["NotificationSent / NotificationFailed"]
```

## 5. UI Optionnelle

```mermaid
flowchart TD
    A["COMMUNICATIONS_UI_ENABLED=true"] --> B["routes communications-ui"]
    B --> C["/communications/templates"]
    B --> D["/communications/notifications"]
    C --> E["GET api/templates"]
    D --> F["API notifications démo"]
    E --> G["Vue TemplatesApp lecture seule"]
    F --> H["Vue NotificationsDemoApp"]
```

## 6. Séparation Des Responsabilités

```mermaid
flowchart LR
    subgraph Host["Projet principal"]
        A["Événements métier"]
        B["config events"]
        C["Templates seed/import si besoin"]
        D["Exposition finale utilisateur"]
    end

    subgraph Package["Package communications"]
        E["Catalogue runtime"]
        F["Résolution template"]
        G["Rendering"]
        H["Drivers"]
        I["Logs techniques"]
    end

    A --> E
    B --> E
    C --> F
    E --> F
    F --> G
    G --> H
    H --> I
    I --> D
```

## 7. Modèle De Données Simplifié

```mermaid
erDiagram
    NOTIFICATION_EVENT ||--o{ COMMUNICATION : "drives"
    COMMUNICATION_TEMPLATE ||--o{ COMMUNICATION : "used by"

    NOTIFICATION_EVENT {
        int id
        string key
        string label
        json payload_schema
        boolean is_active
    }

    COMMUNICATION_TEMPLATE {
        int id
        string key
        string event_key
        int tenant_id
        string subject
        text content
        boolean active
    }

    COMMUNICATION {
        int id
        string correlation_id
        string event_key
        int notification_event_id
        int template_id
        string channel
        string status
        string recipient_address
        int attempts
        json payload
        text rendered_content
        json meta
    }
```
