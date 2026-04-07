<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ChannelDriverInterface;
use Acl\Communications\Contracts\NotificationManagerInterface;
use Acl\Communications\Contracts\TemplateRendererInterface;
use Acl\Communications\Events\CommunicationOrchestrated;
use Acl\Communications\Events\NotificationFailed;
use Acl\Communications\Events\NotificationSent;
use Acl\Communications\Models\Communication;
use Acl\Communications\Models\NotificationEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NotificationManager implements NotificationManagerInterface
{
    public function __construct(
        protected TemplateRendererInterface $renderer,
        protected NotificationTemplateResolver $templates,
    ) {
    }

    public function dispatch(string $eventKey, array $payload, array $options = []): array
    {
        $event = NotificationEvent::query()
            ->where('key', $eventKey)
            ->where('is_active', true)
            ->firstOrFail();

        $this->validatePayload($payload, (array) ($event->payload_schema ?? []));

        $parsed = $this->parseEventKey($eventKey);
        $tenantId = isset($options['tenant_id']) ? (int) $options['tenant_id'] : null;
        $template = $this->templates->resolve($eventKey, $options, $tenantId);
        $rendered = $this->renderer->render((string) $template['template'], $payload);
        $recipient = $this->resolveRecipient($parsed['channel'], $payload, $options);

        $communication = Communication::query()->create([
            'correlation_id' => (string) Str::uuid(),
            'event_key' => $eventKey,
            'notification_event_id' => $event->id,
            'template_id' => $template['id'] ?? null,
            'channel' => $parsed['channel'],
            'status' => 'pending',
            'priority' => (int) ($options['priority'] ?? 100),
            'recipient_type' => $recipient['type'] ?? null,
            'recipient_id' => $recipient['id'] ?? null,
            'recipient_address' => $recipient['address'] ?? null,
            'attempts' => 1,
            'idempotency_key' => (string) Str::uuid(),
            'payload' => $payload,
            'rendered_content' => $rendered,
            'meta' => [
                'options' => $options,
                'parsed_event_key' => $parsed,
                'template_source' => $template['source'] ?? null,
            ],
            'queued_at' => Carbon::now(),
        ]);

        $driverPayload = [
            'event_key' => $eventKey,
            'event' => $event->toArray(),
            'parsed' => $parsed,
            'payload' => $payload,
            'options' => $options,
            'recipient' => $recipient,
            'template' => [
                'id' => $template['id'] ?? null,
                'key' => $template['key'] ?? null,
                'name' => $template['name'] ?? null,
                'subject' => $template['subject'] ?? null,
                'source' => $template['source'] ?? null,
            ],
            'rendered' => $rendered,
            'communication_id' => $communication->id,
        ];

        try {
            $response = $this->resolveDriver($parsed['channel'])->send($parsed['channel'], $driverPayload);
            $status = (string) ($response['status'] ?? 'sent');

            $communication->update([
                'status' => $status,
                'meta' => array_merge($communication->meta ?? [], [
                    'channel_response' => $response,
                ]),
                'sent_at' => $status === 'sent' ? Carbon::now() : null,
                'failed_at' => $status === 'failed' ? Carbon::now() : null,
                'error_message' => $status === 'failed' ? (string) ($response['error'] ?? 'Channel failure.') : null,
            ]);

            if ($status === 'sent') {
                event(new NotificationSent($communication->fresh(), $parsed['channel'], $response));
            } else {
                event(new NotificationFailed($communication->fresh(), $parsed['channel'], $response));
            }
        } catch (\Throwable $exception) {
            $communication->update([
                'status' => 'failed',
                'failed_at' => Carbon::now(),
                'error_message' => $exception->getMessage(),
            ]);

            $response = [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ];

            event(new NotificationFailed($communication->fresh(), $parsed['channel'], $response));
        }

        $result = [
            'event_id' => $communication->correlation_id,
            'event_key' => $eventKey,
            'status' => $communication->fresh()->status,
            'parsed' => $parsed,
            'template' => $template,
            'rendered' => $rendered,
            'payload' => $payload,
            'options' => $options,
            'recipient' => $recipient,
            'response' => $response,
            'communication_id' => $communication->id,
        ];

        event(new CommunicationOrchestrated($result));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rules
     */
    protected function validatePayload(array $payload, array $rules): void
    {
        Validator::make($payload, $rules)->validate();
    }

    /**
     * @return array{module:string,action:string,channel:string}
     */
    protected function parseEventKey(string $eventKey): array
    {
        $segments = explode('.', $eventKey);

        if (count($segments) !== 3 || in_array('', $segments, true)) {
            throw new InvalidArgumentException('Notification event keys must use the format <module>.<action>.<channel>.');
        }

        return [
            'module' => $segments[0],
            'action' => $segments[1],
            'channel' => $segments[2],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function resolveRecipient(string $channel, array $payload, array $options): array
    {
        $recipient = (array) ($options['recipient'] ?? []);

        return match ($channel) {
            'email' => [
                'address' => $recipient['address'] ?? $payload['user_email'] ?? $payload['email'] ?? null,
                'type' => $recipient['type'] ?? 'user',
                'id' => isset($recipient['id']) ? (string) $recipient['id'] : ($payload['user_id'] ?? null),
                'name' => $recipient['name'] ?? $payload['requester_name'] ?? $payload['name'] ?? null,
            ],
            'sms' => [
                'address' => $recipient['address'] ?? $payload['user_phone'] ?? $payload['phone'] ?? null,
                'type' => $recipient['type'] ?? 'user',
                'id' => isset($recipient['id']) ? (string) $recipient['id'] : ($payload['user_id'] ?? null),
            ],
            'in_app' => [
                'address' => null,
                'type' => $recipient['type'] ?? 'user',
                'id' => isset($recipient['id']) ? (string) $recipient['id'] : ($payload['user_id'] ?? null),
            ],
            default => [
                'address' => $recipient['address'] ?? null,
                'type' => $recipient['type'] ?? null,
                'id' => isset($recipient['id']) ? (string) $recipient['id'] : null,
            ],
        };
    }

    protected function resolveDriver(string $channel): ChannelDriverInterface
    {
        $configuredDriver = config("communications.channels.{$channel}.driver");

        if (is_string($configuredDriver) && class_exists($configuredDriver)) {
            $driver = app($configuredDriver);

            if ($driver instanceof ChannelDriverInterface) {
                return $driver;
            }
        }

        throw new InvalidArgumentException("Unsupported channel [{$channel}].");
    }
}
