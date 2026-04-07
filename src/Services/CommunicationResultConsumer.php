<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\CommunicationReactionPolicyInterface;
use Acl\Communications\Contracts\CommunicationResultConsumerInterface;
use Acl\Communications\Events\NotificationDocumentGenerated;
use Acl\Communications\Events\NotificationFailed;
use Acl\Communications\Events\NotificationSent;
use InvalidArgumentException;

class CommunicationResultConsumer implements CommunicationResultConsumerInterface
{
    public function __construct(protected CommunicationReactionPolicyInterface $policy)
    {
    }

    public function canConsume(object $event): bool
    {
        return $event instanceof NotificationSent
            || $event instanceof NotificationFailed
            || $event instanceof NotificationDocumentGenerated;
    }

    public function consume(object $event): array
    {
        if (! $this->canConsume($event)) {
            throw new InvalidArgumentException(sprintf(
                'The event [%s] cannot be consumed by [%s].',
                $event::class,
                self::class,
            ));
        }

        $decision = $this->policy->resolve($event);

        return [
            'owner' => $decision['owner'] ?? 'host',
            'should_react' => (bool) ($decision['should_react'] ?? false),
            'reaction' => $decision['reaction'] ?? 'ignore',
            'event_name' => $decision['event_name'] ?? $event::class,
            'event_class' => $event::class,
        ];
    }
}
