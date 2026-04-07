<?php

namespace Acl\Communications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommunicationTemplate extends Model
{
    protected $fillable = [
        'name',
        'key',
        'event_key',
        'tenant_id',
        'channel',
        'subject',
        'content',
        'variables',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'active' => 'bool',
            'tenant_id' => 'integer',
        ];
    }

    public function rule(): HasOne
    {
        return $this->hasOne(CommunicationRule::class, 'template_id');
    }
}
