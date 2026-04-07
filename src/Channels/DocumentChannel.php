<?php

namespace Acl\Communications\Channels;

use Acl\Communications\Contracts\ChannelDriverInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentChannel implements ChannelDriverInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(string $channel, array $payload): array
    {
        $document = (string) ($payload['document'] ?? '');

        if ($document === '') {
            throw new RuntimeException('Aucun document a generer n\'a ete fourni au canal document.');
        }

        $communicationId = (string) ($payload['communication_id'] ?? '');
        $templateKey = trim((string) data_get($payload, 'template.key', 'communication'));
        $disk = (string) config('communications.documents.disk', 'local');
        $directory = trim((string) config('communications.documents.path', 'communications/documents'), '/');
        $filename = sprintf('%s-%s.html', $templateKey !== '' ? $templateKey : 'communication', $communicationId !== '' ? $communicationId : uniqid());
        $path = $directory.'/'.$filename;

        Storage::disk($disk)->put($path, $document);

        return [
            'status' => 'sent',
            'channel' => $channel,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => 'text/html',
            'size' => strlen($document),
            'checksum' => md5($document),
            'temporary' => true,
        ];
    }
}
