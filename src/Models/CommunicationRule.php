<?php

namespace Acl\Communications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationRule extends Model
{
    protected $fillable = [
        'template_id',
        'event_key',
        'channels',
        'priority',
        'fallback',
        'delay',
        'active',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'fallback' => 'array',
            'conditions' => 'array',
            'active' => 'bool',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }
}
