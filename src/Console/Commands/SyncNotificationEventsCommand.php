<?php

namespace Acl\Communications\Console\Commands;

use Acl\Communications\Models\CommunicationTemplate;
use Acl\Communications\Models\NotificationEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncNotificationEventsCommand extends Command
{
    protected $signature = 'notifications:sync';

    protected $description = 'Synchronize configured notification events into the database runtime catalog';

    public function handle(): int
    {
        $events = config('events', []);

        DB::transaction(function () use ($events): void {
            foreach ($events as $key => $event) {
                if (! is_string($key) || ! is_array($event)) {
                    continue;
                }

                NotificationEvent::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'label' => (string) ($event['label'] ?? $key),
                        'payload_schema' => $event['payload'] ?? [],
                        'is_active' => (bool) ($event['is_active'] ?? true),
                    ],
                );

                $this->syncTemplate($key, $event);
            }
        });

        $this->info('Notification events synchronized.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function syncTemplate(string $eventKey, array $event): void
    {
        if (! isset($event['template'])) {
            return;
        }

        $templateKey = (string) ($event['template_key'] ?? $eventKey);
        $channel = (string) ($event['channel'] ?? $this->channelFromEventKey($eventKey));
        $isActive = (bool) ($event['is_active'] ?? true);

        $template = CommunicationTemplate::query()->updateOrCreate(
            ['key' => $templateKey],
            [
                'name' => (string) ($event['name'] ?? $event['label'] ?? $eventKey),
                'event_key' => $eventKey,
                'tenant_id' => $event['tenant_id'] ?? null,
                'channel' => $channel,
                'subject' => $event['subject'] ?? null,
                'content' => (string) $event['template'],
                'variables' => $event['payload'] ?? [],
                'active' => $isActive,
            ],
        );

        $template->rule()->updateOrCreate(
            ['template_id' => $template->id],
            [
                'event_key' => $eventKey,
                'channels' => $event['channels'] ?? [$channel],
                'priority' => (int) ($event['priority'] ?? 100),
                'fallback' => $event['fallback'] ?? [],
                'delay' => (int) ($event['delay'] ?? 0),
                'active' => $isActive,
                'conditions' => $event['conditions'] ?? null,
            ],
        );
    }

    protected function channelFromEventKey(string $eventKey): string
    {
        $segments = explode('.', $eventKey);

        return (string) end($segments);
    }
}
