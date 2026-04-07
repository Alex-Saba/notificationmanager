<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\CommunicationReactionPolicyInterface;
use Acl\Communications\Events\NotificationFailed;
use Acl\Communications\Events\NotificationSent;

class HostOwnedReactionPolicy implements CommunicationReactionPolicyInterface
{
    public function resolve(object $event): array
    {
        return match (true) {
            $event instanceof NotificationSent => [
                'owner' => 'host',
                'should_react' => true,
                'reaction' => 'acknowledge_delivery',
                'event_name' => $event->eventName(),
            ],
            $event instanceof NotificationFailed => [
                'owner' => 'host',
                'should_react' => true,
                'reaction' => 'handle_failure',
                'event_name' => $event->eventName(),
            ],
            default => [
                'owner' => 'host',
                'should_react' => false,
                'reaction' => 'ignore',
                'event_name' => $event::class,
            ],
        };
    }
}
