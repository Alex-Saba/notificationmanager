# Diagrammes - ACL Communications

Ce document regroupe les principaux diagrammes Mermaid de l'application/package `notifications`.

## 1. Vue d'ensemble

```mermaid
flowchart TD
    A["Projet principal"] --> B["Event applicatif
    ex: RequestCreated"]
    B --> C["NotificationListener"]
    C --> D["LaravelApplicationEventConsumer"]
    D --> E["CommunicationService"]
    E --> F["ApplicationEventCatalog"]
    E --> G["RuleResolver"]
    E --> H["TemplateRepository"]
    E --> I["TemplateRenderer"]
    E --> J["MailChannel"]
    E --> K["Communication log"]
    E --> L["Events de sortie"]

    L --> M["NotificationSent / NotificationFailed"]
    L --> N["CommunicationOrchestrated"]
    M --> O["CommunicationOutcomeListener"]
    N --> P["CommunicationExposureListener"]
    O --> Q["HostReactionRequested"]
    P --> R["HostExposureRequested"]
```

## 2. Sequence de traitement d'un event entrant

```mermaid
sequenceDiagram
    participant Host as Projet principal
    participant Event as RequestCreated
    participant Listener as NotificationListener
    participant Consumer as LaravelApplicationEventConsumer
    participant Service as CommunicationService
    participant Catalog as ApplicationEventCatalog
    participant Rules as RuleResolver
    participant Templates as TemplateRepository
    participant Renderer as TemplateRenderer
    participant Mail as MailChannel

    Host->>Event: event(RequestCreated)
    Event->>Listener: handle(event)
    Listener->>Consumer: consume(event)
    Consumer->>Service: trigger(event)
    Service->>Catalog: lookup(event)
    Catalog-->>Service: event_key + recipient + data
    Service->>Rules: resolve(event_key)
    Rules-->>Service: rule
    Service->>Templates: find(template)
    Templates-->>Service: template
    Service->>Renderer: render(template, data)
    Renderer-->>Service: rendered content
    Service->>Mail: send(mail, payload)
    Mail-->>Service: status=sent
    Service-->>Host: result array
```

## 3. Pipeline interne du package

```mermaid
flowchart TD
    A["Event recu"] --> B["Catalog lookup"]
    B --> C["Resolution de la regle"]
    C --> D["Chargement du template"]
    D --> E["Rendu du template"]
    E --> F["Dispatch vers le canal"]
    F --> G["Log technique"]
    G --> H["Emission des events de sortie"]
```

## 4. Canal mail

```mermaid
flowchart TD
    A["CommunicationService"] --> B["TemplateRenderer"]
    B --> C["Contenu HTML rendu"]
    C --> D["MailChannel"]
    D --> E["CommunicationMail"]
    E --> F["Laravel Mail"]
    F --> G["Provider email"]
    D --> H["communication.status = sent/failed"]
    H --> I["NotificationSent / NotificationFailed"]
    I --> J["HostReactionRequested"]
```

## 5. Evenements de sortie du package

```mermaid
flowchart TD
    A["CommunicationService"] --> B["NotificationSent"]
    A --> C["NotificationFailed"]
    A --> D["NotificationDocumentGenerated"]
    A --> E["CommunicationOrchestrated"]

    B --> F["CommunicationOutcomeListener"]
    C --> F
    D --> F
    E --> G["CommunicationExposureListener"]

    F --> H["HostReactionRequested"]
    G --> I["HostExposureRequested"]
```

## 6. Separation des responsabilites

```mermaid
flowchart LR
    subgraph Host["Projet principal"]
        A["Events metier"]
        B["Ecoute des events host.*"]
        C["Exposition finale a l'utilisateur"]
    end

    subgraph Package["Package communications"]
        D["Catalog"]
        E["Rules"]
        F["Templates"]
        G["Rendering"]
        H["MailChannel"]
        I["Logs techniques"]
    end

    A --> D
    D --> E
    E --> F
    F --> G
    G --> H
    H --> I
    I --> B
    B --> C
```

## 7. Modele de donnees simplifie

```mermaid
erDiagram
    COMMUNICATION_TEMPLATE ||--o| COMMUNICATION_RULE : "has one"
    COMMUNICATION_TEMPLATE ||--o{ COMMUNICATION : "used by"
    COMMUNICATION_RULE ||--o{ COMMUNICATION : "drives"
    COMMUNICATION ||--o{ GENERATED_DOCUMENT : "produces"

    COMMUNICATION_TEMPLATE {
        int id
        string key
        string locale
        string subject
        text content
        boolean active
    }

    COMMUNICATION_RULE {
        int id
        int template_id
        string event_key
        json channels
        json fallback
        int priority
        int delay
        boolean active
    }

    COMMUNICATION {
        int id
        string correlation_id
        string event_key
        string channel
        string status
        string recipient_address
        json payload
        text rendered_content
    }

    GENERATED_DOCUMENT {
        int id
        int communication_id
        string path
        string disk
        string mime_type
    }
```
