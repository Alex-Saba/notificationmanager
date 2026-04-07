<?php

namespace Acl\Communications\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HostReactionRequested
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $decision
     */
    public function __construct(
        public object $sourceEvent,
        public array $decision,
    ) {
    }

    public function eventName(): string
    {
        return 'host.reaction.requested';
    }
}
