<?php

use Acl\Communications\Channels\MailChannel;
use Acl\Communications\Channels\NullChannel;
use App\Events\RequestCreated;

return [
    'package_name' => 'acl/notification-manager',

    'ui' => [
        'enabled' => env('COMMUNICATIONS_UI_ENABLED', false),
        'prefix' => env('COMMUNICATIONS_UI_PREFIX', 'communications'),
        'middleware' => ['web'],
        'name_prefix' => 'communications.',
        'view' => env('COMMUNICATIONS_UI_VIEW', 'welcome'),
    ],

    'default_channel' => env('COMMUNICATIONS_DEFAULT_CHANNEL', 'mail'),

    'channels' => [
        'email' => [
            'driver' => MailChannel::class,
            'queue' => true,
            'queue_name' => env('COMMUNICATIONS_EMAIL_QUEUE', 'notifications.email'),
        ],
        'mail' => [
            'driver' => MailChannel::class,
            'queue' => true,
            'queue_name' => env('COMMUNICATIONS_MAIL_QUEUE', 'notifications.mail'),
        ],
        'sms' => [
            'driver' => NullChannel::class,
            'queue' => false,
            'queue_name' => env('COMMUNICATIONS_SMS_QUEUE', 'notifications.sms'),
        ],
        'in_app' => [
            'driver' => NullChannel::class,
            'queue' => false,
            'queue_name' => env('COMMUNICATIONS_IN_APP_QUEUE', 'notifications.in_app'),
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
            RequestCreated::class => [
                'event_key' => 'request.created.email',
                'name' => 'Request created',
                'description' => 'Nouvelle demande creee dans le projet principal.',
            ],
        ],
    ],
];
