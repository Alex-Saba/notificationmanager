<?php

namespace Acl\Communications\Events;

use Acl\Communications\Models\Communication;
use Acl\Communications\Models\GeneratedDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationDocumentGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $result
     */
    public function __construct(
        public Communication $communication,
        public GeneratedDocument $document,
        public array $result = [],
    ) {
    }

    public function eventName(): string
    {
        return 'notification.document.generated';
    }
}
