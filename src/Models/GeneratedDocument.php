<?php

namespace Acl\Communications\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedDocument extends Model
{
    protected $fillable = [
        'communication_id',
        'template_id',
        'channel',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'checksum',
        'meta',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
