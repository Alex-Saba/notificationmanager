<?php

namespace Acl\Communications\Jobs;

use Acl\Communications\Models\Communication;
use Acl\Communications\Services\CommunicationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCommunicationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $communicationId,
        public readonly string $channel,
        public readonly array $payload,
    ) {}

    public function handle(CommunicationDeliveryService $delivery): void
    {
        $communication = Communication::query()->find($this->communicationId);

        if (! $communication instanceof Communication) {
            return;
        }

        $delivery->send($communication, $this->channel, $this->payload);
    }
}
