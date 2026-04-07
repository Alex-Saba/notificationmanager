<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ApplicationEventCatalogInterface;
use Acl\Communications\Contracts\CommunicationEventInterface;

class ConfigApplicationEventCatalog implements ApplicationEventCatalogInterface
{
    public function lookup(CommunicationEventInterface $event): array
    {
        $catalog = config('communications.events.catalog', []);
        $definition = $catalog[$event::class] ?? [];

        if (! is_array($definition)) {
            $definition = [];
        }

        $eventKey = (string) ($definition['event_key'] ?? $event->communicationEventKey());
        $data = array_merge(
            $event->communicationData(),
            (array) ($definition['data'] ?? []),
        );

        unset($definition['event_key'], $definition['data']);

        return array_merge([
            'event_class' => $event::class,
            'event_key' => $eventKey,
            'recipient' => $event->communicationRecipient(),
            'data' => $data,
        ], $definition);
    }
}
