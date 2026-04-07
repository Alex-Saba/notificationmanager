<?php

namespace Acl\Communications\Contracts;

interface ChannelDriverInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(string $channel, array $payload): array;
}
