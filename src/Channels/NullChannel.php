<?php

namespace Acl\Communications\Channels;

use Acl\Communications\Contracts\ChannelDriverInterface;

class NullChannel implements ChannelDriverInterface
{
    public function send(string $channel, array $payload): array
    {
        return [
            'status' => 'sent',
            'channel' => $channel,
            'payload' => $payload,
        ];
    }
}
