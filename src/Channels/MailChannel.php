<?php

namespace Acl\Communications\Channels;

use Acl\Communications\Contracts\ChannelDriverInterface;
use Acl\Communications\Mail\CommunicationMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class MailChannel implements ChannelDriverInterface
{
    public function send(string $channel, array $payload): array
    {
        $recipientAddress = $payload['recipient']['address'] ?? null;

        if (! is_string($recipientAddress) || trim($recipientAddress) === '') {
            throw new RuntimeException('Aucune adresse email valide n\'a ete fournie pour le canal mail.');
        }

        $template = $payload['template'] ?? [];
        $subject = (string) ($template['subject'] ?? $template['name'] ?? 'Communication');
        $rendered = (string) ($payload['rendered'] ?? '');

        Mail::to($recipientAddress)->send(new CommunicationMail($subject, $rendered));

        return [
            'status' => 'sent',
            'channel' => $channel,
            'recipient' => $recipientAddress,
            'subject' => $subject,
        ];
    }
}
