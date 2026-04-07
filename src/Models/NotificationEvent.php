<?php

namespace Acl\Communications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    protected $fillable = [
        'key',
        'label',
        'payload_schema',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'payload_schema' => 'array',
            'is_active' => 'bool',
        ];
    }
}
