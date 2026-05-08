<?php

namespace Acl\Communications\Services;

use Acl\Communications\Contracts\ChannelDriverInterface;
use Acl\Communications\Events\NotificationFailed;
use Acl\Communications\Events\NotificationSent;
use Acl\Communications\Models\Communication;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CommunicationDeliveryService
{
    public function send(Communication $communication, string $channel, array $payload): array
    {
        $communication->increment('attempts');

        try {
            $response = $this->resolveDriver($channel)->send($channel, $payload);
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
                event(new NotificationSent($communication->fresh(), $channel, $response));
            } else {
                event(new NotificationFailed($communication->fresh(), $channel, $response));
            }

            return $response;
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

            event(new NotificationFailed($communication->fresh(), $channel, $response));

            return $response;
        }
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
