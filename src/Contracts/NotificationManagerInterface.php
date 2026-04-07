<?php

namespace Acl\Communications\Contracts;

interface NotificationManagerInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function dispatch(string $eventKey, array $payload, array $options = []): array;
}
