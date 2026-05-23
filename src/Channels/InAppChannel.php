<?php

namespace Acl\Communications\Channels;

use Acl\Communications\Contracts\ChannelDriverInterface;

class InAppChannel implements ChannelDriverInterface
{
    public function send(string $channel, array $payload): array
    {
        $template = $payload['template'] ?? [];

        return [
            'status' => 'sent',
            'channel' => $channel,
            'title' => $template['subject'] ?? $template['name'] ?? 'Notification',
            'message' => $payload['rendered'] ?? '',
            'recipient' => $payload['recipient'] ?? [],
        ];
    }
}
