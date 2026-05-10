<?php

return [
    'path' => env('TEMPLATES_PATH', storage_path('app/private/templates')),
    'seed_defaults' => env('TEMPLATES_SEED_DEFAULTS', true),

    // Mode d'utilisation :
    // - le projet principal déclare explicitement les entités et les propriétés disponibles
    // - ajoutez une entrée par entité à exposer dans les tags disponibles pour les templates
    //
    // Exemple :
    // [
    //     'model' => 'Company',
    //     'variable' => '$company',
    //     'properties' => ['name', 'address'],
    // ]

    'tag_entities' => [
        [
            'model' => 'User',
            'variable' => '$user',
            'properties' => [
                'name',
                'email',
            ],
        ],
    ],
];
