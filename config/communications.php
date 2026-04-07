<?php

return [
    'package_name' => 'acl/communications',

    'ui' => [
        'enabled' => env('COMMUNICATIONS_UI_ENABLED', true),
        'prefix' => env('COMMUNICATIONS_UI_PREFIX', 'communications'),
        'middleware' => ['web'],
        'name_prefix' => 'communications.',
        'view' => env('COMMUNICATIONS_UI_VIEW', 'welcome'),
    ],

    'default_channel' => env('COMMUNICATIONS_DEFAULT_CHANNEL', 'mail'),

    'channels' => [
        'email' => [
            'driver' => Acl\Communications\Channels\MailChannel::class,
            'queue' => true,
        ],
        'mail' => [
            'driver' => Acl\Communications\Channels\MailChannel::class,
            'queue' => true,
        ],
        'sms' => [
            'driver' => Acl\Communications\Channels\NullChannel::class,
            'queue' => false,
        ],
        'in_app' => [
            'driver' => Acl\Communications\Channels\NullChannel::class,
            'queue' => false,
        ],
    ],

    'templates' => [
        'path' => resource_path('views/communications'),
        'default_extension' => 'blade.php',
        'catalog' => [
            'user-welcome' => [
                'view' => 'communications.user-welcome',
            ],
        ],
    ],

    'events' => [
        'catalog' => [
            App\Events\RequestCreated::class => [
                'event_key' => 'request.created.email',
                'name' => 'Request created',
                'description' => 'Nouvelle demande creee dans le projet principal.',
            ],
        ],
    ],
];
