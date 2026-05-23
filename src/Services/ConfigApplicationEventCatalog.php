<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ApplicationEventCatalogInterface;
use Acl\Communications\Contracts\CommunicationEventInterface;
use InvalidArgumentException;

class ConfigApplicationEventCatalog implements ApplicationEventCatalogInterface
{
    public function lookup(object $event): array
    {
        $catalog = config('communications.events.catalog', []);
        $definition = $catalog[$event::class] ?? [];

        if (! is_array($definition)) {
            $definition = [];
        }

        if (! $event instanceof CommunicationEventInterface && $definition === []) {
            throw new InvalidArgumentException(sprintf(
                'No communication catalog entry is configured for event [%s].',
                $event::class,
            ));
        }

        $context = $this->context($event, $definition);

        if (isset($definition['notifications']) && is_array($definition['notifications'])) {
            $shared = $definition;
            unset($shared['notifications']);

            $notifications = [];
            foreach ($definition['notifications'] as $notification) {
                if (! is_array($notification)) {
                    continue;
                }

                $notifications[] = $this->entry($event, array_replace_recursive($shared, $notification), $context);
            }

            return [
                'event_class' => $event::class,
                'notifications' => $notifications,
            ];
        }

        return $this->entry($event, $definition, $context);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function entry(object $event, array $definition, array $context): array
    {
        $eventKey = (string) ($definition['event_key'] ?? $event->communicationEventKey());
        $data = $event instanceof CommunicationEventInterface ? $event->communicationData() : [];

        $data = array_merge($data, (array) ($definition['data'] ?? []));
        foreach ((array) ($definition['data_map'] ?? []) as $target => $source) {
            if (is_string($target) && is_string($source)) {
                data_set($data, $target, $this->valueFromContext($context, $source));
            }
        }

        $recipient = $event instanceof CommunicationEventInterface ? $event->communicationRecipient() : [];
        $recipient = array_merge((array) $recipient, (array) ($definition['recipient'] ?? []));
        foreach ((array) ($definition['recipient_map'] ?? []) as $target => $source) {
            if (is_string($target) && is_string($source)) {
                data_set($recipient, $target, $this->valueFromContext($context, $source));
            }
        }

        unset(
            $definition['event_key'],
            $definition['data'],
            $definition['data_map'],
            $definition['recipient'],
            $definition['recipient_map'],
            $definition['models'],
            $definition['notifications'],
        );

        return array_merge([
            'event_class' => $event::class,
            'event_key' => $eventKey,
            'recipient' => $recipient,
            'data' => $data,
        ], $definition);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function context(object $event, array $definition): array
    {
        $context = ['event' => $event];

        foreach ((array) ($definition['models'] ?? []) as $alias => $modelConfig) {
            if (! is_string($alias) || ! is_array($modelConfig)) {
                continue;
            }

            $class = $modelConfig['class'] ?? null;
            $source = $modelConfig['source'] ?? $modelConfig['id'] ?? null;

            if (! is_string($class) || ! class_exists($class) || ! is_string($source)) {
                continue;
            }

            $value = $this->valueFromContext($context, $source);

            if ($value === null || $value === '') {
                $context[$alias] = null;
                continue;
            }

            $query = $class::query();
            $with = $modelConfig['with'] ?? [];

            if (is_array($with) && $with !== []) {
                $query->with($with);
            }

            $field = is_string($modelConfig['field'] ?? null) ? $modelConfig['field'] : 'id';
            $context[$alias] = $query->where($field, $value)->first();
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function valueFromContext(array $context, string $path): mixed
    {
        $segments = explode('.', $path, 2);
        $root = $segments[0] ?? '';

        if (array_key_exists($root, $context)) {
            return data_get($context[$root], $segments[1] ?? null);
        }

        return data_get($context['event'], $path);
    }
}
