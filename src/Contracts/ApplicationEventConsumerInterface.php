<?php

namespace Acl\Communications\Contracts;

interface ApplicationEventConsumerInterface
{
    public function canConsume(object $event): bool;

    /**
     * @return array<string, mixed>
     */
    public function consume(object $event): array;
}
