<?php

namespace Acl\Communications\Services;

use Acl\Communications\Models\CommunicationTemplate;
use RuntimeException;

class NotificationTemplateResolver
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function resolve(string $eventKey, array $options = [], ?int $tenantId = null): array
    {
        if (isset($options['template'])) {
            return [
                'template' => (string) $options['template'],
                'subject' => $options['subject'] ?? null,
                'source' => 'options',
            ];
        }

        $template = CommunicationTemplate::query()
            ->where('event_key', $eventKey)
            ->when(
                $tenantId !== null,
                fn ($query) => $query->where('tenant_id', $tenantId),
                fn ($query) => $query->whereNull('tenant_id'),
            )
            ->where('active', true)
            ->latest('id')
            ->first();

        if ($template) {
            return [
                'id' => $template->id,
                'key' => $template->key,
                'event_key' => $template->event_key,
                'name' => $template->name,
                'template' => $template->content,
                'subject' => $template->subject,
                'tenant_id' => $template->tenant_id,
                'source' => 'database',
            ];
        }

        $config = config('events', [])[$eventKey] ?? null;

        if (is_array($config) && isset($config['template'])) {
            return [
                'template' => (string) $config['template'],
                'subject' => $config['subject'] ?? null,
                'source' => 'config',
            ];
        }

        throw new RuntimeException("No template found for event [{$eventKey}].");
    }
}
