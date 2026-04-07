<?php

namespace Acl\Communications\Models;

use Illuminate\Database\Eloquent\Model;

class Communication extends Model
{
    protected $fillable = [
        'correlation_id',
        'event_key',
        'notification_event_id',
        'template_id',
        'rule_id',
        'channel',
        'status',
        'priority',
        'recipient_type',
        'recipient_id',
        'recipient_address',
        'attempts',
        'idempotency_key',
        'error_message',
        'payload',
        'rendered_content',
        'meta',
        'queued_at',
        'sent_at',
        'failed_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'meta' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
