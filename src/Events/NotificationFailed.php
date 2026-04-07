<?php

namespace Acl\Communications\Events;

use Acl\Communications\Models\Communication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public Communication $communication,
        public string $channel,
        public array $result = [],
    ) {
    }

    public function eventName(): string
    {
        return 'notification.failed.'.$this->channel;
    }
}
