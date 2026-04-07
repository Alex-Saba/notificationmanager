<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ApplicationEventCatalogInterface;
use Acl\Communications\Contracts\CommunicationEventInterface;
use Acl\Communications\Contracts\CommunicationServiceInterface;
use Acl\Communications\Contracts\NotificationManagerInterface;

class CommunicationService implements CommunicationServiceInterface
{
    public function __construct(
        protected ApplicationEventCatalogInterface $eventCatalog,
        protected NotificationManagerInterface $manager,
    ) {
    }

    public function trigger(CommunicationEventInterface $event): array
    {
        $catalogEntry = $this->eventCatalog->lookup($event);
        $payload = array_merge((array) ($catalogEntry['data'] ?? []), [
            'user_email' => $catalogEntry['recipient']['address'] ?? null,
            'user_id' => $catalogEntry['recipient']['id'] ?? null,
        ]);

        return $this->manager->dispatch(
            (string) $catalogEntry['event_key'],
            $payload,
            [
                'recipient' => (array) ($catalogEntry['recipient'] ?? []),
            ],
        );
    }
}
