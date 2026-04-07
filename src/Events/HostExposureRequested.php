<?php

namespace Acl\Communications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HostExposureRequested
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $exposure
     */
    public function __construct(
        public CommunicationOrchestrated $sourceEvent,
        public array $exposure,
    ) {
    }

    public function eventName(): string
    {
        return 'host.exposure.requested';
    }
}
