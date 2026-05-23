<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ApplicationEventCatalogInterface;
use Acl\Communications\Contracts\CommunicationServiceInterface;
use Acl\Communications\Contracts\NotificationManagerInterface;

class CommunicationService implements CommunicationServiceInterface
{
    public function __construct(
        protected ApplicationEventCatalogInterface $eventCatalog,
        protected NotificationManagerInterface $manager,
    ) {
    }

    public function trigger(object $event): array
    {
        $catalogEntry = $this->eventCatalog->lookup($event);
        $entries = isset($catalogEntry['notifications']) && is_array($catalogEntry['notifications'])
            ? $catalogEntry['notifications']
            : [$catalogEntry];

        $results = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $payload = array_merge((array) ($entry['data'] ?? []), [
                'user_email' => $entry['recipient']['address'] ?? null,
                'user_id' => $entry['recipient']['id'] ?? null,
            ]);

            $results[] = $this->manager->dispatch(
                (string) $entry['event_key'],
                $payload,
                [
                    'recipient' => (array) ($entry['recipient'] ?? []),
                ],
            );
        }

        if (count($results) === 1) {
            return $results[0];
        }

        return [
            'status' => 'triggered',
            'event_class' => $event::class,
            'results' => $results,
        ];
    }
}
