<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\CommunicationExposureConsumerInterface;
use Acl\Communications\Events\CommunicationOrchestrated;
use InvalidArgumentException;

class HostCommunicationExposureConsumer implements CommunicationExposureConsumerInterface
{
    public function canConsume(object $event): bool
    {
        return $event instanceof CommunicationOrchestrated;
    }

    public function consume(object $event): array
    {
        if (! $this->canConsume($event)) {
            throw new InvalidArgumentException(sprintf(
                'The event [%s] cannot be consumed by [%s].',
                $event::class,
                self::class,
            ));
        }

        $deliveries = collect((array) data_get($event->result, 'payload.emitted_result.deliveries', []));
        $channels = $deliveries
            ->map(fn (mixed $delivery) => is_array($delivery) ? ($delivery['channel'] ?? null) : null)
            ->filter(fn (mixed $channel) => is_string($channel) && $channel !== '')
            ->values()
            ->all();
        $communicationIds = $deliveries
            ->map(fn (mixed $delivery) => is_array($delivery) ? ($delivery['communication_id'] ?? null) : null)
            ->filter(fn (mixed $id) => $id !== null)
            ->values()
            ->all();

        return [
            'owner' => 'host',
            'should_expose' => true,
            'mode' => 'reference',
            'event_name' => $event->eventName(),
            'event_id' => $event->result['event_id'] ?? null,
            'status' => data_get($event->result, 'payload.emitted_result.status', $event->result['status'] ?? null),
            'channels' => $channels,
            'communication_ids' => $communicationIds,
            'rendered' => data_get($event->result, 'context.produced_rendering.content'),
        ];
    }
}
