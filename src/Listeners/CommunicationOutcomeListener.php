<?php

namespace Acl\Communications\Listeners;

use Acl\Communications\Contracts\CommunicationResultConsumerInterface;
use Acl\Communications\Events\HostReactionRequested;

class CommunicationOutcomeListener
{
    public function __construct(protected CommunicationResultConsumerInterface $consumer)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(object $event): array
    {
        $decision = $this->consumer->consume($event);

        if (($decision['owner'] ?? null) === 'host' && ($decision['should_react'] ?? false) === true) {
            event(new HostReactionRequested($event, $decision));
        }

        return $decision;
    }
}
