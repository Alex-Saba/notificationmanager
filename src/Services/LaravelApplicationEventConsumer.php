<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ApplicationEventConsumerInterface;
use Acl\Communications\Contracts\CommunicationEventInterface;
use Acl\Communications\Contracts\CommunicationServiceInterface;
use InvalidArgumentException;

class LaravelApplicationEventConsumer implements ApplicationEventConsumerInterface
{
    public function __construct(protected CommunicationServiceInterface $communications)
    {
    }

    public function canConsume(object $event): bool
    {
        return $event instanceof CommunicationEventInterface
            || is_array(config('communications.events.catalog.'.$event::class));
    }

    public function consume(object $event): array
    {
        if (! $this->canConsume($event)) {
            throw new InvalidArgumentException(sprintf(
                'The event [%s] cannot be consumed by [%s] because it must implement [%s].',
                $event::class,
                self::class,
                CommunicationEventInterface::class,
            ));
        }

        return $this->communications->trigger($event);
    }
}
