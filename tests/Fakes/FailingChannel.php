<?php

namespace Tests\Fakes;

use Acl\Communications\Contracts\ChannelDriverInterface;

class FailingChannel implements ChannelDriverInterface
{
    public function send(string $channel, array $payload): array
    {
        return [
            'status' => 'failed',
            'channel' => $channel,
            'error' => 'Simulated failure',
            'payload' => $payload,
        ];
    }
}
