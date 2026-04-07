<?php

namespace Acl\Communications\Listeners;

use Acl\Communications\Contracts\CommunicationExposureConsumerInterface;
use Acl\Communications\Events\CommunicationOrchestrated;
use Acl\Communications\Events\HostExposureRequested;

class CommunicationExposureListener
{
    public function __construct(protected CommunicationExposureConsumerInterface $consumer)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(object $event): array
    {
        $exposure = $this->consumer->consume($event);

        if ($event instanceof CommunicationOrchestrated
            && ($exposure['owner'] ?? null) === 'host'
            && ($exposure['should_expose'] ?? false) === true) {
            event(new HostExposureRequested($event, $exposure));
        }

        return $exposure;
    }
}
