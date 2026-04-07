<?php

namespace Acl\Communications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunicationOrchestrated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(public array $result)
    {
    }

    public function eventName(): string
    {
        return 'communication.orchestrated';
    }
}
