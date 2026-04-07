<?php

namespace Acl\Communications\Listeners;

use Acl\Communications\Contracts\ApplicationEventConsumerInterface;

class NotificationListener
{
    public function __construct(protected ApplicationEventConsumerInterface $consumer)
    {
    }

    public function handle(object $event): array
    {
        return $this->consumer->consume($event);
    }
}
