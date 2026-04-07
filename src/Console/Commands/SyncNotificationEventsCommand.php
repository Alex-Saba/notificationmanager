<?php

namespace Acl\Communications\Console\Commands;

use Acl\Communications\Models\NotificationEvent;
use Illuminate\Console\Command;

class SyncNotificationEventsCommand extends Command
{
    protected $signature = 'notifications:sync';

    protected $description = 'Synchronize configured notification events into the database runtime catalog';

    public function handle(): int
    {
        $events = config('events', []);

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
        }

        $this->info('Notification events synchronized.');

        return self::SUCCESS;
    }
}
